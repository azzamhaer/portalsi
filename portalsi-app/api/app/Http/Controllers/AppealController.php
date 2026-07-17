<?php

namespace App\Http\Controllers;

use App\Models\Appeal;
use Illuminate\Http\Request;

class AppealController extends Controller
{
    /**
     * Banded user mengirim banding. Hanya untuk akun yang sedang diblokir.
     * Rute ini di luar middleware notBanned agar user diblokir tetap bisa mengakses.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (! (bool) ($user->is_banned ?? false)) {
            return response()->json(['message' => 'Akun kamu tidak sedang diblokir.'], 422);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'min:10', 'max:3000'],
        ]);

        // Kalau masih ada banding PENDING, perbarui isinya (jangan tolak) — supaya user
        // tidak pernah "stuck" dan banding terbaru selalu terlihat admin.
        $pending = Appeal::where('user_id', $user->user_id)->where('status', 'pending')->latest()->first();
        if ($pending) {
            $pending->message = $validated['message'];
            $pending->save();

            return response()->json([
                'message' => 'Banding kamu diperbarui. Tim akan meninjau akunmu.',
                'appeal' => $pending,
            ], 200);
        }

        $appeal = Appeal::create([
            'user_id' => $user->user_id,
            'message' => $validated['message'],
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Banding terkirim. Tim akan meninjau akunmu.',
            'appeal' => $appeal,
        ], 201);
    }

    /**
     * User melihat daftar banding miliknya beserta statusnya.
     */
    public function mine(Request $request)
    {
        $appeals = Appeal::where('user_id', $request->user()->user_id)
            ->latest()
            ->get();

        return response()->json(['appeals' => $appeals]);
    }
}
