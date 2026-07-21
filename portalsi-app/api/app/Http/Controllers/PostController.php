<?php

namespace App\Http\Controllers;

use App\Events\NotificationCreated;
use App\Jobs\GenerateVideoThumbnail;
use App\Models\Notification;
use App\Models\Post;
use App\Models\PostMention;
use App\Models\Tag;
use App\Models\User;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    private function mediaDisk(): string
    {
        return config('filesystems.default', 'public');
    }

    private function storagePathFromUrl(string $url): string
    {
        $path = ltrim(parse_url($url, PHP_URL_PATH) ?? $url, '/');

        return preg_replace('#^storage/#', '', $path);
    }

    /**
     * Tambahkan info story (has_story & story_viewed) ke user
     */
    private function attachStoryInfo($user, $authUser)
    {
        $storyIds = DB::table('stories')
            ->where('user_id', $user->user_id)
            ->where('created_at', '>=', Carbon::now()->subHours(24))
            ->pluck('story_id');

        $user->has_story = $storyIds->isNotEmpty();

        if ($storyIds->isNotEmpty() && $authUser) {
            $viewedCount = DB::table('story_views')
                ->whereIn('story_id', $storyIds)
                ->where('viewer_id', $authUser->user_id)
                ->count();

            $user->story_viewed = ($viewedCount === $storyIds->count());
        } else {
            $user->story_viewed = false;
        }

        return $user;
    }

    public function index(Request $request)
    {
        $authUser = Auth::user();
        $page = max(1, (int) $request->input('page', 1));
        $perPage = max(1, min(20, (int) $request->input('per_page', 10)));

        $followingIds = $authUser->following()
            ->wherePivot('status', 'accepted')
            ->pluck('users.user_id');

        // ========== MAIN POSTS ==========
        $mainPosts = collect();

        if ($followingIds->isEmpty()) {
            // random feed untuk user yang belum follow siapapun
            $mainPosts = Post::with(['user', 'tags', 'mentions'])
                ->withCount(['likes', 'comments'])
                ->where('is_archived', false)
                ->where('is_draft', false)
                ->whereHas('user', fn ($q) => $q->where('is_private', 0)->whereNotNull('email_verified_at'))
                ->orderByDesc('created_at')
                ->take(100)
                ->get()
                ->map(function ($post) use ($authUser) {
                    // is_liked / is_bookmarked diisi batch setelah semua sumber digabung (anti N+1).
                    $post->type = 'post';
                    $post->user = $this->attachStoryInfo($post->user, $authUser);
                    $post->user->is_verified = (bool) $post->user->is_verified;
                    // Belum follow siapa pun → hanya post sendiri yang "tanpa tombol".
                    $post->user->is_following = ($post->user_id == $authUser->user_id);

                    // musik fields (safe)
                    $post->music_track_name = $post->music_track_name ?? null;
                    $post->music_artist_name = $post->music_artist_name ?? null;
                    $post->music_preview_url = $post->music_preview_url ?? null;
                    $post->music_album_art_url = $post->music_album_art_url ?? null;
                    $post->music_start_position_ms = $post->music_start_position_ms ?? null;
                    $post->music_clip_duration_ms = $post->music_clip_duration_ms ?? null;

                    // thumbnail safe field
                    $post->thumbnail_url = $post->thumbnail_url ?? null;

                    return $post;
                })
                ->shuffle()
                ->values();
        } else {
            // Distribusi feed
            $total = 100;
            $countTimeline = (int) round($total * 0.50);
            $countRelasi = (int) round($total * 0.10);
            $countRandom = (int) round($total * 0.25);
            $countLiked = (int) round($total * 0.15);
            $timelineUserIds = $followingIds->concat([$authUser->user_id])->unique()->values();

            // Timeline posts (dari following + diri sendiri)
            $timelinePosts = Post::with(['user', 'tags', 'mentions'])
                ->withCount(['likes', 'comments'])
                ->where('is_archived', false)
                ->where('is_draft', false)
                ->whereIn('user_id', $timelineUserIds)
                ->orderByDesc('created_at')
                ->take($countTimeline)
                ->get();

            // Second degree (temannya teman)
            $secondDegreeIds = DB::table('follows')
                ->whereIn('follower_id', $followingIds)
                ->whereNotIn('followed_id', $followingIds)
                ->where('followed_id', '!=', $authUser->user_id)
                ->pluck('followed_id');

            $relasiPosts = Post::with(['user', 'tags', 'mentions'])
                ->withCount(['likes', 'comments'])
                ->where('is_archived', false)
                ->where('is_draft', false)
                ->whereIn('user_id', $secondDegreeIds)
                ->whereHas('user', fn ($q) => $q->where('is_private', 0))
                ->orderByDesc('created_at')
                ->take($countRelasi)
                ->get();

            // Random posts
            $randomPosts = Post::with(['user', 'tags', 'mentions'])
                ->withCount(['likes', 'comments'])
                ->where('is_archived', false)
                ->where('is_draft', false)
                ->whereNotIn('user_id', $followingIds)
                ->where('user_id', '!=', $authUser->user_id)
                ->whereHas('user', fn ($q) => $q->where('is_private', 0))
                ->inRandomOrder()
                ->take($countRandom)
                ->get();

            // Posts yang disukai following
            $likedByFollowingIds = DB::table('likes')
                ->whereIn('user_id', $followingIds)
                ->pluck('post_id');

            $likedPosts = Post::with(['user', 'tags', 'mentions'])
                ->withCount(['likes', 'comments'])
                ->where('is_archived', false)
                ->where('is_draft', false)
                ->whereIn('post_id', $likedByFollowingIds)
                ->whereHas('user', fn ($q) => $q->where('is_private', 0))
                ->orderByDesc('created_at')
                ->take($countLiked)
                ->get();

            // Shuffle tiap kategori
            $timelinePosts = $timelinePosts->shuffle();
            $relasiPosts = $relasiPosts->shuffle();
            $randomPosts = $randomPosts->shuffle();
            $likedPosts = $likedPosts->shuffle();

            // Gabungkan dan tambahkan fields tambahan
            $mainPosts = $timelinePosts
                ->merge($relasiPosts)
                ->merge($randomPosts)
                ->merge($likedPosts)
                ->map(function ($post) use ($authUser, $followingIds) {
                    // is_liked / is_bookmarked diisi batch setelah semua sumber digabung (anti N+1).
                    $post->type = 'post';
                    $post->user = $this->attachStoryInfo($post->user, $authUser);
                    $post->user->is_verified = (bool) $post->user->is_verified;
                    $post->user->is_following = ($post->user_id == $authUser->user_id) || $followingIds->contains($post->user_id);

                    $post->music_track_name = $post->music_track_name ?? null;
                    $post->music_artist_name = $post->music_artist_name ?? null;
                    $post->music_preview_url = $post->music_preview_url ?? null;
                    $post->music_album_art_url = $post->music_album_art_url ?? null;
                    $post->music_start_position_ms = $post->music_start_position_ms ?? null;
                    $post->music_clip_duration_ms = $post->music_clip_duration_ms ?? null;

                    // thumbnail safe field
                    $post->thumbnail_url = $post->thumbnail_url ?? null;

                    return $post;
                })
                ->shuffle()
                ->values();
        }

        if ($mainPosts->isEmpty()) {
            $mainPosts = Post::with(['user', 'tags', 'mentions'])
                ->withCount(['likes', 'comments'])
                ->where('is_archived', false)
                ->where('is_draft', false)
                ->whereHas('user', fn ($q) => $q->where('is_private', 0))
                ->orderByDesc('created_at')
                ->take(100)
                ->get()
                ->map(function ($post) use ($authUser, $followingIds) {
                    // is_liked / is_bookmarked diisi batch setelah semua sumber digabung (anti N+1).
                    $post->type = 'post';
                    $post->user = $this->attachStoryInfo($post->user, $authUser);
                    $post->user->is_verified = (bool) $post->user->is_verified;
                    $post->user->is_following = ($post->user_id == $authUser->user_id) || $followingIds->contains($post->user_id);
                    $post->music_track_name = $post->music_track_name ?? null;
                    $post->music_artist_name = $post->music_artist_name ?? null;
                    $post->music_preview_url = $post->music_preview_url ?? null;
                    $post->music_album_art_url = $post->music_album_art_url ?? null;
                    $post->music_start_position_ms = $post->music_start_position_ms ?? null;
                    $post->music_clip_duration_ms = $post->music_clip_duration_ms ?? null;
                    $post->thumbnail_url = $post->thumbnail_url ?? null;

                    return $post;
                })
                ->shuffle()
                ->values();
        }

        // Kurangi monoton ("akun itu-itu saja"): batasi maksimal 3 post per akun,
        // dan selang-seling agar tidak beruntun dari akun yang sama.
        $perAuthor = [];
        $mainPosts = $mainPosts->filter(function ($post) use (&$perAuthor) {
            $uid = $post->user_id;
            $perAuthor[$uid] = ($perAuthor[$uid] ?? 0) + 1;

            return $perAuthor[$uid] <= 3;
        })->values();

        // Batch is_liked / is_bookmarked untuk SEMUA post feed sekaligus (hindari N+1
        // yang membuat home lambat/timeout → "postingan belum dapat dimuat").
        $feedIds = $mainPosts->pluck('post_id')->filter()->values();
        if ($feedIds->isNotEmpty()) {
            $likedSet = \App\Models\Like::where('user_id', $authUser->user_id)
                ->whereIn('post_id', $feedIds)->pluck('post_id')->flip();
            $bookmarkedSet = \App\Models\Bookmark::where('user_id', $authUser->user_id)
                ->whereIn('post_id', $feedIds)->pluck('post_id')->flip();
            $mainPosts->each(function ($post) use ($likedSet, $bookmarkedSet) {
                $post->is_liked = $likedSet->has($post->post_id);
                $post->is_bookmarked = $bookmarkedSet->has($post->post_id);
            });
        }

        // ========== SUGGESTIONS ==========
        $suggestions = collect();

        if ($followingIds->isNotEmpty()) {
            $mutuals = DB::table('follows')
                ->select('followed_id', DB::raw('COUNT(*) as mutual_count'))
                ->whereIn('follower_id', $followingIds)
                ->whereNotIn('followed_id', $followingIds)
                ->where('followed_id', '!=', $authUser->user_id)
                ->groupBy('followed_id')
                ->orderByDesc('mutual_count')
                ->take(10)
                ->get();

            $userIds = $mutuals->pluck('followed_id');

            if ($userIds->isNotEmpty()) {
                $users = User::whereIn('user_id', $userIds)
                    ->where('is_private', 0)
                    ->orderByRaw('FIELD(user_id, '.implode(',', $userIds->toArray()).')')
                    ->get();

                $suggestions = $suggestions->merge($users);
            }
        }

        if ($suggestions->count() < 10) {
            $need = 10 - $suggestions->count();

            $randomUsers = User::where('user_id', '!=', $authUser->user_id)
                ->whereNotIn('user_id', $followingIds)
                ->whereNotIn('user_id', $suggestions->pluck('user_id'))
                ->where('is_private', 0)
                ->inRandomOrder()
                ->take($need)
                ->get();

            $suggestions = $suggestions->merge($randomUsers);
        }

        $suggestions = $suggestions->map(function ($user) use ($authUser) {
            $isFollowBack = DB::table('follows')
                ->where('follower_id', $user->user_id)
                ->where('followed_id', $authUser->user_id)
                ->where('status', 'accepted')
                ->exists();

            $user->is_follow_back = (bool) $isFollowBack;
            $user = $this->attachStoryInfo($user, $authUser);
            $user->is_verified = (bool) $user->is_verified;

            return $user;
        })
            ->shuffle()
            ->sortByDesc('is_follow_back')
            ->values();

        // ========== MERGE POSTS + SUGGESTIONS (FULL FEED DULU) ==========
        $feedWithSuggestions = collect();
        $postCount = 0;
        foreach ($mainPosts as $item) {
            $feedWithSuggestions->push($item);
            $postCount++;
            if ($postCount === 2 || ($postCount > 2 && $postCount % 8 === 0)) {
                $feedWithSuggestions->push((object) [
                    'type' => 'suggestion',
                    'users' => $suggestions->shuffle()
                        ->take(15)
                        ->map(function ($user) {
                            return [
                                'user_id' => $user->user_id,
                                'username' => $user->username,
                                'full_name' => $user->full_name,
                                'profile_picture_url' => $user->profile_picture_url,
                                'role' => $user->role ?? 'student',
                                'is_private' => (bool) $user->is_private,
                                'is_follow_back' => (bool) $user->is_follow_back,
                                'is_verified' => (bool) $user->is_verified,
                                'has_story' => (bool) $user->has_story,
                                'story_viewed' => (bool) $user->story_viewed,
                            ];
                        })
                        ->values(),
                ]);
            }
        }

        // ✅ Sekarang baru di-paginate setelah feed full terbentuk
        $totalFeed = $feedWithSuggestions->count();
        $feedSlice = $feedWithSuggestions
            ->slice(($page - 1) * $perPage, $perPage)
            ->values();

        $paginator = new LengthAwarePaginator(
            $feedSlice,
            $totalFeed,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        $nextPage = $paginator->currentPage() < $paginator->lastPage()
            ? $request->url().'?'.http_build_query(array_merge($request->query(), ['page' => $paginator->currentPage() + 1]))
            : null;

        $prevPage = $paginator->currentPage() > 1
            ? $request->url().'?'.http_build_query(array_merge($request->query(), ['page' => $paginator->currentPage() - 1]))
            : null;

        $lastPage = $request->url().'?'.http_build_query(array_merge($request->query(), ['page' => $paginator->lastPage()]));

        return response()->json([
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'next_page_url' => $nextPage,
            'prev_page_url' => $prevPage,
            'last_page_url' => $lastPage,
            'feed' => $feedSlice,
        ]);
    }

    public function explore(Request $request)
    {
        $authUser = Auth::user();
        $page = max(1, (int) $request->input('page', 1));
        $perPage = max(1, (int) $request->input('per_page', 15));

        $query = Post::with(['user', 'tags'])
            ->withCount(['likes', 'comments'])
            ->where('is_archived', false)
            ->where('is_draft', false)
            ->whereHas('user', fn ($q) => $q->whereNotNull('email_verified_at'));

        if ($request->filled('tag')) {
            $tagName = $request->tag;
            $query->whereHas('tags', fn ($q) => $q->where('tag_name', $tagName));
        }

        $sort = $request->input('sort', 'random');
        if ($sort === 'popular') {
            // Acak-populer: postingan ber-like tinggi lebih mungkin di atas, tapi urutannya
            // bervariasi (tidak selalu 1 postingan yang sama terus di puncak). Seed di-bucket
            // per 30 menit agar pagination (infinite scroll) tetap konsisten dalam satu sesi.
            $seed = (int) (($authUser->user_id ?? 0) + floor(time() / 1800));
            $query->orderByRaw('RAND(?) * (likes_count + 1) DESC', [$seed]);
        } elseif ($sort === 'newest') {
            $query->orderByDesc('created_at');
        } else {
            $query->inRandomOrder();
        }

        $total = $query->count();

        $posts = $query->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(function ($post) use ($authUser) {
                // tambahkan is_liked / is_bookmarked jika ada auth
                $post->is_liked = $authUser ? $post->likes()->where('user_id', $authUser->user_id)->exists() : false;
                $post->is_bookmarked = $authUser ? $post->bookmarks()->where('user_id', $authUser->user_id)->exists() : false;
                $post->type = 'post';
                $post->user = $this->attachStoryInfo($post->user, $authUser);
                $post->user->is_verified = (bool) $post->user->is_verified;

                // musik
                $post->music_track_name = $post->music_track_name ?? null;
                $post->music_artist_name = $post->music_artist_name ?? null;
                $post->music_preview_url = $post->music_preview_url ?? null;
                $post->music_album_art_url = $post->music_album_art_url ?? null;
                $post->music_start_position_ms = $post->music_start_position_ms ?? null;
                $post->music_clip_duration_ms = $post->music_clip_duration_ms ?? null;

                // thumbnail
                $post->thumbnail_url = $post->thumbnail_url ?? null;

                return $post;
            });

        $paginator = new LengthAwarePaginator(
            $posts,
            $total,
            $perPage,
            $page,
            ['path' => url()->current(), 'query' => $request->query()]
        );

        return response()->json($paginator);
    }

    public function show($id)
    {
        $authUser = Auth::user();

        $post = Post::with(['user', 'tags', 'mentions', 'acceptedCollaborators'])
            ->withCount(['likes', 'comments'])
            ->findOrFail($id);

        $owner = $post->user;

        // Draft hanya untuk pemiliknya. Dijaga di sini juga, bukan hanya lewat penyaringan
        // di daftar — kalau tidak, siapa pun yang menebak/menyimpan ID post bisa membukanya.
        if ($post->is_draft && (! $authUser || $authUser->user_id !== $post->user_id)) {
            return response()->json(['message' => 'Postingan tidak ditemukan.'], 404);
        }

        // Cek akses view
        $canView = ! $owner->is_private ||
            ($authUser && (
                $authUser->user_id === $owner->user_id ||
                $owner->followers()
                    ->where('follower_id', $authUser->user_id)
                    ->where('status', 'accepted')
                    ->exists()
            ));

        if (! $canView) {
            return response()->json([
                'message' => 'Post ini hanya bisa dilihat oleh followers yang telah diterima.',
                'owner_username' => $owner->username,
                'owner_id' => $owner->user_id,
            ], 403);
        }

        $post->is_liked = $authUser
            ? $post->likes()->where('user_id', $authUser->user_id)->exists()
            : false;

        $post->is_bookmarked = $authUser
            ? $post->bookmarks()->where('user_id', $authUser->user_id)->exists()
            : false;

        // Dipakai modal edit untuk menampilkan status sematan.
        $post->is_pinned = $post->pinned_at !== null;

        if ($authUser) {
            if ($authUser->user_id === $owner->user_id) {
                $post->user->is_following = true;
            } else {
                $post->user->is_following = $owner->followers()
                    ->where('follower_id', $authUser->user_id)
                    ->where('status', 'accepted')
                    ->exists();
            }
        } else {
            $post->user->is_following = false;
        }

        $post->user = $this->attachStoryInfo($post->user, $authUser);
        $post->user->is_verified = (bool) $post->user->is_verified;

        // musik
        $post->music_track_name = $post->music_track_name ?? null;
        $post->music_artist_name = $post->music_artist_name ?? null;
        $post->music_preview_url = $post->music_preview_url ?? null;
        $post->music_album_art_url = $post->music_album_art_url ?? null;
        $post->music_start_position_ms = $post->music_start_position_ms ?? null;
        $post->music_clip_duration_ms = $post->music_clip_duration_ms ?? null;

        // thumbnail
        $post->thumbnail_url = $post->thumbnail_url ?? null;

        // Status undangan kolaborasi untuk viewer (untuk banner accept/reject di post).
        $post->viewer_collab_status = $authUser
            ? (DB::table('post_collaborators')
                ->where('post_id', $post->post_id)
                ->where('user_id', $authUser->user_id)
                ->value('status') ?: null)
            : null;

        return response()->json($post);
    }

    // Buat post baru (media upload pakai file). Menerima optional 'thumbnail' file untuk video.
    public function store(Request $request)
    {
        $request->validate([
            'caption' => 'nullable|string|max:2200',
            'media' => 'nullable|file|max:512000',
            'media_key' => 'nullable|string|max:255', // jalur direct-upload ke R2
            'thumbnail' => 'nullable|file|mimes:jpg,jpeg,png|max:51200', // up to 50MB thumb jika perlu (ubah sesuai kebijakan)
            'location' => 'nullable|string',
            'is_archived' => 'nullable|boolean',
            'is_draft' => 'nullable|boolean',
            'is_video' => 'nullable|boolean',
            'video_muted' => 'nullable|boolean',
            'collaborators' => 'nullable|array|max:5',
            'collaborators.*' => 'integer',
            // musik fields
            'music_track_name' => 'nullable|string|max:255',
            'music_artist_name' => 'nullable|string|max:255',
            'music_preview_url' => 'nullable|string',
            'music_album_art_url' => 'nullable|string|max:255',
            'music_start_position_ms' => 'nullable|integer',
            'music_clip_duration_ms' => 'nullable|string|max:255',
        ]);

        $imageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $videoExtensions = ['mp4', 'mov', 'webm', 'avi', '3gp', 'mkv', 'm4v'];
        $allowedExtensions = [...$imageExtensions, ...$videoExtensions];
        $disk = $this->mediaDisk();

        // Kumpulkan media dari jalur direct (media_keys[] / media_key) dan fallback (file media[]).
        $rawKeys = $request->input('media_keys', []);
        if (! is_array($rawKeys)) {
            $rawKeys = [$rawKeys];
        }
        $singleKey = trim((string) $request->input('media_key'));
        if ($singleKey !== '') {
            $rawKeys[] = $singleKey;
        }
        $rawKeys = array_values(array_unique(array_filter(array_map(
            fn ($key) => trim((string) $key),
            $rawKeys
        ))));

        $files = array_values(array_filter(\Illuminate\Support\Arr::wrap($request->file('media'))));

        $total = count($rawKeys) + count($files);
        if ($total === 0) {
            return response()->json([
                'message' => 'Media wajib diunggah.',
                'errors' => ['media' => ['Media wajib diunggah.']],
            ], 422);
        }
        if ($total > 15) {
            return response()->json([
                'message' => 'Maksimal 15 foto per postingan.',
                'errors' => ['media' => ['Maksimal 15 foto per postingan.']],
            ], 422);
        }

        $mediaUrls = [];
        $extensions = [];

        // Jalur direct: verifikasi tiap key di R2.
        foreach ($rawKeys as $key) {
            if (! preg_match('#^uploads/(posts|stories)/[A-Za-z0-9._/\-]+\.[A-Za-z0-9]+$#', $key)
                || str_contains($key, '..')) {
                return response()->json([
                    'message' => 'Key media tidak valid.',
                    'errors' => ['media' => ['Key media tidak valid.']],
                ], 422);
            }
            $ext = strtolower(pathinfo($key, PATHINFO_EXTENSION));
            if (! in_array($ext, $allowedExtensions, true)) {
                return response()->json([
                    'message' => 'Format media tidak didukung.',
                    'errors' => ['media' => ['Format media tidak didukung.']],
                ], 422);
            }
            try {
                $exists = Storage::disk($disk)->exists($key);
            } catch (\Throwable $error) {
                return response()->json(['message' => 'Media belum dapat diverifikasi. Coba lagi.'], 503);
            }
            if (! $exists) {
                return response()->json([
                    'message' => 'Media belum selesai terunggah. Coba lagi.',
                    'errors' => ['media' => ['Media belum terunggah.']],
                ], 422);
            }
            $extensions[] = $ext;
            $mediaUrls[] = Storage::disk($disk)->url($key);
        }

        // Jalur fallback: simpan tiap file ke disk.
        foreach ($files as $media) {
            $ext = strtolower($media->getClientOriginalExtension());
            if (! in_array($ext, $allowedExtensions, true)) {
                return response()->json([
                    'message' => 'Format media tidak didukung. Gunakan JPG, PNG, WebP, GIF, MP4, MOV, WebM, AVI, 3GP, MKV, atau M4V.',
                    'errors' => ['media' => ['Format media tidak didukung.']],
                ], 422);
            }
            try {
                $mediaPath = $media->store('uploads/posts', $disk);
            } catch (\Throwable $error) {
                \Log::error('Post media upload failed', [
                    'user_id' => Auth::id(),
                    'extension' => $ext,
                    'error' => $error->getMessage(),
                ]);

                return response()->json(['message' => 'Media gagal disimpan ke penyimpanan. Silakan coba lagi.'], 503);
            }
            if (! $mediaPath) {
                return response()->json(['message' => 'Media gagal disimpan ke penyimpanan. Silakan coba lagi.'], 503);
            }
            $extensions[] = $ext;
            $mediaUrls[] = Storage::disk($disk)->url($mediaPath);
        }

        // Aturan: video hanya boleh tunggal; unggah banyak media hanya untuk foto.
        $hasVideo = count(array_intersect($extensions, $videoExtensions)) > 0;
        if ($hasVideo && count($mediaUrls) > 1) {
            return response()->json([
                'message' => 'Video hanya boleh satu per postingan. Untuk banyak media, gunakan foto.',
                'errors' => ['media' => ['Video hanya boleh satu per postingan.']],
            ], 422);
        }

        $isVideo = $hasVideo;
        $mediaUrl = $mediaUrls[0];
        $extension = $extensions[0];

        // Simpan thumbnail jika ada (frontend disarankan mengirim screenshot 1 detik pertama)
        $thumbnailUrl = null;
        if ($request->hasFile('thumbnail')) {
            try {
                $thumbPath = $request->file('thumbnail')->store('uploads/posts/thumbnails', $disk);
                if ($thumbPath) {
                    $thumbnailUrl = Storage::disk($disk)->url($thumbPath);
                }
            } catch (\Throwable $error) {
                // Thumbnail is an optimization only; never reject an otherwise valid video.
                \Log::warning('Video thumbnail upload failed', [
                    'user_id' => Auth::id(),
                    'error' => $error->getMessage(),
                ]);
            }
        }

        $post = Post::create([
            'user_id' => Auth::id(),
            'caption' => $request->caption,
            'media_url' => $mediaUrl,
            'media_urls' => count($mediaUrls) > 1 ? $mediaUrls : null,
            'thumbnail_url' => $thumbnailUrl,
            // Tandai untuk pipeline varian (rendition + thumbnail square) via cron media:process-pending.
            'media_status' => 'pending',
            'location' => $request->location,
            'is_archived' => $request->is_archived ?? false,
            // Draft: media tetap diunggah & diproses, tapi hanya pemiliknya yang bisa melihat.
            'is_draft' => $request->boolean('is_draft'),
            'is_video' => $isVideo,
            // Video dibisukan bila diminta user ATAU saat post memakai musik.
            'video_muted' => $isVideo && ($request->boolean('video_muted') || $request->filled('music_track_name')),
            // musik
            'music_track_name' => $request->music_track_name,
            'music_artist_name' => $request->music_artist_name,
            'music_preview_url' => $request->music_preview_url,
            'music_album_art_url' => $request->music_album_art_url,
            'music_start_position_ms' => $request->music_start_position_ms,
            'music_clip_duration_ms' => $request->music_clip_duration_ms,
        ]);

        // Varian media (thumbnail square + rendition) dibuat SEGERA setelah respons terkirim
        // (afterResponse) supaya thumbnail siap secepatnya. Cron media:process-pending jadi
        // jaring pengaman untuk yang gagal/terlewat. Tidak memblokir request user.
        \App\Jobs\ProcessPostMedia::dispatch($post->post_id)->afterResponse();

        // Undang co-author (status pending → menunggu persetujuan mereka).
        $collabIds = collect($request->input('collaborators', []))
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($id) => $id > 0 && $id !== (int) Auth::id())
            ->unique()
            ->take(5);
        if ($collabIds->isNotEmpty()) {
            $validIds = User::whereIn('user_id', $collabIds)->pluck('user_id');
            foreach ($validIds as $uid) {
                $post->collaborators()->syncWithoutDetaching([
                    $uid => ['status' => 'pending'],
                ]);
                $notif = Notification::create([
                    'recipient_id' => $uid,
                    'type' => 'collab_invite',
                    'related_user_id' => Auth::id(),
                    'related_post_id' => $post->post_id,
                    'is_read' => false,
                    'created_at' => now(),
                ]);
                if ($notif) {
                    broadcast(new NotificationCreated($notif));
                }
            }
        }

        // Tangani hashtag
        if ($request->filled('caption')) {
            preg_match_all('/#(\w+)/', $request->caption, $tags);
            foreach ($tags[1] as $tagName) {
                $tag = Tag::firstOrCreate(['tag_name' => $tagName]);
                $post->tags()->attach($tag->tag_id);
            }
        }

        // Tangani mention
        if ($request->filled('caption')) {
            preg_match_all('/@([A-Za-z0-9._]+)/', $request->caption, $mentions);
            foreach ($mentions[1] as $username) {
                $mentionedUser = User::where('username', $username)->first();
                if ($mentionedUser && $mentionedUser->user_id !== Auth::id()) {
                    PostMention::create([
                        'post_id' => $post->post_id,
                        'mentioned_user_id' => $mentionedUser->user_id,
                    ]);
                    $notification = Notification::createFor($mentionedUser->user_id, [
                        'type' => 'mention',
                        'related_user_id' => Auth::id(),
                        'related_post_id' => $post->post_id,
                        'is_read' => false,
                    ]);
                    if ($notification) {
                        broadcast(new NotificationCreated($notification));
                    }
                }
            }
        }

        // Notifikasi ke followers (logika original dipertahankan; gunakan post_id konsisten)
        $author = $post->user;
        $followers = $author->followers()->wherePivot('status', 'accepted')->withPivot('followed_at')->get();

        foreach ($followers as $follower) {
            $postCountSinceFollow = Post::where('user_id', $author->user_id)
                ->where('created_at', '>=', $follower->pivot->followed_at)
                ->count();

            if ($postCountSinceFollow <= 2) {
                // Hormati preferensi penerima: "pengingat postingan baru dari..." (all/mutual/off).
                $notification = Notification::createFor($follower->user_id, [
                    'type' => 'new_post',
                    'related_user_id' => $author->user_id,
                    'related_post_id' => $post->post_id,
                ]);
                if ($notification) {
                    broadcast(new NotificationCreated($notification));
                }
            }
        }

        $post->load(['user', 'tags', 'mentions']);
        $post->loadCount(['likes', 'comments']);
        $post->is_liked = false;
        $post->is_bookmarked = false;
        $post->type = 'post';

        return response()->json([
            'message' => 'Post created',
            'post' => $post,
        ], 201);
    }

    // Edit post (media optional)
    public function update(Request $request, $id)
    {
        $post = Post::findOrFail($id);

        if ($post->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'caption' => 'nullable|string|max:2200',
            'media' => 'nullable|file|mimes:jpg,jpeg,png,mp4,mov,webm,avi,3gp,mkv|max:512000',
            'thumbnail' => 'nullable|file|mimes:jpg,jpeg,png|max:51200',
            'location' => 'nullable|string',
            'is_archived' => 'nullable|boolean',
            'is_video' => 'nullable|boolean',
            // musik
            'music_track_name' => 'nullable|string|max:255',
            'music_artist_name' => 'nullable|string|max:255',
            'music_preview_url' => 'nullable|string',
            'music_album_art_url' => 'nullable|string|max:255',
            'music_start_position_ms' => 'nullable|integer',
            'music_clip_duration_ms' => 'nullable|string|max:255',
        ]);

        // Replace media jika ada
        if ($request->hasFile('media')) {
            if ($post->media_url) {
                Storage::disk($this->mediaDisk())->delete(
                    $this->storagePathFromUrl($post->media_url)
                );
            }
            $disk = $this->mediaDisk();
            $mediaPath = $request->file('media')->store('uploads/posts', $disk);
            $post->media_url = Storage::disk($disk)->url($mediaPath);
        }

        // Replace thumbnail jika ada
        if ($request->hasFile('thumbnail')) {
            if ($post->thumbnail_url) {
                Storage::disk($this->mediaDisk())->delete(
                    $this->storagePathFromUrl($post->thumbnail_url)
                );
            }
            $disk = $this->mediaDisk();
            $thumbPathNew = $request->file('thumbnail')->store('uploads/posts/thumbnails', $disk);
            $post->thumbnail_url = Storage::disk($disk)->url($thumbPathNew);
        }

        // Update fields — pakai has() agar caption/lokasi bisa DIKOSONGKAN.
        // (Middleware ConvertEmptyStringsToNull membuat string kosong jadi null,
        //  sehingga "?? $post->caption" dulu selalu mempertahankan nilai lama.)
        if ($request->has('caption')) {
            $post->caption = $request->input('caption');
        }
        if ($request->has('location')) {
            $post->location = $request->input('location');
        }
        $post->is_archived = $request->is_archived ?? $post->is_archived;
        $post->is_video = $request->is_video ?? $post->is_video;

        // musik
        $post->music_track_name = $request->music_track_name ?? $post->music_track_name;
        $post->music_artist_name = $request->music_artist_name ?? $post->music_artist_name;
        $post->music_preview_url = $request->music_preview_url ?? $post->music_preview_url;
        $post->music_album_art_url = $request->music_album_art_url ?? $post->music_album_art_url;
        $post->music_start_position_ms = $request->music_start_position_ms ?? $post->music_start_position_ms;
        $post->music_clip_duration_ms = $request->music_clip_duration_ms ?? $post->music_clip_duration_ms;

        $post->save();

        return response()->json([
            'message' => 'Post updated',
            'post' => $post,
        ]);
    }

    // Hapus post
    public function destroy($id)
    {
        $post = Post::findOrFail($id);

        if ($post->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Hapus media
        if ($post->media_url) {
            Storage::disk($this->mediaDisk())->delete(
                $this->storagePathFromUrl($post->media_url)
            );
        }

        // Hapus thumbnail jika ada
        if ($post->thumbnail_url) {
            Storage::disk($this->mediaDisk())->delete(
                $this->storagePathFromUrl($post->thumbnail_url)
            );
        }

        $post->delete();

        return response()->json(['message' => 'Post deleted']);
    }

    /**
     * Daftar draft milik user yang sedang login.
     *
     * Selalu memakai Auth::id(), tidak menerima parameter user — draft bersifat pribadi
     * dan tidak boleh bisa dilihat siapa pun selain pemiliknya, termasuk lewat URL.
     */
    public function drafts(Request $request)
    {
        $authUser = Auth::user();
        $page = max(1, (int) $request->input('page', 1));
        $perPage = max(1, min(30, (int) $request->input('per_page', 12)));

        $paginated = Post::where('user_id', $authUser->user_id)
            ->where('is_draft', true)
            ->orderByDesc('created_at')
            ->select('post_id', 'caption', 'media_url', 'media_urls', 'is_video', 'created_at', 'thumbnail_url')
            ->paginate($perPage, ['*'], 'page', $page);

        $data = $paginated->getCollection()->map(function ($post) {
            return [
                'post_id' => $post->post_id,
                'caption' => $post->caption,
                'media_url' => $post->media_url,
                'thumbnail_url' => $post->thumbnail_url,
                'is_video' => (bool) $post->is_video,
                'is_multiple' => is_array($post->media_urls) && count($post->media_urls) > 1,
                'created_at' => $post->created_at,
            ];
        })->values();

        return response()->json([
            'data' => $data,
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
            'total' => $paginated->total(),
        ]);
    }

    /**
     * Terbitkan draft menjadi post biasa.
     *
     * Dipisahkan dari update() supaya niatnya eksplisit: sekali terbit, post langsung
     * masuk feed orang lain dan tidak bisa "dibatalkan" tanpa mengarsipkannya.
     */
    public function publish($id)
    {
        $post = Post::findOrFail($id);

        if ($post->user_id !== Auth::id()) {
            return response()->json(['message' => 'Hanya pemilik draft yang bisa menerbitkan.'], 403);
        }

        if (! $post->is_draft) {
            return response()->json([
                'message' => 'Postingan ini sudah terbit.',
                'post_id' => $post->post_id,
            ], 422);
        }

        $post->is_draft = false;
        // Waktu terbit dianggap sekarang supaya urutannya wajar di feed pengikut.
        $post->created_at = now();
        $post->save();

        return response()->json([
            'message' => 'Draft berhasil diterbitkan.',
            'post_id' => $post->post_id,
        ]);
    }

    /** Batas jumlah post yang boleh disematkan di profil. */
    public const MAX_PINNED = 3;

    /**
     * Sematkan / lepas sematan sebuah post.
     *
     * Body: `pinned` (bool). Dikirim eksplisit — bukan toggle — supaya dua permintaan
     * beruntun tidak saling membatalkan dan UI tidak pernah desinkron dengan database.
     */
    public function togglePin(Request $request, $id)
    {
        $post = Post::findOrFail($id);

        if ($post->user_id !== Auth::id()) {
            return response()->json(['message' => 'Hanya pemilik post yang bisa menyematkan.'], 403);
        }

        $desired = $request->input('pinned');
        $shouldPin = $desired === null
            ? $post->pinned_at === null
            : filter_var($desired, FILTER_VALIDATE_BOOLEAN);

        if ($shouldPin) {
            $currentlyPinned = Post::where('user_id', $post->user_id)
                ->whereNotNull('pinned_at')
                ->where('post_id', '!=', $post->post_id)
                ->count();

            // Divalidasi di server juga, bukan hanya di UI — batas ini harus tetap berlaku
            // walaupun permintaannya datang dari luar aplikasi.
            if ($currentlyPinned >= self::MAX_PINNED) {
                return response()->json([
                    'message' => 'Maksimal '.self::MAX_PINNED.' postingan yang bisa disematkan.',
                    'code' => 'PIN_LIMIT',
                    'max_pinned' => self::MAX_PINNED,
                ], 422);
            }
        }

        $post->pinned_at = $shouldPin ? now() : null;
        $post->save();

        return response()->json([
            'message' => $shouldPin ? 'Postingan disematkan.' : 'Sematan dilepas.',
            'pinned' => $shouldPin,
            'pinned_count' => Post::where('user_id', $post->user_id)->whereNotNull('pinned_at')->count(),
        ]);
    }

    public function circleAvatar($id)
    {
        $authUser = Auth::user();
        $user = User::select('user_id', 'profile_picture_url')->findOrFail($id);

        // Tambahkan info story
        $user = $this->attachStoryInfo($user, $authUser);

        return response()->json([
            'circle_avatar' => $user,
        ]);
    }

    public function clips($id, Request $request)
    {
        $authUser = Auth::user();

        // Ambil daftar exclude dari query param (kalau ada)
        $excludeIds = $request->input('exclude', []);
        if (! is_array($excludeIds)) {
            $excludeIds = [$excludeIds];
        }

        // Pastikan clip utama juga di-exclude dari next
        $excludeIds[] = $id;

        // Ambil clip utama
        $mainClip = Post::with(['user', 'tags'])
            ->withCount(['likes', 'comments'])
            ->where('is_video', true)
            ->where('is_archived', false)
            ->where('is_draft', false)
            ->findOrFail($id);

        $mainClip->is_liked = $authUser
            ? $mainClip->likes()->where('user_id', $authUser->user_id)->exists()
            : false;

        $mainClip->is_bookmarked = $authUser
            ? $mainClip->bookmarks()->where('user_id', $authUser->user_id)->exists()
            : false;

        $mainClip->type = 'clip';
        $mainClip->user = $this->attachStoryInfo($mainClip->user, $authUser);
        $mainClip->user->is_verified = (bool) $mainClip->user->is_verified;

        // musik + thumbnail untuk mainClip
        $mainClip->music_track_name = $mainClip->music_track_name ?? null;
        $mainClip->music_artist_name = $mainClip->music_artist_name ?? null;
        $mainClip->music_preview_url = $mainClip->music_preview_url ?? null;
        $mainClip->music_album_art_url = $mainClip->music_album_art_url ?? null;
        $mainClip->music_start_position_ms = $mainClip->music_start_position_ms ?? null;
        $mainClip->music_clip_duration_ms = $mainClip->music_clip_duration_ms ?? null;
        $mainClip->thumbnail_url = $mainClip->thumbnail_url ?? null;

        // Ambil 1 clip random, exclude id utama + exclude dari param
        $nextClips = Post::with(['user', 'tags'])
            ->withCount(['likes', 'comments'])
            ->where('is_video', true)
            ->where('is_archived', false)
            ->where('is_draft', false)
            ->whereNotIn('post_id', $excludeIds)
            ->inRandomOrder()
            ->take(1)
            ->get()
            ->map(function ($post) use ($authUser) {
                $post->is_liked = $authUser
                    ? $post->likes()->where('user_id', $authUser->user_id)->exists()
                    : false;

                $post->is_bookmarked = $authUser
                    ? $post->bookmarks()->where('user_id', $authUser->user_id)->exists()
                    : false;

                $post->type = 'clip';
                $post->user = $this->attachStoryInfo($post->user, $authUser);
                $post->user->is_verified = (bool) $post->user->is_verified;

                // musik + thumbnail
                $post->music_track_name = $post->music_track_name ?? null;
                $post->music_artist_name = $post->music_artist_name ?? null;
                $post->music_preview_url = $post->music_preview_url ?? null;
                $post->music_album_art_url = $post->music_album_art_url ?? null;
                $post->music_start_position_ms = $post->music_start_position_ms ?? null;
                $post->music_clip_duration_ms = $post->music_clip_duration_ms ?? null;
                $post->thumbnail_url = $post->thumbnail_url ?? null;

                return $post;
            });

        // Update exclude list dengan id baru yang sudah dipakai
        $newExcludeIds = array_merge($excludeIds, $nextClips->pluck('post_id')->toArray());

        // Buat next_page_url, bawa exclude list biar ga duplikat
        $lastId = $nextClips->last()?->post_id;
        $nextPageUrl = $lastId
            ? url('/api/clips/'.$lastId.'?'.http_build_query(['exclude' => $newExcludeIds]))
            : null;

        return response()->json([
            'clip' => $mainClip,
            'next_clips' => $nextClips,
            'next_page_url' => $nextPageUrl,
        ]);
    }

    /**
     * Feed Reels (video saja) — algoritma keberagaman untuk platform baru:
     *  - Acak (bukan berdasarkan like, agar tidak monoton saat masih sedikit interaksi).
     *  - Kecualikan post yang SUDAH dilihat user (dikirim via `exclude`) → yang sudah
     *    ditonton makin kecil peluang muncul lagi. Bila pool habis, klien mereset exclude.
     *  - Hindari penulis yang sama muncul berturut-turut dalam satu batch.
     *  - Hormati privasi: hanya akun publik atau yang diikuti (accepted).
     */
    public function reels(Request $request)
    {
        $authUser = Auth::user();
        $count = min(8, max(3, (int) $request->input('count', 6)));

        $exclude = $request->input('exclude', []);
        if (is_string($exclude)) {
            $exclude = array_filter(explode(',', $exclude), fn ($v) => $v !== '');
        }
        $exclude = array_slice(array_values(array_unique(array_map('intval', (array) $exclude))), 0, 500);

        $allowedIds = $authUser
            ? $authUser->following()->wherePivot('status', 'accepted')->pluck('users.user_id')->toArray()
            : [];
        if ($authUser) {
            $allowedIds[] = $authUser->user_id;
        }

        $base = Post::with(['user', 'tags', 'acceptedCollaborators'])
            ->withCount(['likes', 'comments'])
            ->where('is_video', true)
            ->where('is_archived', false)
            ->where('is_draft', false)
            ->whereHas('user', function ($u) use ($allowedIds) {
                $u->where(function ($w) use ($allowedIds) {
                    $w->where('is_private', false);
                    if (! empty($allowedIds)) {
                        $w->orWhereIn('users.user_id', $allowedIds);
                    }
                });
            });

        // Ambil sampel acak lebih besar (untuk diversity), kecualikan yang sudah dilihat.
        $sample = (clone $base)
            ->when(! empty($exclude), fn ($q) => $q->whereNotIn('post_id', $exclude))
            ->inRandomOrder()
            ->take($count * 5)
            ->get();

        // Bila hampir habis karena exclude, longgarkan (biar tetap ada konten — recycle).
        $exhausted = false;
        if ($sample->count() < $count) {
            $exhausted = true;
            $sample = (clone $base)->inRandomOrder()->take($count * 5)->get();
        }

        // Susun ulang agar penulis tidak sama beruntun (greedy).
        $ordered = collect();
        $pool = $sample->values()->all();
        $lastAuthor = null;
        while (count($pool) > 0 && $ordered->count() < $count) {
            $pickIndex = null;
            foreach ($pool as $i => $p) {
                if ($p->user_id !== $lastAuthor) {
                    $pickIndex = $i;
                    break;
                }
            }
            if ($pickIndex === null) {
                $pickIndex = 0; // semua sisa penulis sama → terpaksa
            }
            $chosen = $pool[$pickIndex];
            $ordered->push($chosen);
            $lastAuthor = $chosen->user_id;
            array_splice($pool, $pickIndex, 1);
        }

        // Status follow untuk semua pemilik reel sekaligus (dipakai tombol follow di UI).
        $authorIds = $ordered->pluck('user_id')->filter()->unique()->values();
        $followingSet = collect();
        if ($authUser && $authorIds->isNotEmpty()) {
            $followingSet = $authUser->following()
                ->wherePivot('status', 'accepted')
                ->whereIn('users.user_id', $authorIds)
                ->pluck('users.user_id')
                ->flip();
        }

        $data = $ordered->map(function ($post) use ($authUser, $followingSet) {
            $post->is_liked = $authUser
                ? $post->likes()->where('user_id', $authUser->user_id)->exists()
                : false;
            $post->is_bookmarked = $authUser
                ? $post->bookmarks()->where('user_id', $authUser->user_id)->exists()
                : false;
            $post->user->is_verified = (bool) $post->user->is_verified;
            $post->user->is_self = $authUser ? ($post->user_id === $authUser->user_id) : false;
            $post->user->is_following = $followingSet->has($post->user_id);
            $post->music_track_name = $post->music_track_name ?? null;
            $post->music_artist_name = $post->music_artist_name ?? null;
            $post->music_preview_url = $post->music_preview_url ?? null;
            $post->music_album_art_url = $post->music_album_art_url ?? null;
            $post->music_start_position_ms = $post->music_start_position_ms ?? null;
            $post->music_clip_duration_ms = $post->music_clip_duration_ms ?? null;
            $post->thumbnail_url = $post->thumbnail_url ?? null;

            return $post;
        })->values();

        return response()->json([
            'data' => $data,
            // exhausted=true → klien sebaiknya reset daftar "sudah dilihat".
            'exhausted' => $exhausted,
            'has_more' => ! $exhausted && $data->count() >= $count,
        ]);
    }
}
