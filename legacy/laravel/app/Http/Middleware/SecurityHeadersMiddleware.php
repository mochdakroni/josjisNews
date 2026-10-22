<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware Keamanan HTTP Headers (OWASP Best Practice)
 * Melindungi API & CMS dari Clickjacking, MIME-Sniffing, XSS, dan kebocoran Referrer.
 */
class SecurityHeadersMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Cegah MIME-sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Cegah Clickjacking di dalam iframe pihak ketiga
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Batasi informasi Referrer saat navigasi lintas domain
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Nonaktifkan akses fitur hardware sensitif browser
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=(), usb=()'
        );

        // HSTS pada koneksi HTTPS production
        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        // Hapus header X-Powered-By agar versi PHP tidak terekspos
        $response->headers->remove('X-Powered-By');

        return $response;
    }
}
