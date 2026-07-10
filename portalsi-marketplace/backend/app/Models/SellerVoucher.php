<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SellerVoucher extends Model
{
    protected $fillable = [
        'vendor_id', 'code', 'type', 'value', 'min_subtotal', 'max_discount',
        'usage_limit', 'used_count', 'is_active', 'starts_at', 'ends_at'
    ];

    protected $casts = [
        'value' => 'integer',
        'min_subtotal' => 'integer',
        'max_discount' => 'integer',
        'usage_limit' => 'integer',
        'used_count' => 'integer',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function vendor(): BelongsTo { return $this->belongsTo(Vendor::class); }
    public function products(): BelongsToMany { return $this->belongsToMany(Product::class, 'seller_voucher_product'); }

    public function isUsableFor(Product $product, int $subtotal): bool
    {
        if (!$this->is_active) return false;
        if ($this->starts_at && now()->lt($this->starts_at)) return false;
        if ($this->ends_at && now()->gt($this->ends_at)) return false;
        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) return false;
        if ($subtotal < $this->min_subtotal) return false;
        if ($this->products()->exists() && !$this->products()->where('products.id', $product->id)->exists()) return false;
        return true;
    }

    public function discountFor(int $subtotal): int
    {
        $discount = $this->type === 'PERCENT'
            ? (int) floor($subtotal * min(100, $this->value) / 100)
            : min($subtotal, $this->value);
        if ($this->max_discount) $discount = min($discount, $this->max_discount);
        return max(0, min($subtotal, $discount));
    }
}
