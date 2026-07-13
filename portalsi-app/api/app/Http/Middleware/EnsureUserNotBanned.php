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
            $user->currentAccessToken()?->delete();

            return response()->json([
                'message' => 'Akun ini sedang diblokir dari Portal SI.',
                'ban_reason' => $user->ban_reason,
            ], 403);
        }

        return $next($request);
    }
}
