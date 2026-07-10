<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatThread extends Model
{
    protected $fillable = ['user_id', 'vendor_id', 'product_id', 'last_message_at'];
    protected $casts = ['last_message_at' => 'datetime'];

    public function user(): BelongsTo     { return $this->belongsTo(User::class); }
    public function vendor(): BelongsTo   { return $this->belongsTo(Vendor::class); }
    public function product(): BelongsTo  { return $this->belongsTo(Product::class); }
    public function messages(): HasMany   { return $this->hasMany(ChatMessage::class, 'thread_id'); }
}
