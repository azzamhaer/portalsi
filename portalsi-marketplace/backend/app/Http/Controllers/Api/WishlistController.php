<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            $request->user()->wishlist()->with('product.vendor:id,name')->get()->map(fn($w) => [
                'id' => $w->id,
                'product_id' => $w->product_id,
                'product' => $w->product,
            ])
        );
    }

    public function toggle(Request $request)
    {
        $data = $request->validate(['product_id' => 'required|exists:products,id']);
        $w = Wishlist::where('user_id', $request->user()->id)->where('product_id', $data['product_id'])->first();
        if ($w) { $w->delete(); return response()->json(['inWishlist' => false]); }
        Wishlist::create(['user_id' => $request->user()->id, 'product_id' => $data['product_id']]);
        return response()->json(['inWishlist' => true]);
    }
}
