<?php

namespace App\Marketplace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorFollower extends Model
{
    protected $connection = 'marketplace';

    protected $fillable = ['vendor_id', 'user_id'];

    public function vendor(): BelongsTo { return $this->belongsTo(Vendor::class); }
    public function user(): BelongsTo   { return $this->belongsTo(User::class); }
}
