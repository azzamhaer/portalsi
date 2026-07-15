<?php

namespace App\Marketplace\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePortalSiSession
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user?->portal_user_id || ! $user->portal_access_token) {
            $user?->currentAccessToken()?->delete();

            return response()->json([
                'message' => 'Sesi marketplace lama sudah tidak berlaku. Silakan login ulang dengan akun Portal SI.',
            ], 401);
        }

        return $next($request);
    }
}
