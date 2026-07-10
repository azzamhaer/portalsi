<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdrawal extends Model
{
    protected $fillable = [
        'vendor_id', 'amount', 'bank_name', 'bank_account', 'bank_holder',
        'status', 'admin_note', 'processed_at'
    ];

    protected $casts = [
        'amount' => 'integer',
        'processed_at' => 'datetime',
    ];

    public function vendor(): BelongsTo { return $this->belongsTo(Vendor::class); }

    public function getWithdrawerTypeAttribute(): string { return 'SELLER'; }
}
