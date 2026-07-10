<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Report;
use App\Models\UserNotification;
use App\Models\Vendor;
use App\Models\Withdrawal;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $q = UserNotification::where('user_id', $request->user()->id)->orderByDesc('id');
        if ($request->boolean('unread')) $q->whereNull('read_at');
        return response()->json($q->paginate(30));
    }

    public function unreadCount(Request $request)
    {
        $n = UserNotification::where('user_id', $request->user()->id)->whereNull('read_at')->count();
        return response()->json(['count' => $n]);
    }

    public function show(Request $request, $id)
    {
        $n = UserNotification::where('user_id', $request->user()->id)->findOrFail($id);
        if (!$n->read_at) $n->update(['read_at' => now()]);

        return response()->json($this->detailResource($n->fresh(), $request->user()));
    }

    public function markRead(Request $request, $id)
    {
        $n = UserNotification::where('user_id', $request->user()->id)->findOrFail($id);
        if (!$n->read_at) $n->update(['read_at' => now()]);
        return response()->json(['ok' => true]);
    }

    public function markAllRead(Request $request)
    {
        UserNotification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);
        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, $id)
    {
        UserNotification::where('user_id', $request->user()->id)->findOrFail($id)->delete();
        return response()->json(['ok' => true]);
    }

    private function detailResource(UserNotification $n, $user): array
    {
        $payload = $n->payload ?: [];
        $context = [];
        $facts = [];
        $actions = [];
        $primaryUrl = $this->safeInternalUrl($n->action_url);

        if (str_starts_with($n->type, 'ORDER_') && !empty($payload['order_id'])) {
            $order = Order::where('user_id', $user->id)->find($payload['order_id']);
            if ($order) {
                $context['order'] = [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'total' => $order->total,
                    'created_at' => $order->created_at,
                ];
                $facts[] = ['label' => 'Nomor pesanan', 'value' => $order->order_number];
                $facts[] = ['label' => 'Status pesanan', 'value' => $order->status];
                $actions[] = ['label' => 'Buka pesanan', 'url' => '/orders/' . $order->id, 'icon' => 'receipt'];
            }
        }

        if ($n->type === 'REPORT_RESPONSE' && !empty($payload['report_id'])) {
            $report = Report::with('resolver:id,name')->where('reporter_user_id', $user->id)->find($payload['report_id']);
            if ($report) {
                $target = $this->reportTarget($report);
                $context['report'] = [
                    'id' => $report->id,
                    'status' => $report->status,
                    'category' => $report->category,
                    'category_label' => Report::CATEGORIES[$report->category] ?? $report->category,
                    'description' => $report->description,
                    'admin_response' => $report->admin_response,
                    'target_type' => $report->target_type,
                    'target_id' => $report->target_id,
                    'target' => $target,
                    'resolved_at' => $report->resolved_at,
                    'resolver' => $report->resolver,
                ];
                $facts[] = ['label' => 'Status laporan', 'value' => $report->status];
                $facts[] = ['label' => 'Kategori', 'value' => Report::CATEGORIES[$report->category] ?? $report->category];
                if ($report->admin_response) $facts[] = ['label' => 'Respon admin', 'value' => $report->admin_response];
                if ($target && !empty($target['url'])) {
                    $actions[] = ['label' => 'Lihat objek laporan', 'url' => $target['url'], 'icon' => $report->target_type === 'PRODUCT' ? 'package' : 'store'];
                }
            }
        }

        if ($n->type === 'VENDOR_PENDING_APPROVAL' && !empty($payload['vendor_id'])) {
            $vendor = Vendor::with('user:id,name,email')->find($payload['vendor_id']);
            if ($vendor) {
                $context['vendor'] = $this->vendorSummary($vendor);
                $facts[] = ['label' => 'Nama toko', 'value' => $vendor->name];
                $facts[] = ['label' => 'Status verifikasi', 'value' => $vendor->verification_status];
                $actions[] = ['label' => 'Buka verifikasi vendor', 'url' => '/admin/vendors?status=PENDING', 'icon' => 'shield-check'];
            }
        }

        if ($n->type === 'VENDOR_VERIFICATION') {
            $vendor = $user->vendor ?: Vendor::where('user_id', $user->id)->first();
            if ($vendor) {
                $context['vendor'] = $this->vendorSummary($vendor);
                $facts[] = ['label' => 'Nama toko', 'value' => $vendor->name];
                $facts[] = ['label' => 'Status verifikasi', 'value' => $vendor->verification_status];
                if ($vendor->verification_note) $facts[] = ['label' => 'Catatan admin', 'value' => $vendor->verification_note];
                $actions[] = [
                    'label' => $vendor->verification_status === 'APPROVED' ? 'Buka Seller Center' : 'Lihat status toko',
                    'url' => $vendor->verification_status === 'APPROVED' ? '/seller/dashboard' : '/seller/pending',
                    'icon' => $vendor->verification_status === 'APPROVED' ? 'layout-dashboard' : 'clock',
                ];
            }
        }

        if (str_starts_with($n->type, 'WITHDRAW_') && !empty($payload['withdrawal_id'])) {
            $withdrawal = Withdrawal::whereHas('vendor', fn($q) => $q->where('user_id', $user->id))->find($payload['withdrawal_id']);
            if ($withdrawal) {
                $context['withdrawal'] = [
                    'id' => $withdrawal->id,
                    'amount' => $withdrawal->amount,
                    'status' => $withdrawal->status,
                    'admin_note' => $withdrawal->admin_note,
                    'processed_at' => $withdrawal->processed_at,
                ];
                $facts[] = ['label' => 'Nominal', 'value' => 'Rp ' . number_format((int) $withdrawal->amount, 0, ',', '.')];
                $facts[] = ['label' => 'Status penarikan', 'value' => $withdrawal->status];
                if ($withdrawal->admin_note) $facts[] = ['label' => 'Catatan admin', 'value' => $withdrawal->admin_note];
                $actions[] = ['label' => 'Buka penarikan', 'url' => '/seller/withdraw', 'icon' => 'wallet'];
            }
        }

        if ($n->type === 'PRODUCT_ACTION') {
            if (!empty($payload['product_id'])) {
                $facts[] = ['label' => 'ID produk', 'value' => '#' . $payload['product_id']];
            }
            if (!empty($payload['reason'])) $facts[] = ['label' => 'Alasan', 'value' => $payload['reason']];
            $actions[] = ['label' => 'Buka produk seller', 'url' => '/seller/products', 'icon' => 'package'];
        }

        if ($n->type === 'VENDOR_ACTION') {
            if (!empty($payload['action'])) $facts[] = ['label' => 'Tindakan', 'value' => $payload['action']];
            if (!empty($payload['reason'])) $facts[] = ['label' => 'Alasan', 'value' => $payload['reason']];
            $actions[] = ['label' => 'Buka Seller Center', 'url' => '/seller/dashboard', 'icon' => 'store'];
        }

        if (in_array($n->type, ['PASSWORD_CHANGED', 'EMAIL_CHANGED'], true)) {
            $actions[] = ['label' => 'Buka profil', 'url' => '/profile', 'icon' => 'user'];
        }

        $skipPrimaryUrl = $primaryUrl === '/notifications'
            || $primaryUrl === '/notifications/' . $n->id
            || ($n->type === 'REPORT_RESPONSE' && $primaryUrl === '/profile');
        if ($primaryUrl && !$skipPrimaryUrl && !collect($actions)->contains(fn($a) => $a['url'] === $primaryUrl)) {
            $actions[] = ['label' => $this->actionLabel($n->type), 'url' => $primaryUrl, 'icon' => 'external-link'];
        }

        return [
            'id' => $n->id,
            'type' => $n->type,
            'title' => $n->title,
            'message' => $n->message,
            'severity' => $n->severity,
            'read_at' => $n->read_at,
            'created_at' => $n->created_at,
            'updated_at' => $n->updated_at,
            'action_url' => $primaryUrl,
            'payload' => $payload,
            'facts' => $facts,
            'actions' => $actions,
            'context' => $context,
        ];
    }

    private function reportTarget(Report $report): ?array
    {
        if ($report->target_type === 'PRODUCT') {
            $product = Product::with('vendor:id,name,username')->find($report->target_id);
            if (!$product) return ['type' => 'PRODUCT', 'id' => $report->target_id, 'name' => 'Produk sudah tidak tersedia', 'url' => null];
            return [
                'type' => 'PRODUCT',
                'id' => $product->id,
                'name' => $product->name,
                'url' => '/product/' . ($product->slug ?: $product->id),
                'vendor' => $product->vendor?->only(['id', 'name', 'username']),
            ];
        }

        $vendor = Vendor::find($report->target_id);
        if (!$vendor) return ['type' => 'VENDOR', 'id' => $report->target_id, 'name' => 'Toko sudah tidak tersedia', 'url' => null];
        return [
            'type' => 'VENDOR',
            'id' => $vendor->id,
            'name' => $vendor->name,
            'url' => $vendor->username ? '/' . $vendor->username : '/vendors/' . $vendor->id,
        ];
    }

    private function vendorSummary(Vendor $vendor): array
    {
        return [
            'id' => $vendor->id,
            'name' => $vendor->name,
            'username' => $vendor->username,
            'verification_status' => $vendor->verification_status,
            'verification_note' => $vendor->verification_note,
            'moderation_mode' => $vendor->moderation_mode,
            'admin_warning' => $vendor->admin_warning,
        ];
    }

    private function safeInternalUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '' || str_starts_with($url, '//')) return null;
        if (preg_match('#^https?://#i', $url)) return null;
        return str_starts_with($url, '/') ? $url : '/' . $url;
    }

    private function actionLabel(string $type): string
    {
        return match (true) {
            str_starts_with($type, 'ORDER_') => 'Buka pesanan',
            str_starts_with($type, 'WITHDRAW_') => 'Buka penarikan',
            $type === 'VENDOR_PENDING_APPROVAL' => 'Buka verifikasi vendor',
            $type === 'VENDOR_VERIFICATION' => 'Buka status toko',
            default => 'Buka halaman terkait',
        };
    }
}
