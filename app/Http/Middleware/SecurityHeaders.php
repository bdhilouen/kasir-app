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

        // Fix 1: X-Content-Type-Options (Paling gampang, mencegah MIME sniffing)
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Fix 2: Anti-clickjacking (Mencegah web lu dibungkus iframe oleh hacker)
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Fix 3: Content Security Policy (CSP)
        // Catatan: Settingan ini ngizinin script dari domain sendiri. 
        // Kalau React lu butuh asset dari luar, aturannya perlu ditambahin.
        $response->headers->set('Content-Security-Policy', "default-src 'self' 'unsafe-inline' 'unsafe-eval';");

        return $response;
    }
}
