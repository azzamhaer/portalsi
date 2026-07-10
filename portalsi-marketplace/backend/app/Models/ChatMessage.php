<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $fillable = ['thread_id', 'sender_user_id', 'sender_type', 'message', 'image_url', 'is_read'];
    protected $casts = ['is_read' => 'boolean'];

    public function thread(): BelongsTo { return $this->belongsTo(ChatThread::class, 'thread_id'); }
    public function sender(): BelongsTo { return $this->belongsTo(User::class, 'sender_user_id'); }
}
