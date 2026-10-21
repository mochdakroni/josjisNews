<?php
/**
 * Front controller JosjisNews (PHP built-in server / Apache / Nginx try_files).
 *
 *   php -S 0.0.0.0:8080 router.php
 *
 * Semua permintaan masuk lewat sini. Berkas di disk TIDAK pernah disajikan langsung
 * kecuali lewat daftar putih di bawah (mencegah kebocoran .env, SQLite, kode sumber).
 */

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/helpers.php';

$uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$uri = preg_replace('#/+#', '/', $uri);
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if (strpos($uri, "\0") !== false || strpos($uri, '..') !== false) {
    http_response_code(400);
    exit('Bad request');
}

// Tolak Host yang tidak dikenal bila ALLOWED_HOSTS diisi (cegah host-header poisoning pada canonical/sitemap/RSS).
$allowedHosts = env('ALLOWED_HOSTS');
if ($allowedHosts) {
    $reqHost = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    $okHosts = array_map('trim', explode(',', strtolower($allowedHosts)));
    if (!in_array($reqHost, $okHosts, true)) {
        http_response_code(400);
        exit('Invalid host');
    }
}

/** Content-Security-Policy: batasi sumber skrip/gaya/frame, larang plugin, base-uri, dan framing. */
function csp_header($admin = false)
{
    $script = "'self' 'unsafe-inline' https://cdn.tailwindcss.com" . ($admin ? ' https://cdn.ckeditor.com' : '');
    $style = "'self' 'unsafe-inline' https://fonts.googleapis.com" . ($admin ? ' https://cdn.ckeditor.com' : '');
    $frames = "'self' https://drive.google.com https://docs.google.com https://www.youtube.com https://www.youtube-nocookie.com https://player.vimeo.com";
    return "default-src 'self'; script-src $script; style-src $style; font-src 'self' https://fonts.gstatic.com data:; "
        . "img-src 'self' data: blob: https:; media-src 'self' https: blob:; frame-src $frames; connect-src 'self'"
        . ($admin ? ' https://cdn.ckeditor.com' : '') . "; object-src 'none'; base-uri 'self'; form-action 'self'; "
        . "frame-ancestors " . ($admin ? "'none'" : "'self'");
}

// Header keamanan umum untuk semua respons.
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
if (is_https()) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

try {
    // ---- API
    if (str_starts_with($uri, '/api/')) {
        header('Cache-Control: no-store');
        $cors = env('CORS_ORIGIN');
        if ($cors) {
            header('Access-Control-Allow-Origin: ' . $cors);
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization');
            header('Vary: Origin');
        }
        if ($method === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
        require_once __DIR__ . '/src/api.php';
        header('Content-Type: application/json; charset=utf-8');
        handle_api(rtrim($uri, '/') ?: '/', $method);
        exit;
    }

    if ($method !== 'GET' && $method !== 'HEAD') {
        http_response_code(405);
        header('Allow: GET, HEAD');
        exit('Method not allowed');
    }

    // ---- Media unggahan
    if (str_starts_with($uri, '/storage/')) {
        require_once __DIR__ . '/src/media.php';
        if (serve_upload($uri) === false) {
            http_response_code(404);
            exit('Not found');
        }
        exit;
    }

    // ---- Panel admin (statis, dilindungi lewat API)
    if ($uri === '/admin' || $uri === '/admin.html') {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Frame-Options: DENY');
        header('X-Robots-Tag: noindex, nofollow');
        header('Content-Security-Policy: ' . csp_header(true));
        readfile(__DIR__ . '/admin.html');
        exit;
    }

    // ---- Halaman publik
    require_once __DIR__ . '/src/seo.php';
    header('Content-Security-Policy: ' . csp_header(false));

    if ($uri === '/' || $uri === '/preview.html' || $uri === '/index.html') {
        return page_home();
    }
    if ($uri === '/robots.txt') {
        return render_robots();
    }
    if ($uri === '/sitemap.xml') {
        return render_sitemap();
    }
    if ($uri === '/rss.xsl') {
        return render_rss_xsl();
    }
    if ($uri === '/rss.xml' || $uri === '/feed') {
        return render_rss();
    }
    if (preg_match('#^/kanal/([a-z0-9\-]+)/rss\.xml$#', $uri, $m)) {
        return render_rss($m[1]);
    }
    if (preg_match('#^/artikel/([a-z0-9\-]+)/?$#', $uri, $m)) {
        return page_article($m[1]);
    }
    if (preg_match('#^/kanal/([a-z0-9\-]+)/?$#', $uri, $m)) {
        return page_category($m[1]);
    }
    if (preg_match('#^/halaman/([a-z0-9\-]+)/?$#', $uri, $m)) {
        return page_static($m[1]);
    }
    if ($uri === '/favicon.ico' || $uri === '/favicon.svg') {
        header('Content-Type: image/svg+xml');
        header('Cache-Control: public, max-age=86400');
        echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#e11d48"/><text x="32" y="46" font-family="Georgia,serif" font-size="40" font-weight="900" text-anchor="middle" fill="#fff">J</text></svg>';
        exit;
    }

    page_not_found();
} catch (Throwable $e) {
    error_log('[josjis] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (str_starts_with($uri, '/api/')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'Ana kesalahan ing server. Coba maneh mengko.']);
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Ana kesalahan ing server.';
    }
}
