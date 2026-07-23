<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hanya Developer (role 'dev') yang SUDAH terverifikasi yang boleh memoderasi postingan.
 * Ini gerbang keamanan fitur moderasi — jangan longgar.
 */
class EnsureModerator
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || (int) $user->is_verified !== 1 || $user->role !== 'dev') {
            return response()->json([
                'message' => 'Hanya moderator (Developer terverifikasi) yang dapat mengakses fitur ini.',
            ], 403);
        }

        if ((bool) ($user->is_banned ?? false)) {
            return response()->json(['message' => 'Akun ini sedang diblokir.'], 403);
        }

        return $next($request);
    }
}
