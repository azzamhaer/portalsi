<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    protected $fillable = ['slug', 'name', 'product_count'];

    public function products(): BelongsToMany {
        return $this->belongsToMany(Product::class, 'product_tag');
    }

    public static function findOrCreateBySlug(string $slug): self {
        $slug = Str::slug(strtolower($slug));
        return self::firstOrCreate(['slug' => $slug], ['name' => $slug]);
    }
}
