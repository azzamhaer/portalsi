<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'order_number', 'user_id', 'address_id', 'address_snapshot',
        'subtotal', 'shipping', 'insurance', 'payment_fee', 'total',
        'shipping_cashback', 'shipping_service_fee',
        'courier_name', 'courier_code', 'courier_service', 'courier_eta', 'shipping_type',
        'status', 'tracking_no', 'rajaongkir_order_no', 'shipping_payload', 'notes',
        'paid_at', 'shipped_at', 'done_at'
    ];

    protected $casts = [
        'paid_at'    => 'datetime',
        'shipped_at' => 'datetime',
        'done_at'    => 'datetime',
        'subtotal'   => 'integer',
        'shipping'   => 'integer',
        'shipping_cashback' => 'integer',
        'shipping_service_fee' => 'integer',
        'insurance'  => 'integer',
        'payment_fee'=> 'integer',
        'total'      => 'integer',
        'address_snapshot' => 'array',
        'shipping_payload' => 'array',
    ];

    public function user(): BelongsTo     { return $this->belongsTo(User::class); }
    public function address(): BelongsTo  { return $this->belongsTo(Address::class); }
    public function items(): HasMany      { return $this->hasMany(OrderItem::class); }
    public function payment(): HasOne     { return $this->hasOne(Payment::class); }
    public function returns(): HasMany    { return $this->hasMany(OrderReturn::class); }
}
