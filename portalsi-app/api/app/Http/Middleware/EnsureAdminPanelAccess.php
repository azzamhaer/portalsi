<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminPanelAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || (int) $user->is_verified !== 1) {
            return response()->json([
                'message' => 'Akses admin panel hanya untuk akun Portal SI yang terverifikasi.',
            ], 403);
        }

        if ((bool) ($user->is_banned ?? false)) {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'message' => 'Akun ini sedang diblokir dari Portal SI.',
            ], 403);
        }

        return $next($request);
    }
}
