<?php

namespace App\Services;

use App\Models\Product;
use App\Models\UserNotification;
use App\Models\Wishlist;

class ProductStockNotificationService
{
    public function notifySellerOutOfStock(Product $product): void
    {
        $product->loadMissing('vendor:id,user_id,name');
        $sellerId = $product->vendor?->user_id;
        if (!$sellerId) return;

        $actionUrl = '/seller/products?product=' . $product->id;
        $exists = UserNotification::where('user_id', $sellerId)
            ->where('type', 'PRODUCT_OUT_OF_STOCK')
            ->where('action_url', $actionUrl)
            ->whereNull('read_at')
            ->exists();

        if ($exists) return;

        UserNotification::send(
            $sellerId,
            'PRODUCT_OUT_OF_STOCK',
            'Stok produk habis',
            "Stok \"{$product->name}\" sudah habis. Segera perbarui stok agar pembeli bisa checkout lagi.",
            $actionUrl,
            'WARNING',
            ['product_id' => $product->id, 'vendor_id' => $product->vendor_id]
        );
    }

    public function notifyWishlistRestocked(Product $product): void
    {
        $wishlists = Wishlist::where('product_id', $product->id)->pluck('user_id')->unique();
        foreach ($wishlists as $userId) {
            UserNotification::send(
                (int) $userId,
                'WISHLIST_PRODUCT_RESTOCKED',
                'Produk wishlist tersedia lagi',
                "\"{$product->name}\" yang ada di wishlist Anda sudah tersedia kembali.",
                '/product/' . ($product->slug ?: $product->id),
                'SUCCESS',
                ['product_id' => $product->id]
            );
        }
    }
}
