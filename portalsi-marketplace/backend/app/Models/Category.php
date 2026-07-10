<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'parent_id', 'name', 'slug', 'tag_slug', 'emoji', 'color', 'icon',
        'is_active', 'featured_home', 'sort_order'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'featured_home' => 'boolean',
    ];

    public function products(): HasMany { return $this->hasMany(Product::class); }
    public function parent(): BelongsTo { return $this->belongsTo(Category::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order')->orderBy('name'); }
}
