<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

// ⬇️ Tambahan untuk verifikasi email
use Illuminate\Contracts\Auth\MustVerifyEmail;

// ⬇️ Relasi
use App\Models\Post;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Story;
use App\Models\DirectMessage;
use App\Models\Notification;
use App\Notifications\CustomVerifyEmail;
use App\Notifications\CustomResetPassword;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    // 🟢 PRIMARY KEY custom: user_id
    protected $primaryKey = 'user_id';

    /**
     * Mass assignment
     */
    protected $fillable = [
        'username',
        'full_name',
        'email',
        'password_hash',
        'bio',
        'profile_picture_url',
        'banner_url',
        'is_verified',       // centang biru
        'is_banned',
        'banned_at',
        'banned_by',
        'ban_reason',
        'admin_notes',
        'is_private',
        'role',              // dev, teacher, parent, student
        'email_verified_at', // verifikasi email
        'is_online',         // online status
        'last_seen',         // last seen timestamp
        'last_activity',     // last activity timestamp
        'notification_preferences', // preferensi notifikasi in-app
    ];

    /**
     * Hidden dari JSON response
     */
    protected $hidden = [
        'password_hash',
        'remember_token',
        'admin_notes',
        'ban_reason',
        'banned_at',
        'banned_by',
    ];

    /**
     * Casting otomatis
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_verified' => 'boolean',
        'is_banned' => 'boolean',
        'banned_at' => 'datetime',
        'is_private' => 'boolean',
        'is_online' => 'boolean',
        'last_seen' => 'datetime',
        'last_activity' => 'datetime',
        'notification_preferences' => 'array',
    ];

    /**
     * Selalu sertakan `is_followed_by` di setiap serialisasi user.
     *
     * Dipakai agar tombol follow di SELURUH aplikasi (reels, daftar pengikut, detail post,
     * saran, pencarian, dsb.) bisa berubah menjadi "Ikuti balik" saat orang tersebut sudah
     * lebih dulu mengikuti kita. Ditaruh di model, bukan di tiap controller, supaya tidak
     * ada satu pun tempat yang kelewatan.
     */
    protected $appends = ['is_followed_by', 'has_story', 'story_viewed', 'profile_picture_thumb_url'];

    /**
     * Cache daftar pengikut viewer selama satu request.
     *
     * Tanpa ini, setiap user yang diserialisasi memicu satu query sendiri (N+1) — berat
     * sekali di halaman berisi puluhan user. Dengan cache: satu query untuk seluruh request.
     * `$viewerFollowerCacheFor` menyimpan ID pemilik cache agar tidak salah pakai bila
     * proses hidup melayani lebih dari satu pengguna (mis. queue worker).
     */
    private static ?array $viewerFollowerIds = null;
    private static ?int $viewerFollowerCacheFor = null;

    public static function forgetViewerFollowerCache(): void
    {
        self::$viewerFollowerIds = null;
        self::$viewerFollowerCacheFor = null;
    }

    /**
     * ID pengguna yang sedang melihat.
     *
     * TIDAK cukup memakai `Auth::id()` saja: sebagian rute (mis. `GET /profile/{username}`)
     * berada DI LUAR middleware `auth:sanctum` karena boleh diakses tanpa login, sehingga
     * guard default tidak pernah terisi walaupun request membawa token. Di rute seperti itu
     * `Auth::id()` mengembalikan null dan status "mengikuti kita" selalu terbaca false.
     * Karena itu guard sanctum diperiksa sebagai cadangan.
     */
    private static function viewerId(): ?int
    {
        $id = \Illuminate\Support\Facades\Auth::id();
        if ($id) {
            return (int) $id;
        }
        // Di CLI (cron, queue worker) tidak ada request → jangan sentuh guard sanctum.
        if (app()->runningInConsole()) {
            return null;
        }
        try {
            $sanctumId = \Illuminate\Support\Facades\Auth::guard('sanctum')->id();
        } catch (\Throwable $e) {
            return null;
        }

        return $sanctumId ? (int) $sanctumId : null;
    }

    public function getIsFollowedByAttribute(): bool
    {
        $authId = self::viewerId();
        if (! $authId || (int) $this->user_id === (int) $authId) {
            return false;
        }

        if (self::$viewerFollowerIds === null || self::$viewerFollowerCacheFor !== (int) $authId) {
            self::$viewerFollowerIds = array_flip(
                \Illuminate\Support\Facades\DB::table('follows')
                    ->where('followed_id', $authId)
                    ->where('status', 'accepted')
                    ->pluck('follower_id')
                    ->map(fn ($id) => (int) $id)
                    ->all()
            );
            self::$viewerFollowerCacheFor = (int) $authId;
        }

        return isset(self::$viewerFollowerIds[(int) $this->user_id]);
    }

    /**
     * Cache lingkar story per request.
     *
     * `has_story` & `story_viewed` disertakan di SETIAP serialisasi user agar lingkar story
     * muncul di mana pun ada avatar (pencarian, komentar, follower, penonton, dsb.) tanpa
     * perlu tiap controller menghitungnya sendiri.
     *
     * Efisien karena himpunannya kecil: hanya user yang PUNYA story aktif (24 jam terakhir),
     * bukan seluruh pengguna. Dua query untuk seluruh request — bukan satu per user.
     */
    private static ?array $activeStoryOwners = null;   // user_id => jumlah story aktif
    private static ?array $storySeenByViewer = null;    // user_id => jumlah story-nya yg dilihat viewer
    private static ?int $storyCacheFor = null;

    public static function forgetStoryCache(): void
    {
        self::$activeStoryOwners = null;
        self::$storySeenByViewer = null;
        self::$storyCacheFor = null;
    }

    private static function ensureStoryCache(?int $viewerId): void
    {
        if (self::$activeStoryOwners !== null && self::$storyCacheFor === (int) $viewerId) {
            return;
        }

        $activeStories = \Illuminate\Support\Facades\DB::table('stories')
            ->where('expires_at', '>', now())
            ->get(['story_id', 'user_id']);

        $owners = [];
        foreach ($activeStories as $row) {
            $owners[(int) $row->user_id] = ($owners[(int) $row->user_id] ?? 0) + 1;
        }
        self::$activeStoryOwners = $owners;

        $seen = [];
        if ($viewerId && $activeStories->isNotEmpty()) {
            $storyIds = $activeStories->pluck('story_id')->all();
            $ownerByStory = $activeStories->pluck('user_id', 'story_id');
            $viewedIds = \Illuminate\Support\Facades\DB::table('story_views')
                ->where('viewer_id', $viewerId)
                ->whereIn('story_id', $storyIds)
                ->pluck('story_id');
            foreach ($viewedIds as $storyId) {
                $ownerId = (int) ($ownerByStory[$storyId] ?? 0);
                if ($ownerId) {
                    $seen[$ownerId] = ($seen[$ownerId] ?? 0) + 1;
                }
            }
        }
        self::$storySeenByViewer = $seen;
        self::$storyCacheFor = (int) $viewerId;
    }

    /**
     * Bolehkah viewer melihat story user ini?
     *
     * Story mengikuti privasi akun: akun privat hanya bisa dilihat oleh dirinya sendiri
     * atau pengikut yang SUDAH diterima. Akun publik selalu boleh. Tanpa aturan ini,
     * lingkar story akan bocor untuk akun privat yang belum kita follow.
     */
    private function viewerMaySeeStory(?int $viewerId): bool
    {
        if (! (bool) $this->is_private) {
            return true;
        }
        if (! $viewerId) {
            return false;
        }
        if ((int) $this->user_id === $viewerId) {
            return true;
        }

        return \Illuminate\Support\Facades\DB::table('follows')
            ->where('follower_id', $viewerId)
            ->where('followed_id', $this->user_id)
            ->where('status', 'accepted')
            ->exists();
    }

    public function getHasStoryAttribute(): bool
    {
        $viewerId = self::viewerId();
        self::ensureStoryCache($viewerId);

        if (! isset(self::$activeStoryOwners[(int) $this->user_id])) {
            return false;
        }

        return $this->viewerMaySeeStory($viewerId);
    }

    public function getStoryViewedAttribute(): bool
    {
        $viewerId = self::viewerId();
        self::ensureStoryCache($viewerId);

        $total = self::$activeStoryOwners[(int) $this->user_id] ?? 0;
        if ($total === 0 || ! $this->viewerMaySeeStory($viewerId)) {
            return false;
        }
        // "Dilihat" (ring abu-abu) hanya bila SEMUA story aktifnya sudah ditonton viewer.
        $seen = self::$storySeenByViewer[(int) $this->user_id] ?? 0;

        return $seen >= $total;
    }

    /**
     * URL thumbnail foto profil (kecil) untuk ditampilkan di mana pun avatar muncul.
     *
     * Foto asli tetap dipakai untuk pratinjau saat avatar diketuk; thumbnail dipangkas kecil
     * ini yang dimuat di daftar/komentar/dll supaya jauh lebih ringan. Bila thumbnail belum
     * ada (PP lama yang belum di-scan), jatuh ke foto asli agar tidak pernah kosong.
     */
    public function getProfilePictureThumbUrlAttribute(): ?string
    {
        // Kembalikan HANYA nilai thumbnail asli (atau null). JANGAN fallback ke foto asli:
        // dulu begitu, sehingga saat kolom ini tidak ikut ter-`select`, accessor mengirim URL
        // foto ASLI ke frontend yang menyangkanya thumbnail → thumbnail tak pernah terpakai.
        // Biarkan frontend yang memutuskan fallback (`thumb ?? asli`).
        return $this->attributes['profile_picture_thumb_url'] ?? null;
    }

    /**
     * Scope: hanya user yang sudah verifikasi email.
     * Dipakai untuk menyembunyikan user belum-verified dari area publik
     * (feed, explore, rekomendasi, search).
     */
    public function scopeEmailVerified($query)
    {
        return $query->whereNotNull('email_verified_at');
    }

    /**
     * Default preferensi notifikasi in-app.
     *   new_post_reminders: all | mutual | off
     *   likes, comments, mentions, follows: bool
     */
    public static function defaultNotificationPreferences(): array
    {
        return [
            'new_post_reminders' => 'all',
            'likes' => true,
            'comments' => true,
            'mentions' => true,
            'follows' => true,
        ];
    }

    /** Preferensi lengkap (default digabung dengan yang tersimpan). */
    public function notificationPreferences(): array
    {
        return array_merge(self::defaultNotificationPreferences(), $this->notification_preferences ?? []);
    }

    /**
     * Apakah user ini ingin menerima notifikasi in-app untuk tipe tertentu.
     * $context boleh berisi 'related_user_id' (pelaku) untuk aturan "mutual".
     */
    public function wantsNotificationType(?string $type, array $context = []): bool
    {
        $prefs = $this->notificationPreferences();

        switch ($type) {
            case 'new_post':
                $mode = $prefs['new_post_reminders'] ?? 'all';
                if ($mode === 'off') {
                    return false;
                }
                if ($mode === 'mutual') {
                    $authorId = $context['related_user_id'] ?? null;
                    // "mutual" = si pembuat postingan juga mengikuti saya (accepted).
                    return $authorId
                        ? $this->followers()->where('users.user_id', $authorId)
                            ->wherePivot('status', 'accepted')->exists()
                        : true;
                }
                return true;
            case 'like':
                return (bool) ($prefs['likes'] ?? true);
            case 'comment':
            case 'reply':
                return (bool) ($prefs['comments'] ?? true);
            case 'mention':
            case 'bio_mention':
            case 'story_mention':
                return (bool) ($prefs['mentions'] ?? true);
            case 'follow':
                return (bool) ($prefs['follows'] ?? true);
            // follow_request & follow_accepted selalu tampil (penting/aktvariabel), tak digate.
            default:
                return true;
        }
    }

    /**
     * Agar Laravel pakai "password_hash" untuk login
     */
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    public function getProfilePictureUrlAttribute($value): ?string
    {
        return $this->normalizeDefaultMediaUrl($value, 'default-profile.png');
    }

    public function getBannerUrlAttribute($value): ?string
    {
        return $this->normalizeDefaultMediaUrl($value, 'default-banner.png');
    }

    private function normalizeDefaultMediaUrl($value, string $defaultFile): ?string
    {
        if (!$value) {
            return null;
        }

        $path = parse_url((string) $value, PHP_URL_PATH) ?: (string) $value;

        return str_ends_with($path, "/{$defaultFile}") ? null : (string) $value;
    }

    /* ================================
     | 💡 RELASI ELOQUENT
     ================================= */

    public function posts()
    {
        return $this->hasMany(Post::class, 'user_id');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'user_id');
    }

    public function likes()
    {
        return $this->hasMany(Like::class, 'user_id');
    }

    // User.php
    public function followers()
    {
        return $this->belongsToMany(User::class, 'follows', 'followed_id', 'follower_id')
            ->withPivot('followed_at', 'status'); // tanpa withTimestamps
    }

    public function following()
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'followed_id')
            ->withPivot('followed_at', 'status'); // tanpa withTimestamps
    }


    public function stories()
    {
        return $this->hasMany(Story::class, 'user_id');
    }

    public function sentMessages()
    {
        return $this->hasMany(DirectMessage::class, 'sender_id');
    }

    public function receivedMessages()
    {
        return $this->hasMany(DirectMessage::class, 'receiver_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'recipient_id');
    }

    public function sendEmailVerificationNotification()
    {
        $this->notify(new CustomVerifyEmail);
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new CustomResetPassword($token));
    }
    public function ownedGroups()
    {
        return $this->hasMany(Group::class, 'owner_id', 'user_id');
    }

    public function groupMemberships()
    {
        return $this->hasMany(GroupMember::class, 'user_id', 'user_id');
    }

    public function groupMessages()
    {
        return $this->hasMany(GroupMessage::class, 'sender_id', 'user_id');
    }

public function bookmarks()
{
    return $this->hasMany(Bookmark::class, 'user_id');
}


public function bookmarkedPosts()
{
    return $this->belongsToMany(Post::class, 'bookmarks', 'user_id', 'post_id')
                ->withTimestamps();
}

public function loginHistories()
{
    return $this->hasMany(LoginHistory::class, 'user_id', 'user_id');
}

public function commentLikes()
{
    return $this->hasMany(CommentLike::class, 'user_id', 'user_id');
}

public function groups()
{
    return $this->belongsToMany(Group::class, 'group_members', 'user_id', 'group_id')
                ->withPivot('role', 'joined_at', 'is_muted');
}



}
