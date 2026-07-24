<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected $table = 'posts';
    protected $primaryKey = 'post_id';

    protected $fillable = [
        'user_id',
        'caption',
        'media_url',
        'media_urls',
        'thumbnail_url',
        'has_custom_thumbnail',
        'media_variants',
        'media_status',
        'location',
        'is_archived',
        'is_draft',
        'moderated_at',
        'moderation_reason',
        'moderation_note',
        'moderated_by',
        'moderation_ack_at',
        'pinned_at',
        'is_video',
        'video_muted',
        'music_track_name',
        'music_artist_name',
        'music_preview_url',
        'music_album_art_url',
        'music_start_position_ms',
        'music_clip_duration_ms',
    ];

    protected $casts = [
        'is_archived' => 'boolean',
        'is_draft' => 'boolean',
        'moderated_at' => 'datetime',
        'moderation_ack_at' => 'datetime',
        'pinned_at' => 'datetime',
        'is_video' => 'boolean',
        'video_muted' => 'boolean',
        'has_custom_thumbnail' => 'boolean',
        'media_urls' => 'array',
        'media_variants' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'post_id');
    }

    public function likes()
    {
        return $this->hasMany(Like::class, 'post_id');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'post_tags', 'post_id', 'tag_id');
    }

    public function mentions()
    {
        return $this->hasMany(PostMention::class, 'post_id');
    }

public function bookmarks()
{
    return $this->hasMany(Bookmark::class, 'post_id');
}


public function bookmarkedByUsers()
{
    return $this->belongsToMany(User::class, 'bookmarks', 'post_id', 'user_id')
                ->withTimestamps();
}

    // Semua kolaborator (pending + accepted).
    public function collaborators()
    {
        return $this->belongsToMany(User::class, 'post_collaborators', 'post_id', 'user_id')
            ->withPivot('status', 'responded_at')
            ->withTimestamps();
    }

    // Hanya co-author yang sudah menerima undangan.
    public function acceptedCollaborators()
    {
        return $this->collaborators()->wherePivot('status', 'accepted');
    }

    protected $appends = ['co_authors'];

    /**
     * Daftar co-author (accepted) untuk response — hanya bila relasi sudah di-eager-load,
     * agar tidak memicu N+1. Endpoint yang butuh cukup ->with('acceptedCollaborators').
     */
    public function getCoAuthorsAttribute()
    {
        if (! $this->relationLoaded('acceptedCollaborators')) {
            return [];
        }

        return $this->acceptedCollaborators->map(fn ($u) => [
            'user_id' => $u->user_id,
            'username' => $u->username,
            'full_name' => $u->full_name,
            'profile_picture_url' => $u->profile_picture_url,
            'is_verified' => (bool) $u->is_verified,
            'role' => $u->role,
        ])->values();
    }
}
