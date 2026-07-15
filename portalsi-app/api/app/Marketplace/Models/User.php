<?php

namespace App\Marketplace\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    protected $connection = 'marketplace';

    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'portal_user_id',
        'portal_username',
        'portal_role',
        'portal_access_token',
        'name',
        'email',
        'email_verified_at',
        'password',
        'phone',
        'role',
        'image',
    ];

    protected $hidden = ['password', 'remember_token', 'portal_access_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'portal_access_token' => 'encrypted',
        ];
    }

    public function vendor(): HasOne     { return $this->hasOne(Vendor::class); }
    public function addresses(): HasMany { return $this->hasMany(Address::class); }
    public function orders(): HasMany    { return $this->hasMany(Order::class); }
    public function wishlist(): HasMany  { return $this->hasMany(Wishlist::class); }
    public function reviews(): HasMany   { return $this->hasMany(Review::class); }
    public function walletTransactions(): HasMany { return $this->hasMany(UserWalletTransaction::class); }
    public function withdrawals(): HasMany { return $this->hasMany(UserWithdrawal::class); }

    public function isSeller(): bool { return $this->role === 'SELLER' || $this->vendor()->exists(); }
    public function isAdmin(): bool  { return $this->role === 'ADMIN'; }
}
