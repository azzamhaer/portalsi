<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id', 'category_id', 'name', 'slug', 'description',
        'price', 'original_price', 'stock', 'sold', 'rating', 'weight',
        'image', 'images', 'variants', 'is_active', 'is_flash_sale'
    ];

    protected $casts = [
        'is_active'     => 'boolean',
        'is_flash_sale' => 'boolean',
        'rating'        => 'float',
        'price'         => 'integer',
        'original_price'=> 'integer',
        'weight'        => 'integer',
        'images'        => 'array',
        'variants'      => 'array',
    ];

    protected $appends = ['tags'];

    public function vendor(): BelongsTo    { return $this->belongsTo(Vendor::class); }
    public function category(): BelongsTo  { return $this->belongsTo(Category::class); }
    public function reviews(): HasMany     { return $this->hasMany(Review::class); }
    public function orderItems(): HasMany  { return $this->hasMany(OrderItem::class); }
    public function wishlist(): HasMany    { return $this->hasMany(Wishlist::class); }
    public function tagModels(): BelongsToMany { return $this->belongsToMany(Tag::class, 'product_tag'); }

    public function getTagsAttribute(): array {
        if (!$this->relationLoaded('tagModels')) $this->load('tagModels:id,slug');
        return $this->tagModels->pluck('slug')->toArray();
    }

    public function getDiscountPercentAttribute(): int {
        if (!$this->original_price || $this->original_price <= $this->price) return 0;
        return (int) round((($this->original_price - $this->price) / $this->original_price) * 100);
    }
}
