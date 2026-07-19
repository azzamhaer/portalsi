<?php

namespace App\Http\Controllers;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\Post;
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
                'u.profile_picture_url as inviter_avatar'
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
}
