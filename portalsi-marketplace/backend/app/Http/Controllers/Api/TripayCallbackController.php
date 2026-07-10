<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderPaymentService;
use App\Services\TripayService;
use Illuminate\Http\Request;

class TripayCallbackController extends Controller
{
    public function __construct(private TripayService $tripay, private OrderPaymentService $orderPayments) {}

    public function __invoke(Request $request)
    {
        $raw = $request->getContent();
        $sig = $request->header('X-Callback-Signature', '');
        if (!$this->tripay->isMockMode()) {
            if (!$this->tripay->verifyCallbackSignature($raw, $sig)) {
                return response()->json(['success' => false, 'message' => 'Invalid signature'], 400);
            }
        }
        $data = json_decode($raw, true);
        if (!$data) return response()->json(['success' => false, 'message' => 'Invalid JSON'], 400);

        $orderNumber = $data['merchant_ref'] ?? null;
        $order = Order::with('payment')->where('order_number', $orderNumber)->first();
        if (!$order) return response()->json(['success' => false, 'message' => 'Order not found'], 404);

        $status = strtoupper($data['status'] ?? '');
        if ($status === 'PAID') {
            $this->orderPayments->markPaid($order, $data);
        } elseif (in_array($status, ['EXPIRED', 'FAILED'])) {
            $order->payment?->update(['status' => $status]);
            $order->update(['status' => $status === 'EXPIRED' ? 'EXPIRED' : 'CANCELLED']);
        } elseif ($status === 'REFUND') {
            $order->payment?->update(['status' => 'REFUND']);
            $order->update(['status' => 'CANCELLED']);
        }
        return response()->json(['success' => true]);
    }
}
