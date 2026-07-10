<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingOption extends Model
{
    protected $fillable = ['name', 'eta', 'cost', 'is_active', 'sort_order'];
    protected $casts = ['is_active' => 'boolean', 'cost' => 'integer'];
}
