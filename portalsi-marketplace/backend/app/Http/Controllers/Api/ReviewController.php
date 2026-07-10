<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    /** Eligibility check — user pernah beli produk ini & order DONE */
    public function eligibility(Request $request, $productId)
    {
        $user = $request->user();
        $bought = OrderItem::where('product_id', $productId)
            ->whereHas('order', fn($q) => $q->where('user_id', $user->id)->where('status', 'DONE'))
            ->exists();
        $already = Review::where('product_id', $productId)->where('user_id', $user->id)->exists();
        return response()->json([
            'can_review' => $bought && !$already,
            'has_purchased' => $bought,
            'already_reviewed' => $already,
        ]);
    }

    public function store(Request $request, $productId)
    {
        $user = $request->user();
        $product = Product::findOrFail($productId);

        // Pastikan user sudah membeli produk ini (order DONE)
        $bought = OrderItem::where('product_id', $product->id)
            ->whereHas('order', fn($q) => $q->where('user_id', $user->id)->where('status', 'DONE'))
            ->exists();
        if (!$bought) {
            return response()->json(['message' => 'Anda hanya bisa memberi ulasan untuk produk yang sudah Anda beli & selesai.'], 422);
        }

        // Hanya 1 ulasan per user per produk
        if (Review::where('product_id', $product->id)->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Anda sudah memberi ulasan untuk produk ini.'], 422);
        }

        $data = $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'required|string|min:5|max:1000',
        ]);

        $review = Review::create([
            'product_id' => $product->id,
            'user_id'    => $user->id,
            'rating'     => $data['rating'],
            'comment'    => $data['comment'],
        ]);

        // Recalc rating produk + vendor
        $this->recalcRatings($product);

        return response()->json($review->load('user:id,name'), 201);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $review = Review::where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $product = $review->product;
        $review->delete();
        $this->recalcRatings($product);
        return response()->json(['ok' => true]);
    }

    private function recalcRatings(Product $product): void
    {
        $avg = (float) Review::where('product_id', $product->id)->avg('rating');
        $product->update(['rating' => round($avg, 2)]);

        // Vendor.rating = AVG of all its products' ratings (yang ada review-nya)
        if ($vendor = $product->vendor) {
            $vAvg = (float) Product::where('vendor_id', $vendor->id)
                ->whereHas('reviews')
                ->avg('rating');
            $vendor->update(['rating' => round($vAvg ?: 5.0, 2)]);
        }
    }
}
