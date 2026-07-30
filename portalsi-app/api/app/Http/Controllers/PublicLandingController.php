<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Post;
use Illuminate\Support\Facades\DB;

/**
 * Data PUBLIK (tanpa auth) untuk halaman landing "super app":
 * beberapa postingan publik terbaru, pengumuman, dan produk marketplace.
 * Sengaja hanya konten yang aman ditampilkan ke pengunjung anonim.
 */
class PublicLandingController extends Controller
{
    public function index()
    {
        return response()->json([
            'stats' => $this->stats(),
            'posts' => $this->recentPublicPosts(),
            'announcements' => $this->recentAnnouncements(),
            'products' => $this->recentProducts(),
        ]);
    }

    private function stats(): array
    {
        $members = \App\Models\User::whereNotNull('email_verified_at')->count();
        $posts = Post::where('is_archived', false)
            ->where('is_draft', false)
            ->whereNull('moderated_at')
            ->count();
        $products = 0;
        try {
            $products = \App\Marketplace\Models\Product::where('is_active', true)->count();
        } catch (\Throwable $e) {
            $products = 0;
        }

        return [
            'members' => (int) $members,
            'posts' => (int) $posts,
            'products' => (int) $products,
        ];
    }

    private function bestThumb($post): ?string
    {
        $v = is_array($post->media_variants ?? null) ? $post->media_variants : [];
        if (! empty($v['thumbnail']['url']) && is_string($v['thumbnail']['url'])) {
            return $v['thumbnail']['url'];
        }

        return $post->thumbnail_url ?: null;
    }

    private function recentPublicPosts()
    {
        return Post::with(['user:user_id,username,full_name,profile_picture_url,profile_picture_thumb_url,is_verified'])
            ->where('is_archived', false)
            ->where('is_draft', false)
            ->whereNull('moderated_at')
            ->whereHas('user', fn ($q) => $q->where('is_private', false)->whereNotNull('email_verified_at'))
            ->whereNotNull('media_url')
            ->inRandomOrder()
            ->take(12)
            ->get()
            ->map(fn ($p) => [
                'id' => (int) $p->post_id,
                'caption' => $p->caption ? mb_strimwidth($p->caption, 0, 120, '…') : '',
                'media_url' => $p->media_url,
                'thumbnail_url' => $this->bestThumb($p),
                'is_video' => (bool) $p->is_video,
                'user' => $p->user ? [
                    'username' => $p->user->username,
                    'full_name' => $p->user->full_name,
                    'avatar_url' => $p->user->profile_picture_thumb_url ?? $p->user->profile_picture_url,
                    'is_verified' => (bool) $p->user->is_verified,
                ] : null,
            ])
            ->values();
    }

    private function recentAnnouncements()
    {
        return Announcement::with(['creator:user_id,full_name,username'])
            ->orderByDesc('pinned')
            ->latest()
            ->take(4)
            ->get()
            ->map(fn ($a) => [
                'id' => (int) $a->id,
                'title' => $a->title,
                'excerpt' => $a->content ? mb_strimwidth(strip_tags($a->content), 0, 160, '…') : '',
                'image_url' => $a->image_url,
                'pinned' => (bool) $a->pinned,
                'created_at' => optional($a->created_at)->toIso8601String(),
                'author' => $a->creator->full_name ?? $a->creator->username ?? 'Portal SI',
            ])
            ->values();
    }

    private function recentProducts()
    {
        // Produk marketplace (modul tergabung di API utama). Aman gagal bila tabel belum ada.
        try {
            return \App\Marketplace\Models\Product::where('is_active', true)
                ->latest()
                ->take(8)
                ->get()
                ->map(fn ($p) => [
                    'id' => (int) $p->id,
                    'name' => $p->name,
                    'slug' => $p->slug,
                    'price' => (int) $p->price,
                    'original_price' => (int) ($p->original_price ?? 0),
                    'image' => $p->image,
                    'rating' => (float) ($p->rating ?? 0),
                ])
                ->values();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
