<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Address extends Model
{
    protected $fillable = [
        'user_id', 'recipient', 'phone', 'country', 'province', 'city', 'district', 'village',
        'province_id', 'city_id', 'district_id', 'village_id', 'rajaongkir_destination_id',
        'latitude', 'longitude', 'full_address', 'postal_code', 'address_note', 'is_default',
    ];
    protected $casts = [
        'is_default' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'rajaongkir_destination_id' => 'integer',
    ];

    public function user(): BelongsTo  { return $this->belongsTo(User::class); }
    public function orders(): HasMany  { return $this->hasMany(Order::class); }
}
