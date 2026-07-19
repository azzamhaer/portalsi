<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Pencarian terpadu: akun, hashtag/tag, dan konten (caption + tag) postingan.
 *   GET /search?q=...&type=all|users|tags|posts
 * type=all  → sedikit dari tiap kategori (untuk tampilan gabungan).
 * type=xxx  → daftar lebih panjang untuk kategori itu (tab "lihat semua").
 */
class SearchController extends Controller
{
    public function index(Request $request)
    {
        $authUser = $request->user();
        $q = trim((string) $request->input('q', ''));
        $type = $request->input('type', 'all');
        if (mb_strlen($q) < 1) {
            return response()->json(['users' => [], 'hashtags' => [], 'posts' => []]);
        }
        $like = '%'.$q.'%';
        $tagQ = '%'.ltrim($q, '#').'%';

        $users = ($type === 'all' || $type === 'users')
            ? User::query()
                ->emailVerified()
                ->where(fn ($w) => $w->where('username', 'like', $like)->orWhere('full_name', 'like', $like))
                ->when($authUser, fn ($x) => $x->where('user_id', '!=', $authUser->user_id))
                ->select('user_id', 'username', 'full_name', 'is_verified', 'profile_picture_url', 'role', 'is_private')
                ->orderByRaw('CASE WHEN username LIKE ? THEN 0 ELSE 1 END', [$q.'%'])
                ->limit($type === 'users' ? 30 : 8)
                ->get()
                ->map(fn ($u) => [
                    'user_id' => $u->user_id,
                    'username' => $u->username,
                    'full_name' => $u->full_name,
                    'is_verified' => (bool) $u->is_verified,
                    'profile_picture_url' => $u->profile_picture_url,
                    'role' => $u->role,
                    'is_private' => (bool) $u->is_private,
                ])
            : collect();

        $hashtags = ($type === 'all' || $type === 'tags')
            ? Tag::query()
                ->where('tag_name', 'like', $tagQ)
                ->withCount('posts')
                ->orderByDesc('posts_count')
                ->limit($type === 'tags' ? 40 : 8)
                ->get()
                ->map(fn ($t) => ['tag' => $t->tag_name, 'posts_count' => $t->posts_count])
            : collect();

        $posts = ($type === 'all' || $type === 'posts')
            ? $this->searchPosts($q, $authUser, $type === 'posts' ? 24 : 9)
            : collect();

        return response()->json([
            'users' => $users->values(),
            'hashtags' => $hashtags->values(),
            'posts' => $posts->values(),
        ]);
    }

    private function searchPosts(string $q, $authUser, int $limit)
    {
        $like = '%'.$q.'%';
        $tagQ = ltrim($q, '#');

        $allowedIds = $authUser
            ? $authUser->following()->wherePivot('status', 'accepted')->pluck('users.user_id')->toArray()
            : [];
        if ($authUser) {
            $allowedIds[] = $authUser->user_id;
        }

        return Post::with('user')
            ->where('is_archived', false)
            ->whereHas('user', function ($u) use ($allowedIds) {
                $u->where(function ($w) use ($allowedIds) {
                    $w->where('is_private', false);
                    if (! empty($allowedIds)) {
                        $w->orWhereIn('users.user_id', $allowedIds);
                    }
                });
            })
            ->where(function ($w) use ($like, $tagQ) {
                $w->where('caption', 'like', $like)
                    ->orWhereHas('tags', fn ($t) => $t->where('tag_name', 'like', '%'.$tagQ.'%'));
            })
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function ($post) {
                $variants = is_array($post->media_variants) ? $post->media_variants : [];
                $thumb = $variants['thumbnail']['url']
                    ?? (! empty($post->thumbnail_url) && ! preg_match('#placeholder|/img/#i', (string) $post->thumbnail_url)
                        ? $post->thumbnail_url
                        : null);

                return [
                    'post_id' => $post->post_id,
                    'caption' => $post->caption,
                    'media_url' => $post->media_url,
                    'thumbnail_url' => $thumb,
                    'is_video' => (bool) $post->is_video,
                    'is_multiple' => is_array($post->media_urls) && count($post->media_urls) > 1,
                    'user' => [
                        'user_id' => $post->user->user_id,
                        'username' => $post->user->username,
                    ],
                ];
            });
    }
}
