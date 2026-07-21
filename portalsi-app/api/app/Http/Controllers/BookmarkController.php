<?php

namespace App\Http\Controllers;

use App\Models\Bookmark;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookmarkController extends Controller
{
    // Simpan bookmark
    public function store($postId)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $post = Post::findOrFail($postId);

        $bookmark = Bookmark::firstOrCreate([
            'user_id' => $user->user_id,   // 👈 pakai user_id
            'post_id' => $post->post_id,   // 👈 pakai post_id
        ]);

        return response()->json([
            'message' => 'Post bookmarked successfully',
            'bookmark' => $bookmark
        ]);
    }

    // Hapus bookmark
    public function destroy($postId)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $post = Post::findOrFail($postId);

        Bookmark::where('user_id', $user->user_id)   // 👈 user_id
            ->where('post_id', $post->post_id)       // 👈 post_id
            ->delete();

        return response()->json([
            'message' => 'Bookmark removed successfully',
        ]);
    }

    // Lihat semua bookmark user
    public function index()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // Privasi dicek ULANG saat menampilkan, bukan hanya saat menyimpan. Sebuah post
        // bisa berubah setelah di-bookmark: pemiliknya menjadikan akunnya privat, atau
        // post-nya diarsipkan/dikembalikan jadi draft. Tanpa penyaringan ini, bookmark
        // menjadi celah untuk terus melihat konten yang seharusnya sudah tertutup.
        $allowedPrivateIds = $user->following()
            ->wherePivot('status', 'accepted')
            ->pluck('users.user_id')
            ->toArray();
        $allowedPrivateIds[] = $user->user_id;

        $bookmarks = $user->bookmarkedPosts()
            ->with('user')
            ->where('is_archived', false)
            ->where('is_draft', false)
            ->whereHas('user', function ($u) use ($allowedPrivateIds) {
                $u->where(function ($w) use ($allowedPrivateIds) {
                    $w->where('is_private', false)
                        ->orWhereIn('users.user_id', $allowedPrivateIds);
                });
            })
            ->latest()
            ->get();

        return response()->json($bookmarks);
    }
}
