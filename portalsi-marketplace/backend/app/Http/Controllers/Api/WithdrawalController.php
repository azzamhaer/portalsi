<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Setting;
use App\Models\UserWalletTransaction;
use App\Models\UserWithdrawal;
use App\Models\Withdrawal;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WithdrawalController extends Controller
{
    /**
     * Hitung saldo yang bisa ditarik oleh vendor.
     * = total dari OrderItem pada Order berstatus DONE
     *   dikurangi platform commission %
     *   dikurangi withdrawal yang sudah dibuat (PENDING/APPROVED/PAID)
     */
    public function balance(Request $request)
    {
        $vendor = $request->user()->vendor;
        if (!$vendor) return response()->json(['message' => 'Belum punya toko'], 404);
        if ($vendor->verification_status !== 'APPROVED') {
            return response()->json(['message' => 'Toko belum terverifikasi'], 403);
        }

        $commissionPct = (float) (Setting::get('commission_percent', 5));

        $grossEarning = (int) OrderItem::where('vendor_id', $vendor->id)
            ->whereHas('order', fn($q) => $this->sellerBalanceOrderScope($q))
            ->sum(DB::raw('price * quantity'));

        $commission  = (int) round($grossEarning * $commissionPct / 100);
        $netEarning  = $grossEarning - $commission;

        $withdrawn = (int) Withdrawal::where('vendor_id', $vendor->id)
            ->whereIn('status', ['PENDING','APPROVED','PAID'])
            ->sum('amount');

        $available = max(0, $netEarning - $withdrawn);

        return response()->json([
            'commission_percent' => $commissionPct,
            'gross_earning'      => $grossEarning,
            'commission'         => $commission,
            'net_earning'        => $netEarning,
            'withdrawn'          => $withdrawn,
            'available'          => $available,
            'bank' => [
                'name'    => $vendor->bank_name,
                'account' => $vendor->bank_account,
                'holder'  => $vendor->bank_holder,
            ],
            'history' => Withdrawal::where('vendor_id', $vendor->id)->orderByDesc('id')->take(50)->get(),
        ]);
    }

    public function request(Request $request)
    {
        $vendor = $request->user()->vendor;
        if (!$vendor) return response()->json(['message' => 'Belum punya toko'], 404);
        if ($vendor->verification_status !== 'APPROVED') return response()->json(['message' => 'Toko belum terverifikasi'], 403);
        if (!$vendor->bank_name || !$vendor->bank_account || !$vendor->bank_holder)
            return response()->json(['message' => 'Lengkapi data bank di Profil Toko dulu'], 422);

        $data = $request->validate(['amount' => 'required|integer|min:10000']);

        $commissionPct = (float) (Setting::get('commission_percent', 5));
        $grossEarning = (int) OrderItem::where('vendor_id', $vendor->id)
            ->whereHas('order', fn($q) => $this->sellerBalanceOrderScope($q))
            ->sum(DB::raw('price * quantity'));
        $netEarning = $grossEarning - (int) round($grossEarning * $commissionPct / 100);

        $withdrawn = (int) Withdrawal::where('vendor_id', $vendor->id)
            ->whereIn('status', ['PENDING','APPROVED','PAID'])->sum('amount');
        $available = max(0, $netEarning - $withdrawn);

        if ($data['amount'] > $available)
            return response()->json(['message' => "Saldo tidak cukup. Tersedia: Rp " . number_format($available, 0, ',', '.')], 422);

        $w = Withdrawal::create([
            'vendor_id'    => $vendor->id,
            'amount'       => $data['amount'],
            'bank_name'    => $vendor->bank_name,
            'bank_account' => $vendor->bank_account,
            'bank_holder'  => $vendor->bank_holder,
            'status'       => 'PENDING',
        ]);

        return response()->json($w, 201);
    }

    public function cancel(Request $request, $id)
    {
        $vendor = $request->user()->vendor;
        if (!$vendor) return response()->json(['message' => 'Belum punya toko'], 404);
        if ($vendor->verification_status !== 'APPROVED') return response()->json(['message' => 'Toko belum terverifikasi'], 403);
        $w = Withdrawal::where('vendor_id', $vendor?->id)->findOrFail($id);
        if ($w->status !== 'PENDING') return response()->json(['message' => 'Hanya request PENDING yang bisa dibatalkan'], 422);
        $w->delete();
        return response()->json(['ok' => true]);
    }

    /* ========== ADMIN ========== */

    public function adminList(Request $request)
    {
        $status = $request->query('status');
        $seller = Withdrawal::with('vendor:id,name,user_id,bank_name,bank_account,bank_holder')
            ->when($status, fn($q) => $q->where('status', $status))
            ->get()
            ->map(function ($w) {
                $w->display_id = (string) $w->id;
                $w->withdrawer_type = 'SELLER';
                return $w;
            });
        $buyer = UserWithdrawal::with('user:id,name,email,phone')
            ->when($status, fn($q) => $q->where('status', $status))
            ->get()
            ->map(function ($w) {
                $w->display_id = 'user-' . $w->id;
                $w->withdrawer_type = 'USER';
                return $w;
            });

        $items = $seller->concat($buyer)->sortByDesc('created_at')->values();
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 30;
        return response()->json(new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        ));
    }

    public function adminProcess(Request $request, $id)
    {
        $data = $request->validate([
            'status' => 'required|in:APPROVED,REJECTED,PAID',
            'admin_note' => 'nullable|string',
        ]);
        if (str_starts_with((string) $id, 'user-')) {
            $w = UserWithdrawal::with('user')->findOrFail((int) substr((string) $id, 5));
            $w->update([
                'status'       => $data['status'],
                'admin_note'   => $data['admin_note'] ?? null,
                'processed_at' => now(),
            ]);
            \App\Models\UserNotification::send(
                $w->user_id,
                'USER_WITHDRAW_' . $data['status'],
                "Penarikan saldo Rp " . number_format($w->amount, 0, ',', '.') . " - {$data['status']}",
                "Permintaan penarikan saldo Anda sebesar Rp " . number_format($w->amount, 0, ',', '.') . " telah diubah ke status {$data['status']}." . ($data['admin_note'] ? "\nCatatan: " . $data['admin_note'] : ''),
                '/profile',
                $data['status'] === 'PAID' ? 'SUCCESS' : ($data['status'] === 'REJECTED' ? 'DANGER' : 'INFO'),
                ['user_withdrawal_id' => $w->id]
            );
            return response()->json($w);
        }
        $w = Withdrawal::with('vendor.user')->findOrFail($id);
        $w->update([
            'status'       => $data['status'],
            'admin_note'   => $data['admin_note'] ?? null,
            'processed_at' => now(),
        ]);
        if ($w->vendor?->user_id) {
            $sev = match($data['status']) { 'PAID' => 'SUCCESS', 'REJECTED' => 'DANGER', default => 'INFO' };
            \App\Models\UserNotification::send(
                $w->vendor->user_id, 'WITHDRAW_' . $data['status'],
                "Penarikan Rp " . number_format($w->amount, 0, ',', '.') . " — {$data['status']}",
                "Permintaan penarikan Anda sebesar Rp " . number_format($w->amount, 0, ',', '.') . " telah diubah ke status {$data['status']}." . ($data['admin_note'] ? "\nCatatan: " . $data['admin_note'] : ''),
                '/seller/withdraw', $sev,
                ['withdrawal_id' => $w->id]
            );
        }
        return response()->json($w);
    }

    public function userBalance(Request $request)
    {
        $user = $request->user();
        $gross = (int) UserWalletTransaction::where('user_id', $user->id)->sum('amount');
        $withdrawn = (int) UserWithdrawal::where('user_id', $user->id)
            ->whereIn('status', ['PENDING', 'APPROVED', 'PAID'])
            ->sum('amount');

        return response()->json([
            'gross' => $gross,
            'withdrawn' => $withdrawn,
            'available' => max(0, $gross - $withdrawn),
            'transactions' => UserWalletTransaction::where('user_id', $user->id)->orderByDesc('id')->take(30)->get(),
            'history' => UserWithdrawal::where('user_id', $user->id)->orderByDesc('id')->take(30)->get(),
        ]);
    }

    public function userRequest(Request $request)
    {
        $data = $request->validate([
            'amount' => 'required|integer|min:10000',
            'bank_name' => 'required|string|max:50',
            'bank_account' => 'required|string|max:30',
            'bank_holder' => 'required|string|max:255',
        ]);

        $balance = $this->userBalance($request)->getData(true);
        if ($data['amount'] > (int) $balance['available']) {
            return response()->json(['message' => 'Saldo tidak cukup. Tersedia: Rp ' . number_format((int) $balance['available'], 0, ',', '.')], 422);
        }

        return response()->json(UserWithdrawal::create([
            'user_id' => $request->user()->id,
            'amount' => $data['amount'],
            'bank_name' => $data['bank_name'],
            'bank_account' => $data['bank_account'],
            'bank_holder' => $data['bank_holder'],
            'status' => 'PENDING',
        ]), 201);
    }

    private function sellerBalanceOrderScope($q)
    {
        $cutoff = now()->subDays(7)->toDateTimeString();
        return $q->where(function ($qq) use ($cutoff) {
            $qq->where('status', 'DONE')
                ->orWhere(function ($auto) use ($cutoff) {
                    $auto->where('status', 'ARRIVED')
                        ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(shipping_payload, '$.arrived_at')) <= ?", [$cutoff]);
                });
        });
    }
}
