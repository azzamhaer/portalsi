<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Report;
use App\Models\UserNotification;
use App\Models\Vendor;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function categories()
    {
        return response()->json(Report::CATEGORIES);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'target_type' => 'required|in:PRODUCT,VENDOR',
            'target_id'   => 'required|integer',
            'category'    => 'required|string|in:' . implode(',', array_keys(Report::CATEGORIES)),
            'description' => 'required|string|min:10|max:2000',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'string', // data URI
        ]);

        // Validate target exists
        if ($data['target_type'] === 'PRODUCT' && !Product::find($data['target_id'])) {
            return response()->json(['message' => 'Produk tidak ditemukan'], 422);
        }
        if ($data['target_type'] === 'VENDOR' && !Vendor::find($data['target_id'])) {
            return response()->json(['message' => 'Toko tidak ditemukan'], 422);
        }

        $report = Report::create([
            'reporter_user_id' => $request->user()->id,
            'target_type'      => $data['target_type'],
            'target_id'        => $data['target_id'],
            'category'         => $data['category'],
            'description'      => $data['description'],
            'attachments'      => $data['attachments'] ?? null,
            'status'           => 'OPEN',
        ]);

        return response()->json($report, 201);
    }

    /** ============ ADMIN ============ */

    /**
     * Listing untuk admin — grouped berdasarkan target dan diurutkan berdasarkan jumlah laporan.
     */
    public function adminGroupedList(Request $request)
    {
        $filter = $request->query('status', 'OPEN'); // OPEN|ALL|RESOLVED|REJECTED
        $q = Report::query();
        if ($filter !== 'ALL') $q->where('status', $filter);

        $reports = $q->orderByDesc('id')->get();

        // Group by target
        $grouped = [];
        foreach ($reports as $r) {
            $key = $r->target_type . ':' . $r->target_id;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'target_type' => $r->target_type,
                    'target_id'   => $r->target_id,
                    'target'      => $this->loadTarget($r->target_type, $r->target_id),
                    'count'       => 0,
                    'reports'     => [],
                    'categories'  => [],
                ];
            }
            $grouped[$key]['count']++;
            $grouped[$key]['categories'][$r->category] = ($grouped[$key]['categories'][$r->category] ?? 0) + 1;
            $grouped[$key]['reports'][] = $r->load('reporter:id,name,email');
        }
        // Sort by count desc
        $values = array_values($grouped);
        usort($values, fn($a, $b) => $b['count'] - $a['count']);
        return response()->json($values);
    }

    private function loadTarget(string $type, int $id)
    {
        if ($type === 'PRODUCT') {
            return Product::with('vendor:id,name,username,user_id')->find($id);
        }
        return Vendor::with('user:id,name,email')->find($id);
    }

    public function adminResolve(Request $request, $id)
    {
        $report = Report::findOrFail($id);
        $data = $request->validate([
            'status'         => 'required|in:RESOLVED,REJECTED,REVIEWING',
            'admin_response' => 'nullable|string|max:2000',
            'action'         => 'nullable|in:DELETE_PRODUCT,DEACTIVATE_PRODUCT,DISABLE_VENDOR,BAN_VENDOR,NONE',
            'action_reason'  => 'nullable|string|max:1000',
        ]);

        $report->status         = $data['status'];
        $report->admin_response = $data['admin_response'] ?? null;
        $report->resolved_at    = in_array($data['status'], ['RESOLVED','REJECTED']) ? now() : null;
        $report->resolved_by    = $request->user()->id;
        $report->save();

        // Eksekusi tindakan (opsional)
        if (!empty($data['action']) && $data['action'] !== 'NONE') {
            $this->executeAction($report, $data['action'], $data['action_reason'] ?? '');
        }

        // Notifikasi ke reporter
        UserNotification::send(
            $report->reporter_user_id,
            'REPORT_RESPONSE',
            'Laporan Anda diproses',
            "Laporan Anda telah {$report->status}. " . ($data['admin_response'] ?? ''),
            null,
            $data['status'] === 'RESOLVED' ? 'SUCCESS' : ($data['status'] === 'REJECTED' ? 'WARNING' : 'INFO'),
            [
                'report_id' => $report->id,
                'status' => $report->status,
                'admin_response' => $report->admin_response,
                'target_type' => $report->target_type,
                'target_id' => $report->target_id,
            ]
        );

        return response()->json($report->fresh());
    }

    private function executeAction(Report $report, string $action, string $reason): void
    {
        $reason = $reason ?: 'Pelanggaran kebijakan';
        if ($report->target_type === 'PRODUCT') {
            $product = Product::with('vendor.user')->find($report->target_id);
            if (!$product) return;
            if ($action === 'DELETE_PRODUCT') {
                $product->delete();
            } elseif ($action === 'DEACTIVATE_PRODUCT') {
                $product->update(['is_active' => false]);
            }
            if ($product->vendor?->user_id) {
                UserNotification::send(
                    $product->vendor->user_id,
                    'PRODUCT_ACTION',
                    "Produk \"{$product->name}\" " . ($action === 'DELETE_PRODUCT' ? 'dihapus' : 'dinonaktifkan'),
                    "Produk \"{$product->name}\" " . ($action === 'DELETE_PRODUCT' ? 'dihapus' : 'dinonaktifkan') . " oleh admin.\nAlasan: {$reason}",
                    null, 'DANGER',
                    ['product_id' => $product->id, 'reason' => $reason]
                );
            }
        } elseif ($report->target_type === 'VENDOR') {
            $vendor = Vendor::with('user')->find($report->target_id);
            if (!$vendor) return;
            if ($action === 'DISABLE_VENDOR') {
                $vendor->update([
                    'moderation_mode'        => 'DISABLED',
                    'admin_warning'          => $reason,
                    'warning_dismissed_at'   => null,
                ]);
            } elseif ($action === 'BAN_VENDOR') {
                $vendor->update([
                    'is_banned'              => true,
                    'ban_reason'             => $reason,
                    'moderation_mode'        => 'DISABLED',
                    'admin_warning'          => 'Toko Anda dibanned permanen. ' . $reason,
                    'warning_dismissed_at'   => null,
                ]);
            }
            if ($vendor->user_id) {
                UserNotification::send(
                    $vendor->user_id,
                    'VENDOR_ACTION',
                    $action === 'BAN_VENDOR' ? 'Akun toko Anda dibanned' : 'Toko Anda dinonaktifkan',
                    "Akun toko \"{$vendor->name}\" " . ($action === 'BAN_VENDOR' ? 'dibanned permanen' : 'dinonaktifkan') . " oleh admin.\nAlasan: {$reason}",
                    '/seller/dashboard', 'DANGER',
                    ['vendor_id' => $vendor->id, 'reason' => $reason, 'action' => $action]
                );
            }
        }
    }
}
