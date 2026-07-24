<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PolicyAcceptance extends Model
{
    protected $fillable = [
        'policy_id',
        'user_id',
        'policy_version',
        'accepted_at',
        'ip_address',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'policy_version' => 'integer',
    ];

    public function policy()
    {
        return $this->belongsTo(Policy::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
