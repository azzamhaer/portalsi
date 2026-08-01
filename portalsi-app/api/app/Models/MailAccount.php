<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailAccount extends Model
{
    protected $fillable = [
        'user_id',
        'local_part',
        'email',
        'password',
        'quota',
        'suspended_at',
    ];

    protected $casts = [
        // Password mailbox disimpan terenkripsi (APP_KEY); otomatis dekripsi saat dibaca.
        'password' => 'encrypted',
        'suspended_at' => 'datetime',
    ];

    protected $hidden = [
        'password',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
