<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id', 'method', 'method_name', 'reference', 'pay_code', 'pay_url',
        'qr_string', 'amount', 'fee', 'total', 'status', 'expired_at', 'paid_at', 'raw_response'
    ];

    protected $casts = [
        'expired_at' => 'datetime',
        'paid_at'    => 'datetime',
        'amount'     => 'integer',
        'fee'        => 'integer',
        'total'      => 'integer',
    ];

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
}
