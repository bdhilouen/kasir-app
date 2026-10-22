<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Kalau responnya berupa file atau stream, skip aja biar ga error
        if (method_exists($response, 'header')) {

            // Fix HSTS (Strict-Transport-Security)
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

            // Re-apply X-Content-Type buat endpoint API
            $response->header('X-Content-Type-Options', 'nosniff');

            // Hapus header X-Powered-By bawaan PHP (Fix Server Leaks Info)
            if (function_exists('header_remove')) {
                header_remove('X-Powered-By');
            }
            $response->headers->remove('X-Powered-By');
        }

        return $response;
    }
}
