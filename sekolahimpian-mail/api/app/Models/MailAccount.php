<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailAccount extends Model
{
    protected $fillable = ['user_id', 'local_part', 'email', 'password', 'quota', 'suspended_at'];

    protected $casts = [
        'password' => 'encrypted',
        'suspended_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
