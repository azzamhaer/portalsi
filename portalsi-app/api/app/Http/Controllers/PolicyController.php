<?php

namespace App\Http\Controllers;

use App\Models\Policy;
use App\Models\PolicyAcceptance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PolicyController extends Controller
{
    // ══════════════════════════════════════════════════════════
    // SISI PENGGUNA
    // ══════════════════════════════════════════════════════════

    /**
     * Kebijakan aktif yang BELUM disetujui pengguna (versi terbaru).
     * Dipakai modal wajib-baca saat login.
     */
    public function active(Request $request)
    {
        $userId = Auth::id();

        $policies = Policy::where('is_active', true)
            ->orderBy('created_at')
            ->get();

        // Ambil persetujuan pengguna untuk kebijakan-kebijakan ini.
        $accepted = PolicyAcceptance::where('user_id', $userId)
            ->whereIn('policy_id', $policies->pluck('id'))
            ->get()
            ->keyBy('policy_id');

        $pending = $policies->filter(function ($p) use ($accepted) {
            $a = $accepted->get($p->id);
            // Belum pernah setuju, atau versi kebijakan sudah naik sejak terakhir setuju.
            return ! $a || (int) $a->policy_version < (int) $p->version;
        })->map(fn ($p) => $this->publicPayload($p))->values();

        return response()->json(['policies' => $pending]);
    }

    /** Pengguna menyetujui sebuah kebijakan. Dicatat sebagai bukti. */
    public function accept(Request $request, $id)
    {
        $policy = Policy::where('is_active', true)->findOrFail($id);
        $userId = Auth::id();

        PolicyAcceptance::updateOrCreate(
            ['policy_id' => $policy->id, 'user_id' => $userId],
            [
                'policy_version' => $policy->version,
                'accepted_at' => now(),
                'ip_address' => substr((string) $request->ip(), 0, 64),
            ]
        );

        return response()->json(['message' => 'Persetujuan tercatat.']);
    }

    // ══════════════════════════════════════════════════════════
    // SISI ADMIN (admin.panel)
    // ══════════════════════════════════════════════════════════

    /** Daftar semua kebijakan + ringkasan jumlah persetujuan. */
    public function index()
    {
        $totalUsers = User::count();

        $policies = Policy::orderByDesc('created_at')->get()->map(function ($p) use ($totalUsers) {
            $acceptedCurrent = PolicyAcceptance::where('policy_id', $p->id)
                ->where('policy_version', $p->version)
                ->count();

            return array_merge($this->adminPayload($p), [
                'accepted_count' => $acceptedCurrent,
                'total_users' => $totalUsers,
                'pending_count' => max(0, $totalUsers - $acceptedCurrent),
            ]);
        });

        return response()->json(['policies' => $policies]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = Auth::id();
        $data['version'] = 1;

        $policy = Policy::create($data);

        return response()->json(['policy' => $this->adminPayload($policy)], 201);
    }

    public function update(Request $request, $id)
    {
        $policy = Policy::findOrFail($id);
        $data = $this->validated($request);

        // Bila konten berubah, naikkan versi agar pengguna diminta setuju ulang —
        // kecuali hanya mengubah status aktif/nonaktif.
        $contentChanged = $policy->title !== $data['title']
            || $policy->description !== ($data['description'] ?? null)
            || json_encode($policy->slides) !== json_encode($data['slides'])
            || (int) $policy->read_seconds !== (int) $data['read_seconds']
            || (bool) $policy->require_agreement !== (bool) $data['require_agreement']
            || $policy->agreement_text !== ($data['agreement_text'] ?? null);

        if ($contentChanged || $request->boolean('bump_version')) {
            $data['version'] = $policy->version + 1;
        }

        $policy->update($data);

        return response()->json(['policy' => $this->adminPayload($policy->fresh())]);
    }

    public function destroy($id)
    {
        $policy = Policy::findOrFail($id);
        PolicyAcceptance::where('policy_id', $policy->id)->delete();
        $policy->delete();

        return response()->json(['message' => 'Kebijakan dihapus.']);
    }

    public function toggle(Request $request, $id)
    {
        $policy = Policy::findOrFail($id);
        $policy->is_active = ! $policy->is_active;
        $policy->save();

        return response()->json(['policy' => $this->adminPayload($policy)]);
    }

    /** Riwayat persetujuan: siapa yang sudah & belum menyetujui (versi terbaru). */
    public function acceptances(Request $request, $id)
    {
        $policy = Policy::findOrFail($id);
        $perPage = max(1, min(100, (int) $request->input('per_page', 30)));
        $status = $request->input('status', 'accepted'); // accepted | pending

        if ($status === 'pending') {
            // Pengguna yang BELUM menyetujui versi terbaru.
            $acceptedIds = PolicyAcceptance::where('policy_id', $policy->id)
                ->where('policy_version', $policy->version)
                ->pluck('user_id');

            $paginated = User::whereNotIn('user_id', $acceptedIds)
                ->select('user_id', 'username', 'full_name', 'profile_picture_thumb_url')
                ->orderBy('username')
                ->paginate($perPage);

            $rows = $paginated->getCollection()->map(fn ($u) => [
                'user_id' => (int) $u->user_id,
                'username' => $u->username,
                'full_name' => $u->full_name,
                'profile_picture_thumb_url' => $u->profile_picture_thumb_url,
                'accepted_at' => null,
            ]);
        } else {
            $paginated = PolicyAcceptance::where('policy_id', $policy->id)
                ->where('policy_version', $policy->version)
                ->with(['user:user_id,username,full_name,profile_picture_thumb_url'])
                ->orderByDesc('accepted_at')
                ->paginate($perPage);

            $rows = $paginated->getCollection()->map(fn ($a) => [
                'user_id' => (int) $a->user_id,
                'username' => $a->user->username ?? '—',
                'full_name' => $a->user->full_name ?? null,
                'profile_picture_thumb_url' => $a->user->profile_picture_thumb_url ?? null,
                'accepted_at' => optional($a->accepted_at)->toIso8601String(),
            ]);
        }

        return response()->json([
            'policy_id' => (int) $policy->id,
            'version' => (int) $policy->version,
            'status' => $status,
            'data' => $rows,
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // HELPER
    // ══════════════════════════════════════════════════════════

    private function validated(Request $request): array
    {
        $v = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:1000',
            'slides' => 'required|array|min:1',
            'slides.*.title' => 'nullable|string|max:200',
            'slides.*.body' => 'nullable|string|max:5000',
            'slides.*.image_url' => 'nullable|string|max:1000',
            'read_seconds' => 'required|integer|min:0|max:120',
            'require_agreement' => 'boolean',
            'agreement_text' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        // Bersihkan slide kosong (tanpa judul, isi, maupun gambar).
        $v['slides'] = collect($v['slides'])
            ->map(fn ($s) => [
                'title' => trim((string) ($s['title'] ?? '')),
                'body' => trim((string) ($s['body'] ?? '')),
                'image_url' => trim((string) ($s['image_url'] ?? '')),
            ])
            ->filter(fn ($s) => $s['title'] !== '' || $s['body'] !== '' || $s['image_url'] !== '')
            ->values()
            ->all();

        if (empty($v['slides'])) {
            abort(422, 'Minimal satu halaman kebijakan harus memiliki isi.');
        }

        $v['require_agreement'] = $request->boolean('require_agreement', true);
        $v['is_active'] = $request->boolean('is_active', false);

        return $v;
    }

    private function publicPayload(Policy $p): array
    {
        return [
            'id' => (int) $p->id,
            'title' => $p->title,
            'description' => $p->description,
            'slides' => $p->slides ?? [],
            'read_seconds' => (int) $p->read_seconds,
            'require_agreement' => (bool) $p->require_agreement,
            'agreement_text' => $p->agreement_text,
            'version' => (int) $p->version,
        ];
    }

    private function adminPayload(Policy $p): array
    {
        return array_merge($this->publicPayload($p), [
            'is_active' => (bool) $p->is_active,
            'created_at' => optional($p->created_at)->toIso8601String(),
            'updated_at' => optional($p->updated_at)->toIso8601String(),
        ]);
    }
}
