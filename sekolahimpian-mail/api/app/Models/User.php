<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = ['full_name', 'username', 'email', 'password', 'profile_picture_url', 'pending_email', 'email_change_token'];

    protected $hidden = ['password', 'remember_token', 'email_change_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /** Bentuk user untuk API (dipakai webmail). */
    public function toApi(): array
    {
        return [
            'user_id' => $this->id,
            'username' => $this->username,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'is_verified' => (bool) $this->email_verified_at,
            'profile_picture_url' => $this->profile_picture_url,
        ];
    }
}
