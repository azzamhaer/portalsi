<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\UserNotification;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;

class OrderPaymentService
{
    public function __construct(private ProductStockNotificationService $stockNotifications) {}

    public function markPaid(Order $order, ?array $rawResponse = null): bool
    {
        return DB::transaction(function () use ($order, $rawResponse) {
            $lockedOrder = Order::with(['items', 'payment'])
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $shouldAdjustStock = $lockedOrder->status === 'PENDING_PAYMENT';
            $paidAt = $lockedOrder->paid_at ?: now();

            if ($shouldAdjustStock) {
                foreach ($lockedOrder->items as $item) {
                    $qty = max(0, (int) $item->quantity);
                    if ($qty < 1) continue;

                    $product = Product::whereKey($item->product_id)->lockForUpdate()->first();
                    if (!$product) continue;

                    $product->stock = max(0, (int) $product->stock - $qty);
                    $product->sold = (int) $product->sold + $qty;
                    $product->save();

                    if ((int) $product->stock === 0) {
                        $this->stockNotifications->notifySellerOutOfStock($product);
                    }
                }

                $this->notifySellersPaidOrder($lockedOrder);
            }

            if ($lockedOrder->payment) {
                $paymentPayload = [
                    'status' => 'PAID',
                    'paid_at' => $paidAt,
                ];
                if ($rawResponse !== null) {
                    $paymentPayload['raw_response'] = json_encode($rawResponse);
                }
                $lockedOrder->payment->update($paymentPayload);
            }

            $lockedOrder->update([
                'status' => 'PROCESSING',
                'paid_at' => $paidAt,
            ]);

            return $shouldAdjustStock;
        });
    }

    private function notifySellersPaidOrder(Order $order): void
    {
        $itemsByVendor = $order->items->groupBy('vendor_id');
        $vendors = Vendor::whereIn('id', $itemsByVendor->keys())->get()->keyBy('id');

        foreach ($itemsByVendor as $vendorId => $items) {
            $vendor = $vendors->get((int) $vendorId);
            if (!$vendor?->user_id) continue;

            $count = $items->sum('quantity');
            $total = $items->sum(fn($item) => (int) $item->price * (int) $item->quantity);
            $productNames = $items->pluck('product_name')->filter()->take(2)->implode(', ');

            UserNotification::send(
                $vendor->user_id,
                'SELLER_ORDER_PAID',
                'Pesanan baru sudah dibayar',
                "Pesanan #{$order->order_number} sudah dibayar. {$count} item perlu diproses" . ($productNames ? ": {$productNames}" : '') . ".",
                '/seller/orders',
                'SUCCESS',
                [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'vendor_id' => $vendor->id,
                    'item_count' => $count,
                    'seller_total' => $total,
                ]
            );
        }
    }
}
