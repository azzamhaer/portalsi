<?php

namespace App\Http\Controllers;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Undangan co-author postingan: terima / tolak, dan daftar undangan pending.
 */
class CollaboratorController extends Controller
{
    /** Daftar undangan kolaborasi yang menunggu keputusan user. */
    public function pending(Request $request)
    {
        $authId = Auth::id();

        $rows = DB::table('post_collaborators as pc')
            ->join('posts as p', 'p.post_id', '=', 'pc.post_id')
            ->join('users as u', 'u.user_id', '=', 'p.user_id')
            ->where('pc.user_id', $authId)
            ->where('pc.status', 'pending')
            ->orderByDesc('pc.created_at')
            ->select(
                'pc.post_id',
                'p.caption',
                'p.media_url',
                'p.thumbnail_url',
                'p.is_video',
                'pc.created_at as invited_at',
                'u.user_id as inviter_id',
                'u.username as inviter_username',
                'u.full_name as inviter_name',
                'u.profile_picture_url as inviter_avatar',
                'u.profile_picture_thumb_url as inviter_avatar_thumb'
            )
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $rows->map(fn ($r) => [
                'post_id' => (int) $r->post_id,
                'caption' => $r->caption,
                'media_url' => $r->media_url,
                'thumbnail_url' => $r->thumbnail_url,
                'is_video' => (bool) $r->is_video,
                'invited_at' => $r->invited_at,
                'inviter' => [
                    'user_id' => (int) $r->inviter_id,
                    'username' => $r->inviter_username,
                    'full_name' => $r->inviter_name,
                    'profile_picture_url' => $r->inviter_avatar,
                    'profile_picture_thumb_url' => $r->inviter_avatar_thumb,
                ],
            ]),
        ]);
    }

    public function accept($postId)
    {
        $authId = Auth::id();
        $exists = DB::table('post_collaborators')
            ->where('post_id', $postId)->where('user_id', $authId)->where('status', 'pending')->exists();
        if (! $exists) {
            return response()->json(['message' => 'Tidak ada undangan kolaborasi yang menunggu.'], 404);
        }

        DB::table('post_collaborators')
            ->where('post_id', $postId)->where('user_id', $authId)
            ->update(['status' => 'accepted', 'responded_at' => now(), 'updated_at' => now()]);

        // Beri tahu pemilik post.
        $post = Post::find($postId);
        if ($post) {
            $notif = Notification::create([
                'recipient_id' => $post->user_id,
                'type' => 'collab_accepted',
                'related_user_id' => $authId,
                'related_post_id' => (int) $postId,
                'is_read' => false,
                'created_at' => now(),
            ]);
            if ($notif) {
                broadcast(new NotificationCreated($notif));
            }
        }

        return response()->json(['message' => 'Kolaborasi diterima.']);
    }

    /** Daftar kolaborator sebuah post (owner melihat status pending; lainnya hanya accepted). */
    public function list($postId)
    {
        $post = Post::find($postId);
        if (! $post) {
            return response()->json(['message' => 'Post tidak ditemukan.'], 404);
        }
        $isOwner = $post->user_id === Auth::id();

        $rows = DB::table('post_collaborators as pc')
            ->join('users as u', 'u.user_id', '=', 'pc.user_id')
            ->where('pc.post_id', $postId)
            ->when(! $isOwner, fn ($q) => $q->where('pc.status', 'accepted'))
            ->select('u.user_id', 'u.username', 'u.full_name', 'u.profile_picture_url', 'u.profile_picture_thumb_url', 'pc.status')
            ->get();

        return response()->json([
            'owner_id' => $post->user_id,
            'is_owner' => $isOwner,
            'collaborators' => $rows->map(fn ($r) => [
                'user_id' => (int) $r->user_id,
                'username' => $r->username,
                'full_name' => $r->full_name,
                'profile_picture_url' => $r->profile_picture_url,
                'profile_picture_thumb_url' => $r->profile_picture_thumb_url,
                'status' => $r->status,
            ]),
        ]);
    }

    /** Owner menambahkan kolaborator baru (undangan pending + notifikasi). */
    public function add($postId, Request $request)
    {
        $post = Post::find($postId);
        if (! $post) {
            return response()->json(['message' => 'Post tidak ditemukan.'], 404);
        }
        if ($post->user_id !== Auth::id()) {
            return response()->json(['message' => 'Hanya pembuat post yang dapat menambah kolaborator.'], 403);
        }
        $request->validate([
            'collaborators' => 'required|array|max:5',
            'collaborators.*' => 'integer',
        ]);

        $current = DB::table('post_collaborators')->where('post_id', $postId)->count();
        $ids = collect($request->input('collaborators'))
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($id) => $id > 0 && $id !== (int) Auth::id())
            ->unique()
            ->take(max(0, 5 - $current));

        $valid = User::whereIn('user_id', $ids)->pluck('user_id');
        foreach ($valid as $uid) {
            $post->collaborators()->syncWithoutDetaching([$uid => ['status' => 'pending']]);
            $notif = Notification::create([
                'recipient_id' => $uid,
                'type' => 'collab_invite',
                'related_user_id' => Auth::id(),
                'related_post_id' => (int) $postId,
                'is_read' => false,
                'created_at' => now(),
            ]);
            if ($notif) {
                broadcast(new NotificationCreated($notif));
            }
        }

        return response()->json(['message' => 'Undangan kolaborasi dikirim.']);
    }

    /** Owner menghapus kolaborator (pending maupun accepted). */
    public function removeByOwner($postId, $userId)
    {
        $post = Post::find($postId);
        if (! $post) {
            return response()->json(['message' => 'Post tidak ditemukan.'], 404);
        }
        if ($post->user_id !== Auth::id()) {
            return response()->json(['message' => 'Hanya pembuat post yang dapat menghapus kolaborator.'], 403);
        }
        DB::table('post_collaborators')->where('post_id', $postId)->where('user_id', $userId)->delete();
        Notification::where('recipient_id', $userId)
            ->where('related_post_id', $postId)
            ->where('type', 'collab_invite')
            ->delete();

        return response()->json(['message' => 'Kolaborator dihapus.']);
    }

    public function reject($postId)
    {
        $authId = Auth::id();
        $deleted = DB::table('post_collaborators')
            ->where('post_id', $postId)->where('user_id', $authId)->where('status', 'pending')
            ->delete();
        if (! $deleted) {
            return response()->json(['message' => 'Tidak ada undangan kolaborasi yang menunggu.'], 404);
        }

        // Bersihkan notifikasi undangan terkait.
        Notification::where('recipient_id', $authId)
            ->where('related_post_id', $postId)
            ->where('type', 'collab_invite')
            ->delete();

        return response()->json(['message' => 'Undangan kolaborasi ditolak.']);
    }

    /**
     * Kolaborator membatalkan kolaborasinya sendiri pada sebuah post yang SUDAH diterima.
     *
     * Barisnya DIHAPUS (bukan dikembalikan ke pending), sehingga di daftar kolaborator milik
     * pemilik post orang ini benar-benar hilang. Bila ingin berkolaborasi lagi, pemilik post
     * tinggal menambahkannya kembali sebagai kolaborator.
     */
    public function leave($postId)
    {
        $authId = Auth::id();
        $deleted = DB::table('post_collaborators')
            ->where('post_id', $postId)
            ->where('user_id', $authId)
            ->where('status', 'accepted')
            ->delete();

        if (! $deleted) {
            return response()->json(['message' => 'Anda bukan kolaborator postingan ini.'], 404);
        }

        return response()->json(['message' => 'Kolaborasi dibatalkan.']);
    }
}
