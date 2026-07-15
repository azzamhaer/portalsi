<?php

namespace App\Marketplace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserWithdrawal extends Model
{
    protected $connection = 'marketplace';

    protected $fillable = [
        'user_id', 'amount', 'bank_name', 'bank_account', 'bank_holder',
        'status', 'admin_note', 'processed_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'processed_at' => 'datetime',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
