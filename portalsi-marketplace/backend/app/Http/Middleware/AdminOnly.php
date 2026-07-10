<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminOnly
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'ADMIN') {
            return response()->json(['message' => 'Hanya admin yang boleh mengakses'], 403);
        }
        return $next($request);
    }
}
