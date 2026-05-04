<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserIsCashier
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Admin boleh akses semua endpoint kasir
        if ($request->user()->isAdmin() || $request->user()->isCashier()) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Akses ditolak.',
        ], 403);
    }
}