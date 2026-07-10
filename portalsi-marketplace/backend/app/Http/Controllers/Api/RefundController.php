<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderReturn;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    /**
     * Riwayat refund/pengembalian dana untuk user yang login.
     * Menggabungkan 2 sumber:
     *  1. OrderReturn — pengajuan return barang oleh buyer (APPROVED/REJECTED/REFUNDED)
     *  2. Order CANCELLED / EXPIRED yang sudah dibayar (payment paid → status CANCELLED)
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $items = collect();

        // --- 1. Returns ---
        $returns = OrderReturn::with(['order:id,order_number,total,status,created_at,courier_name', 'order.items:id,order_id,product_name,quantity,price'])
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->get();
        foreach ($returns as $r) {
            $items->push([
                'id'           => 'return-' . $r->id,
                'type'         => 'RETURN',
                'type_label'   => 'Return barang',
                'order_number' => $r->order?->order_number,
                'order_id'     => $r->order_id,
                'amount'       => (int) ($r->order?->total ?? 0),
                'status'       => $r->status,                   // PENDING|APPROVED|REJECTED|REFUNDED
                'status_label' => $this->returnStatusLabel($r->status),
                'reason'       => $r->reason,
                'admin_note'   => $r->admin_note,
                'created_at'   => $r->created_at,
                'updated_at'   => $r->updated_at,
                'items'        => $r->order?->items?->map(fn($it) => [
                    'name'     => $it->product_name,
                    'quantity' => $it->quantity,
                    'subtotal' => (int) ($it->price * $it->quantity),
                ]) ?? [],
            ]);
        }

        // --- 2. Order Cancelled / Expired yang sudah dibayar ---
        $cancelledOrders = Order::with(['payment:id,order_id,status,method_name,paid_at', 'items:id,order_id,product_name,quantity,price'])
            ->where('user_id', $userId)
            ->whereIn('status', ['CANCELLED', 'EXPIRED'])
            ->whereHas('payment', fn($q) => $q->whereNotNull('paid_at'))
            ->orderByDesc('id')->get();
        foreach ($cancelledOrders as $o) {
            $items->push([
                'id'           => 'cancel-' . $o->id,
                'type'         => 'CANCELLATION',
                'type_label'   => 'Pesanan dibatalkan',
                'order_number' => $o->order_number,
                'order_id'     => $o->id,
                'amount'       => (int) $o->total,
                'status'       => $o->status === 'EXPIRED' ? 'REFUNDED' : 'REFUNDED', // dibayar → otomatis dikembalikan
                'status_label' => $o->status === 'EXPIRED' ? 'Kadaluarsa' : 'Dibatalkan',
                'reason'       => $o->status === 'EXPIRED' ? 'Pembayaran tidak diteruskan' : 'Pesanan dibatalkan',
                'admin_note'   => null,
                'payment_method' => $o->payment?->method_name,
                'created_at'   => $o->created_at,
                'updated_at'   => $o->updated_at,
                'items'        => $o->items->map(fn($it) => [
                    'name'     => $it->product_name,
                    'quantity' => $it->quantity,
                    'subtotal' => (int) ($it->price * $it->quantity),
                ]),
            ]);
        }

        // Urutkan gabungan berdasarkan created_at desc
        $sorted = $items->sortByDesc('created_at')->values();

        // Hitung ringkasan
        $totalRefunded = $sorted->where('status', 'REFUNDED')->sum('amount');
        $totalPending  = $sorted->whereIn('status', ['PENDING', 'APPROVED'])->sum('amount');

        return response()->json([
            'data'    => $sorted,
            'summary' => [
                'count'          => $sorted->count(),
                'total_refunded' => $totalRefunded,
                'total_pending'  => $totalPending,
            ],
        ]);
    }

    private function returnStatusLabel(string $status): string
    {
        return match ($status) {
            'PENDING'  => 'Menunggu review admin',
            'APPROVED' => 'Disetujui, menunggu refund',
            'REJECTED' => 'Ditolak',
            'REFUNDED' => 'Dana sudah dikembalikan',
            default    => $status,
        };
    }
}
