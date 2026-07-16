<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserNotBanned
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (bool) ($user->is_banned ?? false)) {
            // Jangan hapus token di sini: akun diblokir tetap butuh sesi aktif untuk
            // mengakses halaman "akun diblokir" dan mengirim banding (rute /appeals).
            return response()->json([
                'message' => 'Akun ini sedang diblokir dari Portal SI.',
                'banned' => true,
                'ban_reason' => $user->ban_reason,
            ], 403);
        }

        return $next($request);
    }
}
