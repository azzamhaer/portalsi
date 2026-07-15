<?php

namespace App\Marketplace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserWalletTransaction extends Model
{
    protected $connection = 'marketplace';

    protected $fillable = ['user_id', 'amount', 'type', 'reference', 'note'];

    protected $casts = ['amount' => 'integer'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
