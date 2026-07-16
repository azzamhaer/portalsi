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
            // Akun diblokir tetap boleh login & MEMBACA (GET), supaya bisa melihat
            // pemberitahuan ban dan mengajukan banding. Aksi MENULIS diblokir.
            if (! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
                return response()->json([
                    'message' => 'Akun kamu sedang diblokir, sehingga aksi ini tidak dapat dilakukan.',
                    'banned' => true,
                    'ban_reason' => $user->ban_reason,
                ], 403);
            }
        }

        return $next($request);
    }
}
