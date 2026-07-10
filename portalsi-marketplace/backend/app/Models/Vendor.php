<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'slug', 'username', 'username_changed_at',
        'city', 'country', 'province', 'district', 'village', 'postal_code',
        'province_id', 'city_id', 'district_id', 'village_id', 'rajaongkir_destination_id',
        'latitude', 'longitude', 'full_address', 'address_note',
        'description', 'avatar', 'banner', 'ktp_image', 'verification_status', 'verification_note',
        'bank_name', 'bank_account', 'bank_holder',
        'rating', 'total_sold', 'followers', 'is_official', 'badge',
        'moderation_mode', 'admin_warning', 'warning_dismissed_at',
        'tour_completed_at', 'is_banned', 'ban_reason'
    ];

    protected $casts = [
        'is_official'         => 'boolean',
        'rating'              => 'float',
        'latitude'            => 'float',
        'longitude'           => 'float',
        'rajaongkir_destination_id' => 'integer',
        'username_changed_at' => 'datetime',
        'tour_completed_at'   => 'datetime',
        'is_banned'           => 'boolean',
    ];

    protected $hidden = ['ktp_image']; // jangan expose KTP di public response

    public function user(): BelongsTo               { return $this->belongsTo(User::class); }
    public function products(): HasMany             { return $this->hasMany(Product::class); }
    public function orderItems(): HasMany           { return $this->hasMany(OrderItem::class); }
    public function chatThreads(): HasMany          { return $this->hasMany(ChatThread::class); }
    public function withdrawals(): HasMany          { return $this->hasMany(Withdrawal::class); }
    public function followerRecords(): HasMany      { return $this->hasMany(VendorFollower::class); }

    public function followerUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'vendor_followers')->withTimestamps();
    }
}
