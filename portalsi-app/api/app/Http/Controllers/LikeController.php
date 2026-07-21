<?php

namespace App\Http\Controllers;

use App\Models\Like;
use App\Models\Post;
use App\Models\Notification;
use App\Events\LikeCreated;
use App\Events\NotificationCreated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\Follow; // pastikan ada model Follow

class LikeController extends Controller
{
    public function toggle(Request $request, $post_id)
    {
        $user_id = Auth::id();

        if (!$user_id) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'User ID not found. Are you logged in and sending token correctly?'
            ], 401);
        }

        $post = Post::find($post_id);
        if (!$post) {
            return response()->json(['error' => 'Post not found'], 404);
        }

        if (!$post->user) {
            return response()->json(['error' => 'Post owner not found'], 404);
        }

        $like = Like::where('user_id', $user_id)
            ->where('post_id', $post_id)
            ->first();

        // Klien boleh mengirim state yang DIINGINKAN (`liked=true/false`) agar operasi
        // idempoten: dua request beruntun (mis. double-tap + klik) tidak lagi saling
        // membatalkan sehingga UI dan DB tidak pernah desinkron.
        $desired = $request->input('liked');
        if ($desired !== null) {
            $desired = filter_var($desired, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }
        $shouldLike = $desired === null ? ! $like : $desired;

        if (! $shouldLike) {
            if ($like) {
                $like->delete();
            }

            return response()->json([
                'message' => 'Post unliked',
                'liked' => false,
                'likes_count' => $post->likes()->count(),
            ]);
        }

        if ($like) {
            // Sudah disukai & memang diminta tetap disukai → tidak ada perubahan.
            return response()->json([
                'message' => 'Post liked',
                'liked' => true,
                'likes_count' => $post->likes()->count(),
            ]);
        }

        // Belum disukai → buat like baru + notifikasi.
        {
            $like = Like::create([
                'user_id' => $user_id,
                'post_id' => $post_id,
            ]);

            // Broadcast like event.
            // This is for real-time updates on the post's like count.
            broadcast(new LikeCreated($like))->toOthers();

            // Kirim notifikasi HANYA jika user bukan pemilik post
            if ($post->user_id != $user_id) {
                // Cek notifikasi terakhir untuk like
                $lastNotif = Notification::where('recipient_id', $post->user_id)
                    ->where('related_user_id', $user_id)
                    ->where('related_post_id', $post_id)
                    ->where('type', 'like')
                    ->latest()
                    ->first();

                $allowNotify = true;

                if ($lastNotif) {
                    $lastCreated = Carbon::parse($lastNotif->created_at);
                    $diff = now()->diffInSeconds($lastCreated);
                    if ($diff < 60) {
                        $allowNotify = false;
                    }
                }

                if ($allowNotify) {
                    $notification = Notification::createFor($post->user_id, [
                        'type' => 'like',
                        'related_user_id' => $user_id,
                        'related_post_id' => $post_id,
                        'is_read' => false,
                    ]);

                    // Broadcast (real-time bell) hanya bila tidak ditekan preferensi penerima.
                    if ($notification) {
                        broadcast(new NotificationCreated($notification));
                    }
                }
            }

            return response()->json([
                'message' => 'Post liked',
                'liked' => true,
                'likes_count' => $post->likes()->count(),
            ]);
        }
    }

public function index($post_id)
{
    $authUser = Auth::user();

    // Ambil ID semua user yang di-follow oleh current user (status accepted saja)
    $followingIds = $authUser 
        ? $authUser->following()->wherePivot('status', 'accepted')->pluck('users.user_id')->toArray()
        : [];

    $likes = Like::where('post_id', $post_id)
        ->with('user') // pastikan Like model ada relasi user()
        ->get()
        // Baris like yang usernya sudah tidak ada (mis. sisa data lama) dilewati —
        // dulu ikut terkirim sebagai user null dan menjatuhkan seluruh halaman post.
        ->filter(fn ($like) => $like->user !== null)
        ->map(function ($like) use ($followingIds, $authUser) {
            return [
                'id' => $like->like_id,
                'post_id' => $like->post_id,
                'user' => $like->user,
                // Sebagian baris lama tidak punya timestamp; kirim string kosong, bukan null.
                'created_at' => optional($like->created_at)->toIso8601String() ?? '',
                'is_following_status' => $authUser
                    ? in_array($like->user_id, $followingIds)
                    : false,
            ];
        })
        ->values();

    return response()->json($likes);
}



}
