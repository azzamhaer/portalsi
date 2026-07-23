<?php

namespace App\Http\Controllers;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Moderasi postingan — hanya untuk Developer terverifikasi (dijaga middleware 'moderator').
 *
 * Take-down memakai soft take-down: kolom moderated_at diisi, postingan disaring dari SEMUA
 * permukaan (feed/explore/reels/pencarian/profil orang lain), tapi barisnya tetap tersimpan
 * ~30 hari. Pemilik masih bisa melihat postingannya (ditandai) dan hanya bisa menghapusnya.
 */
class ModerationController extends Controller
{
    /** Alasan bebas dibatasi panjangnya; template dikirim dari klien sebagai teks jadi. */
    public function moderate(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $post = Post::findOrFail($id);

        if ($post->moderated_at !== null) {
            return response()->json(['message' => 'Postingan ini sudah dimoderasi.'], 422);
        }

        $post->moderated_at = now();
        $post->moderation_reason = trim($request->input('reason'));
        $post->moderated_by = Auth::id();
        $post->moderation_ack_at = null; // reset agar pemilik melihat modal lagi
        $post->save();

        // Beri tahu pemilik (kecuali moderator memoderasi postingannya sendiri).
        if ($post->user_id !== Auth::id()) {
            $notif = Notification::createFor($post->user_id, [
                'type' => 'post_moderated',
                'related_user_id' => Auth::id(),
                'related_post_id' => (int) $post->post_id,
                'message' => $post->moderation_reason,
                'is_read' => false,
            ]);
            if ($notif) {
                broadcast(new NotificationCreated($notif));
            }
        }

        return response()->json([
            'message' => 'Postingan dimoderasi & diturunkan.',
            'post_id' => (int) $post->post_id,
        ]);
    }

    /** Batalkan moderasi (pulihkan postingan). Hanya moderator. */
    public function cancel($id)
    {
        $post = Post::findOrFail($id);

        if ($post->moderated_at === null) {
            return response()->json(['message' => 'Postingan ini tidak sedang dimoderasi.'], 422);
        }

        $post->moderated_at = null;
        $post->moderation_reason = null;
        $post->moderated_by = null;
        $post->moderation_ack_at = null;
        $post->save();

        // Hapus notifikasi moderasi yang menggantung di pemilik.
        Notification::where('recipient_id', $post->user_id)
            ->where('related_post_id', $post->post_id)
            ->where('type', 'post_moderated')
            ->delete();

        return response()->json(['message' => 'Moderasi dibatalkan; postingan dipulihkan.']);
    }

    /**
     * Daftar postingan yang sedang dimoderasi (untuk panel admin).
     * Menyertakan detail: pemilik, alasan, moderator, kapan, sisa hari retensi.
     */
    public function index(Request $request)
    {
        $perPage = max(1, min(50, (int) $request->input('per_page', 20)));

        $paginated = Post::whereNotNull('moderated_at')
            ->with(['user:user_id,username,full_name,profile_picture_url,profile_picture_thumb_url'])
            ->orderByDesc('moderated_at')
            ->paginate($perPage);

        $moderatorIds = $paginated->getCollection()->pluck('moderated_by')->filter()->unique();
        $moderators = \App\Models\User::whereIn('user_id', $moderatorIds)
            ->get(['user_id', 'username'])->keyBy('user_id');

        $data = $paginated->getCollection()->map(function ($post) use ($moderators) {
            $moderatedAt = $post->moderated_at;
            $retentionEndsAt = $moderatedAt ? $moderatedAt->copy()->addDays(30) : null;

            return [
                'post_id' => (int) $post->post_id,
                'caption' => $post->caption,
                'media_url' => $post->media_url,
                'thumbnail_url' => $post->thumbnail_url,
                'is_video' => (bool) $post->is_video,
                'moderated_at' => $moderatedAt?->toIso8601String(),
                'reason' => $post->moderation_reason,
                'retention_ends_at' => $retentionEndsAt?->toIso8601String(),
                'days_left' => $retentionEndsAt ? max(0, (int) now()->diffInDays($retentionEndsAt, false)) : null,
                'moderated_by' => $post->moderated_by ? [
                    'user_id' => (int) $post->moderated_by,
                    'username' => $moderators[$post->moderated_by]->username ?? '—',
                ] : null,
                'owner' => $post->user ? [
                    'user_id' => (int) $post->user->user_id,
                    'username' => $post->user->username,
                    'full_name' => $post->user->full_name,
                    'profile_picture_thumb_url' => $post->user->profile_picture_thumb_url,
                ] : null,
            ];
        });

        return response()->json([
            'data' => $data,
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
        ]);
    }

    /**
     * Pemberitahuan untuk PEMILIK: postingan miliknya yang dimoderasi & belum ia tutup modalnya.
     * Dipakai untuk modal yang muncul saat login/refresh.
     */
    public function notices()
    {
        $userId = Auth::id();

        $posts = Post::where('user_id', $userId)
            ->whereNotNull('moderated_at')
            ->whereNull('moderation_ack_at')
            ->orderByDesc('moderated_at')
            ->get(['post_id', 'caption', 'thumbnail_url', 'media_url', 'is_video', 'moderated_at', 'moderation_reason']);

        return response()->json([
            'notices' => $posts->map(fn ($p) => [
                'post_id' => (int) $p->post_id,
                'caption' => $p->caption,
                'thumbnail_url' => $p->thumbnail_url,
                'media_url' => $p->media_url,
                'is_video' => (bool) $p->is_video,
                'moderated_at' => $p->moderated_at?->toIso8601String(),
                'reason' => $p->moderation_reason,
            ]),
        ]);
    }

    /** Pemilik menutup modal pemberitahuan untuk satu postingan (tidak muncul lagi). */
    public function acknowledge($id)
    {
        $post = Post::where('post_id', $id)->where('user_id', Auth::id())->first();
        if (! $post) {
            return response()->json(['message' => 'Postingan tidak ditemukan.'], 404);
        }
        $post->moderation_ack_at = now();
        $post->save();

        return response()->json(['message' => 'Pemberitahuan ditutup.']);
    }
}
