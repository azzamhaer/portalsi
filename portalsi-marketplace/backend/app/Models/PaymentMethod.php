<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = ['code', 'name', 'group', 'icon', 'color', 'fee_pct', 'fee_flat', 'sort_order', 'is_active'];
    protected $casts = [
        'is_active' => 'boolean',
        'fee_pct'   => 'float',
        'fee_flat'  => 'integer',
    ];
}
