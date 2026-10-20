<?php
/**
 * Front Controller & Live SQLite Database REST API Router (/api/v1/*)
 * Menghubungkan preview.html dan admin.html secara REAL-TIME ke database/database.sqlite
 */

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ERROR | E_PARSE);
if (ob_get_level() > 0) {
    ob_clean();
}

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// 1. Jika meminta file statis (.html, .css, .js, gambar, video, pdf di /storage/uploads, dll)
$filePath = __DIR__ . $uri;
if ($uri !== '/' && $uri !== '/router.php' && !str_starts_with($uri, '/api/') && is_file($filePath)) {
    return false;
}

// Redirect root '/' ke '/preview.html'
if ($uri === '/') {
    header('Location: /preview.html');
    exit;
}

// 2. Koneksi ke Database SQLite Real (database/database.sqlite)
$dbFile = __DIR__ . '/database/database.sqlite';
if (!file_exists($dbFile)) {
    require_once __DIR__ . '/database/build_sqlite_db.php';
}

$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('PRAGMA foreign_keys = ON;');

// Pastikan tabel advertisements tersedia & slot halaman depan terdaftar
$pdo->exec("
CREATE TABLE IF NOT EXISTS advertisements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title VARCHAR(180) NOT NULL,
    sponsor_name VARCHAR(120) NULL,
    position VARCHAR(50) NOT NULL,
    ad_type VARCHAR(20) NOT NULL DEFAULT 'image',
    image_url VARCHAR(2048) NULL,
    target_url VARCHAR(2048) NULL,
    html_code TEXT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    views_count INTEGER NOT NULL DEFAULT 0,
    clicks_count INTEGER NOT NULL DEFAULT 0,
    start_date DATETIME NULL,
    end_date DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_ads_position_active ON advertisements(position, is_active);
");

if ((int) $pdo->query("SELECT COUNT(*) FROM advertisements")->fetchColumn() === 0) {
    $stmtSeedAd = $pdo->prepare("
        INSERT INTO advertisements (title, sponsor_name, position, ad_type, image_url, target_url, html_code, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $defaultSlots = [
        [
            'Slot Leaderboard Utama Nduwur Omah (970×90)',
            'Lapak Iklan JosjisNews',
            'leaderboard_top',
            'html',
            '',
            'https://josjisnews.com/pasang-iklan',
            '<div class="flex flex-col items-center justify-center py-2 text-center"><span class="text-[10px] font-bold uppercase tracking-[0.2em] text-brand-600">SPONSOR • LEADERBOARD UTAMA (970×90)</span><p class="mt-1 text-xs font-semibold text-zinc-700 dark:text-zinc-200">Pasang Spanduk Usahamu utawa Instansimu ning Panggonan Paling Strategis Ngarep Dewe JosjisNews</p></div>',
            1,
        ],
        [
            'Slot In-Feed Native Ngisor Kabar Anyar',
            'Lapak Iklan JosjisNews',
            'infeed_home',
            'html',
            '',
            'https://josjisnews.com/pasang-iklan',
            '<div class="flex flex-col items-center justify-center py-3 text-center"><span class="text-[10px] font-bold uppercase tracking-[0.2em] text-brand-600">IKLAN • IN-FEED NATIVE BANNER</span><p class="mt-1 text-xs font-semibold text-zinc-700 dark:text-zinc-200">Lapak Iklan Nyawiji langsung ning Tengah Daftaran Kabar Utama</p></div>',
            1,
        ],
        [
            'Slot Pinggir Tengen Omah (300×250 Medium Rectangle)',
            'Lapak Iklan JosjisNews',
            'sidebar_home',
            'html',
            '',
            'https://josjisnews.com/pasang-iklan',
            '<div class="flex flex-col items-center justify-center py-6 text-center"><span class="text-[10px] font-bold uppercase tracking-[0.2em] text-brand-600">IKLAN • 300×250</span><p class="mt-2 text-xs font-semibold text-zinc-700 dark:text-zinc-200">Banner Pinggir Tengen (Nempel terus ning Ngisore Paling Rame Diwaca)</p></div>',
            1,
        ],
        [
            'Slot Pinggir Kaca Maca Kabar (300×250)',
            'Lapak Iklan JosjisNews',
            'sidebar_article',
            'html',
            '',
            'https://josjisnews.com/pasang-iklan',
            '<div class="flex flex-col items-center justify-center py-6 text-center"><span class="text-[10px] font-bold uppercase tracking-[0.2em] text-brand-600">IKLAN • PINGGIR ARTIKEL (300×250)</span><p class="mt-2 text-xs font-semibold text-zinc-700 dark:text-zinc-200">Ketok Pas ning Sebelahe Kabar Pas Wong Lagi Asyik Maca</p></div>',
            1,
        ],
    ];
    foreach ($defaultSlots as $slot) {
        $stmtSeedAd->execute($slot);
    }
}

// Pastikan direktori penyimpanan media asli tersedia
$uploadBaseDir = __DIR__ . '/storage/uploads';
foreach (['images', 'videos', 'documents'] as $sub) {
    if (!is_dir($uploadBaseDir . '/' . $sub)) {
        mkdir($uploadBaseDir . '/' . $sub, 0775, true);
    }
}

// Security & CORS Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function relativeTime($datetime = null): string
{
    if (!$datetime) {
        return 'Mentas wae';
    }
    $ts = strtotime((string) $datetime);
    if (!$ts) {
        return 'Mentas wae';
    }
    $diff = max(0, time() - $ts);
    if ($diff < 60) {
        return 'Mentas wae';
    }
    $mins = (int) floor($diff / 60);
    if ($mins < 60) {
        return $mins . ' menit kepungkur';
    }
    $hours = (int) floor($mins / 60);
    if ($hours < 24) {
        return $hours . ' jam kepungkur';
    }
    $days = (int) floor($hours / 24);
    return $days . ' dina kepungkur';
}

function scanUploadedMedia(string $uploadBaseDir): array
{
    $items = [];
    $map = [
        'images' => 'image',
        'videos' => 'video',
        'documents' => 'pdf',
    ];
    foreach ($map as $folder => $type) {
        $dir = $uploadBaseDir . '/' . $folder;
        if (!is_dir($dir)) {
            continue;
        }
        $files = scandir($dir) ?: [];
        foreach ($files as $f) {
            if ($f === '.' || $f === '..' || str_starts_with($f, '.')) {
                continue;
            }
            $full = $dir . '/' . $f;
            if (!is_file($full)) {
                continue;
            }
            $sizeKb = max(1, (int) round(filesize($full) / 1024));
            $items[] = [
                'name' => $f,
                'type' => $type,
                'size' => $sizeKb >= 1024 ? round($sizeKb / 1024, 1) . ' MB' : $sizeKb . ' KB',
                'path' => 'storage/uploads/' . $folder . '/' . $f,
                'url' => '/storage/uploads/' . $folder . '/' . rawurlencode($f),
                'mtime' => filemtime($full) ?: 0,
            ];
        }
    }
    usort($items, function ($a, $b) {
        return $b['mtime'] <=> $a['mtime'];
    });
    return $items;
}

function hydrateArticles(PDO $pdo, array $rows, bool $adminMode = false): array
{
    if (empty($rows)) {
        return [];
    }

    $stmtTags = $pdo->prepare("
        SELECT t.id, t.name, t.slug
        FROM tags t
        INNER JOIN article_tag at ON at.tag_id = t.id
        WHERE at.article_id = ?
    ");

    $result = [];
    foreach ($rows as $row) {
        $stmtTags->execute([$row['id']]);
        $tags = $stmtTags->fetchAll();

        $wordCount = str_word_count(strip_tags((string) $row['content_body']));
        $readingMinutes = max(1, (int) ceil($wordCount / 180));

        $item = [
            'id' => (int) $row['id'],
            'title' => $row['title'],
            'slug' => $row['slug'],
            'excerpt' => $row['excerpt'] ?? '',
            'content_body' => $row['content_body'] ?? '',
            'body' => $row['content_body'] ?? '',
            'featured_image_url' => $row['featured_image'] ?? '',
            'image' => $row['featured_image'] ?? '',
            'caption_image' => $row['caption_image'] ?? '',
            'status' => $row['status'],
            'is_headline' => (bool) $row['is_headline'],
            'headline' => (bool) $row['is_headline'],
            'is_trending' => (bool) $row['is_trending'],
            'views_count' => (int) $row['views_count'],
            'views' => (int) $row['views_count'],
            'reading_time_minutes' => $readingMinutes,
            'published_at' => $row['published_at'],
            'published_rel' => relativeTime($row['published_at'] ?? $row['created_at']),
            'author' => $adminMode
                ? ($row['author_name'] ?? 'Administrator Redaksi')
                : [
                    'id' => (int) $row['user_id'],
                    'name' => $row['author_name'] ?? 'Administrator Redaksi',
                    'role' => $row['author_role'] ?? 'editor',
                ],
            'category' => $adminMode
                ? ($row['category_name'] ?? 'Nasional')
                : [
                    'id' => (int) $row['category_id'],
                    'name' => $row['category_name'] ?? 'Nasional',
                    'slug' => $row['category_slug'] ?? 'nasional',
                ],
            'tags' => $tags,
        ];

        $result[] = $item;
    }

    return $result;
}

function getJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return $_POST;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function getBearerOrCookieToken(): string
{
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+([a-fA-F0-9]{32,128})$/', trim($authHeader), $m)) {
        return $m[1];
    }
    $customHeader = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';
    if (preg_match('/^[a-fA-F0-9]{32,128}$/', trim($customHeader))) {
        return trim($customHeader);
    }
    $cookieToken = $_COOKIE['josjis_admin_session'] ?? '';
    if (preg_match('/^[a-fA-F0-9]{32,128}$/', trim($cookieToken))) {
        return trim($cookieToken);
    }
    return '';
}

function getAuthenticatedAdmin(PDO $pdo)
{
    $token = getBearerOrCookieToken();
    if ($token === '') {
        return null;
    }

    // Bersihkan sesi yang sudah kedaluwarsa (> 24 jam tidak aktif)
    $pdo->prepare("DELETE FROM sessions WHERE last_activity < ?")->execute([time() - 86400]);

    $stmt = $pdo->prepare("
        SELECT s.id AS session_id, u.id, u.name, u.email, u.role
        FROM sessions s
        INNER JOIN users u ON u.id = s.user_id
        WHERE s.id = ?
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $admin = $stmt->fetch();
    if (!$admin) {
        return null;
    }

    // Perbarui waktu aktivitas terakhir
    $pdo->prepare("UPDATE sessions SET last_activity = ? WHERE id = ?")->execute([time(), $token]);

    return [
        'id' => (int) $admin['id'],
        'name' => $admin['name'],
        'email' => $admin['email'],
        'role' => $admin['role'],
        'token' => $token,
    ];
}

function setAdminSessionCookie(string $token, int $expiresTimestamp)
{
    if (PHP_VERSION_ID >= 70300) {
        setcookie('josjis_admin_session', $token, [
            'expires' => $expiresTimestamp,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        setcookie('josjis_admin_session', $token, $expiresTimestamp, '/; samesite=Lax', '', false, true);
    }
}

function requireAdminAuth(PDO $pdo): array
{
    $admin = getAuthenticatedAdmin($pdo);
    if (!$admin) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Sesi login telah berakhir atau Anda sudah logout. Silakan login kembali.',
        ]);
        exit;
    }
    return $admin;
}

$baseArticleSelect = "
    SELECT a.*,
           u.name AS author_name, u.role AS author_role,
           c.name AS category_name, c.slug AS category_slug
    FROM articles a
    LEFT JOIN users u ON u.id = a.user_id
    LEFT JOIN categories c ON c.id = a.category_id
";

try {
    // =========================================================================
    // LOGIN ADMIN: POST /api/v1/admin/login
    // =========================================================================
    if ($uri === '/api/v1/admin/login' && $method === 'POST') {
        $body = getJsonBody();
        $email = trim((string) ($body['email'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        $stmt = $pdo->prepare("SELECT id, name, email, password, role FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, (string) $user['password'])) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Email atau password tidak sesuai.']);
            exit;
        }

        // Buat token sesi baru di tabel sessions SQLite
        $token = bin2hex(random_bytes(32));
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'Browser'), 0, 500);
        $payload = json_encode([
            'user_id' => (int) $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'login_at' => date('c'),
        ]);

        $stmtSession = $pdo->prepare("
            INSERT INTO sessions (id, user_id, ip_address, user_agent, payload, last_activity)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmtSession->execute([$token, (int) $user['id'], $ip, $ua, $payload, time()]);

        setAdminSessionCookie($token, time() + 86400);

        echo json_encode([
            'status' => 'success',
            'token' => $token,
            'user' => [
                'id' => (int) $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
            ],
        ]);
        exit;
    }

    // =========================================================================
    // CEK SESI LOGIN AKTIF: GET /api/v1/admin/me
    // =========================================================================
    if ($uri === '/api/v1/admin/me' && $method === 'GET') {
        $admin = getAuthenticatedAdmin($pdo);
        if (!$admin) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Belum login atau sesi sudah berakhir.']);
            exit;
        }
        echo json_encode([
            'status' => 'success',
            'user' => $admin,
        ]);
        exit;
    }

    // =========================================================================
    // LOGOUT ADMIN (HAPUS SESI DARI DATABASE): POST /api/v1/admin/logout
    // =========================================================================
    if ($uri === '/api/v1/admin/logout' && $method === 'POST') {
        $token = getBearerOrCookieToken();
        if ($token !== '') {
            $pdo->prepare("DELETE FROM sessions WHERE id = ?")->execute([$token]);
        }
        setAdminSessionCookie('', time() - 3600);
        echo json_encode([
            'status' => 'success',
            'message' => 'Berhasil keluar dan sesi login telah dihapus.',
        ]);
        exit;
    }

    // Proteksi seluruh endpoint /api/v1/admin/* agar wajib memiliki sesi login aktif
    if (str_starts_with($uri, '/api/v1/admin/')) {
        requireAdminAuth($pdo);
    }

    // =========================================================================
    // ENDPOINT PUBLIK & ADMIN: GET /api/v1/bootstrap
    // =========================================================================
    if ($uri === '/api/v1/bootstrap' && $method === 'GET') {
        $isAdmin = isset($_GET['mode']) && $_GET['mode'] === 'admin';
        if ($isAdmin) {
            requireAdminAuth($pdo);
        }

        $whereClause = $isAdmin ? '' : "WHERE a.status = 'published'";
        $stmt = $pdo->query("$baseArticleSelect $whereClause ORDER BY a.id DESC");
        $articles = hydrateArticles($pdo, $stmt->fetchAll(), $isAdmin);

        $rawCategories = $pdo->query("
            SELECT c.*,
                   (SELECT COUNT(*) FROM articles a WHERE a.category_id = c.id AND a.status = 'published') AS articles_count
            FROM categories c
            ORDER BY c.id ASC
        ")->fetchAll();

        $categories = array_map(function ($c) {
            return [
                'id' => (int) $c['id'],
                'name' => $c['name'],
                'slug' => $c['slug'],
                'desc' => $c['description'] ?? '',
                'description' => $c['description'] ?? '',
                'active' => (bool) $c['is_active'],
                'is_active' => (int) $c['is_active'],
                'articles_count' => (int) $c['articles_count'],
            ];
        }, $rawCategories);

        $tags = $pdo->query("
            SELECT t.*,
                   (SELECT COUNT(*) FROM article_tag at INNER JOIN articles a ON a.id = at.article_id WHERE at.tag_id = t.id AND a.status = 'published') AS articles_count
            FROM tags t
            ORDER BY articles_count DESC, t.name ASC
        ")->fetchAll();

        $rawUsers = $pdo->query("
            SELECT u.id, u.name, u.email, u.role, u.created_at,
                   (SELECT COUNT(*) FROM articles a WHERE a.user_id = u.id) AS articles_count
            FROM users u
            ORDER BY u.id ASC
        ")->fetchAll();

        $users = array_map(function ($u) {
            return [
                'id' => (int) $u['id'],
                'name' => $u['name'],
                'email' => $u['email'],
                'role' => $u['role'],
                'articles' => (int) $u['articles_count'],
                'articles_count' => (int) $u['articles_count'],
            ];
        }, $rawUsers);

        $totalViews = (int) $pdo->query("SELECT COALESCE(SUM(views_count), 0) FROM articles")->fetchColumn();

        if (!$isAdmin) {
            $pdo->exec("UPDATE advertisements SET views_count = views_count + 1 WHERE is_active = 1");
        }
        $adWhere = $isAdmin ? '' : 'WHERE is_active = 1';
        $rawAds = $pdo->query("SELECT * FROM advertisements $adWhere ORDER BY id ASC")->fetchAll();
        $advertisements = array_map(function ($ad) {
            return [
                'id' => (int) $ad['id'],
                'title' => $ad['title'],
                'sponsor_name' => $ad['sponsor_name'] ?? '',
                'position' => $ad['position'],
                'ad_type' => $ad['ad_type'] ?? 'image',
                'image_url' => $ad['image_url'] ?? '',
                'target_url' => $ad['target_url'] ?? '',
                'html_code' => $ad['html_code'] ?? '',
                'active' => (bool) $ad['is_active'],
                'is_active' => (int) $ad['is_active'],
                'views_count' => (int) $ad['views_count'],
                'clicks_count' => (int) $ad['clicks_count'],
                'start_date' => $ad['start_date'],
                'end_date' => $ad['end_date'],
            ];
        }, $rawAds);

        // Statistik pembaca 7 hari terakhir secara real dari tabel article_views
        $dailyViews = [];
        $dayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
        $stmtDay = $pdo->prepare("SELECT COUNT(*) FROM article_views WHERE date(viewed_at) = ?");
        for ($i = 6; $i >= 0; $i--) {
            $dateStr = date('Y-m-d', strtotime("-{$i} days"));
            $dow = (int) date('w', strtotime($dateStr));
            $stmtDay->execute([$dateStr]);
            $cnt = (int) $stmtDay->fetchColumn();
            $dailyViews[] = [
                'date' => $dateStr,
                'label' => $i === 0 ? 'Hari Ini' : $dayNames[$dow],
                'views' => $cnt,
            ];
        }

        echo json_encode([
            'status' => 'success',
            'data' => [
                'articles' => $articles,
                'categories' => $categories,
                'tags' => $tags,
                'users' => $users,
                'advertisements' => $advertisements,
                'media' => scanUploadedMedia($uploadBaseDir),
                'stats' => [
                    'total_views' => $totalViews,
                    'daily_views' => $dailyViews,
                ],
            ],
        ]);
        exit;
    }

    // =========================================================================
    // GET & POST /api/v1/admin/media (Direktori Media & Upload File Real)
    // =========================================================================
    if ($uri === '/api/v1/admin/media' && $method === 'GET') {
        echo json_encode([
            'status' => 'success',
            'data' => scanUploadedMedia($uploadBaseDir),
        ]);
        exit;
    }

    if ($uri === '/api/v1/admin/media' && $method === 'POST') {
        if (!empty($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['file']['tmp_name'];
            $origName = preg_replace('/[^a-zA-Z0-9._-]/', '-', basename((string) $_FILES['file']['name']));
            $mime = mime_content_type($tmpName) ?: (string) $_FILES['file']['type'];

            $folder = 'images';
            $type = 'image';
            if (str_starts_with($mime, 'video/')) {
                $folder = 'videos';
                $type = 'video';
            } elseif ($mime === 'application/pdf') {
                $folder = 'documents';
                $type = 'pdf';
            }

            $fileName = date('YmdHis') . '-' . $origName;
            $dest = $uploadBaseDir . '/' . $folder . '/' . $fileName;
            move_uploaded_file($tmpName, $dest);

            $sizeKb = max(1, (int) round(filesize($dest) / 1024));
            $entry = [
                'name' => $fileName,
                'type' => $type,
                'size' => $sizeKb >= 1024 ? round($sizeKb / 1024, 1) . ' MB' : $sizeKb . ' KB',
                'path' => 'storage/uploads/' . $folder . '/' . $fileName,
                'url' => '/storage/uploads/' . $folder . '/' . rawurlencode($fileName),
            ];

            echo json_encode(['status' => 'success', 'data' => $entry]);
            exit;
        }

        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => 'Tidak ada file valid yang diunggah.']);
        exit;
    }

    // =========================================================================
    // GET /api/v1/articles (Filter Kategori, Tag, Search, atau Semua)
    // =========================================================================
    if ($uri === '/api/v1/articles' && $method === 'GET') {
        $conditions = ["a.status = 'published'"];
        $params = [];

        if (!empty($_GET['category']) && $_GET['category'] !== 'all') {
            $conditions[] = 'c.slug = ?';
            $params[] = $_GET['category'];
        }

        if (!empty($_GET['tag'])) {
            $conditions[] = 'EXISTS (SELECT 1 FROM article_tag at INNER JOIN tags t ON t.id = at.tag_id WHERE at.article_id = a.id AND (t.slug = ? OR LOWER(t.name) = LOWER(?)))';
            $params[] = $_GET['tag'];
            $params[] = $_GET['tag'];
        }

        if (!empty($_GET['search'])) {
            $q = '%' . trim((string) $_GET['search']) . '%';
            $conditions[] = '(a.title LIKE ? OR a.excerpt LIKE ? OR a.content_body LIKE ?)';
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }

        $whereSql = 'WHERE ' . implode(' AND ', $conditions);
        $stmt = $pdo->prepare("$baseArticleSelect $whereSql ORDER BY a.published_at DESC, a.id DESC");
        $stmt->execute($params);

        echo json_encode([
            'status' => 'success',
            'data' => hydrateArticles($pdo, $stmt->fetchAll()),
        ]);
        exit;
    }

    // =========================================================================
    // GET /api/v1/articles/headline
    // =========================================================================
    if ($uri === '/api/v1/articles/headline' && $method === 'GET') {
        $stmt = $pdo->query("$baseArticleSelect WHERE a.status = 'published' AND a.is_headline = 1 ORDER BY a.id DESC LIMIT 5");
        echo json_encode([
            'status' => 'success',
            'data' => hydrateArticles($pdo, $stmt->fetchAll()),
        ]);
        exit;
    }

    // =========================================================================
    // GET /api/v1/articles/trending
    // =========================================================================
    if ($uri === '/api/v1/articles/trending' && $method === 'GET') {
        $stmt = $pdo->query("$baseArticleSelect WHERE a.status = 'published' ORDER BY a.views_count DESC LIMIT 5");
        echo json_encode([
            'status' => 'success',
            'data' => hydrateArticles($pdo, $stmt->fetchAll()),
        ]);
        exit;
    }

    // =========================================================================
    // GET /api/v1/categories & GET /api/v1/tags/popular
    // =========================================================================
    if ($uri === '/api/v1/categories' && $method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY id ASC");
        echo json_encode([
            'status' => 'success',
            'data' => $stmt->fetchAll(),
        ]);
        exit;
    }

    if ($uri === '/api/v1/tags/popular' && $method === 'GET') {
        $stmt = $pdo->query("
            SELECT t.*,
                   (SELECT COUNT(*) FROM article_tag at INNER JOIN articles a ON a.id = at.article_id WHERE at.tag_id = t.id AND a.status = 'published') AS articles_count
            FROM tags t
            ORDER BY articles_count DESC, t.name ASC
            LIMIT 10
        ");
        echo json_encode([
            'status' => 'success',
            'data' => $stmt->fetchAll(),
        ]);
        exit;
    }

    // =========================================================================
    // GET /api/v1/articles/{id_or_slug} (Detail Artikel + Increment View Counter Real)
    // =========================================================================
    if (preg_match('#^/api/v1/articles/([^/]+)$#', $uri, $matches) && $method === 'GET') {
        $identifier = urldecode($matches[1]);
        if (ctype_digit($identifier)) {
            $stmt = $pdo->prepare("$baseArticleSelect WHERE a.id = ? LIMIT 1");
        } else {
            $stmt = $pdo->prepare("$baseArticleSelect WHERE a.slug = ? LIMIT 1");
        }
        $stmt->execute([$identifier]);
        $row = $stmt->fetch();

        if (!$row) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Artikel tidak ditemukan.']);
            exit;
        }

        $pdo->prepare("UPDATE articles SET views_count = views_count + 1 WHERE id = ?")->execute([$row['id']]);
        $pdo->prepare("INSERT INTO article_views (article_id, ip_address, user_agent, viewed_at) VALUES (?, ?, ?, datetime('now'))")
            ->execute([$row['id'], $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', $_SERVER['HTTP_USER_AGENT'] ?? 'Browser']);

        $row['views_count'] = (int) $row['views_count'] + 1;
        $hydrated = hydrateArticles($pdo, [$row])[0];

        echo json_encode([
            'status' => 'success',
            'data' => $hydrated,
        ]);
        exit;
    }

    // =========================================================================
    // CRUD ADMIN: POST / DELETE /api/v1/admin/articles
    // =========================================================================
    if ($uri === '/api/v1/admin/articles' && $method === 'POST') {
        $body = getJsonBody();
        $id = isset($body['id']) ? (int) $body['id'] : 0;
        $title = trim((string) ($body['title'] ?? ''));
        $slug = trim((string) ($body['slug'] ?? ''));
        if ($slug === '') {
            $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $title), '-'));
        }
        $excerpt = trim((string) ($body['excerpt'] ?? ''));
        $contentBody = (string) ($body['body'] ?? $body['content_body'] ?? '');
        $status = in_array($body['status'] ?? 'published', ['draft', 'review', 'published'], true) ? $body['status'] : 'published';
        $isHeadline = !empty($body['headline']) || !empty($body['is_headline']) ? 1 : 0;
        $featuredImage = trim((string) ($body['image'] ?? $body['featured_image'] ?? ''));
        $captionImage = trim((string) ($body['image_caption'] ?? $body['caption_image'] ?? ''));

        $catName = trim((string) ($body['category'] ?? 'Nasional'));
        $stmtCat = $pdo->prepare("SELECT id FROM categories WHERE name = ? OR slug = ? LIMIT 1");
        $stmtCat->execute([$catName, strtolower($catName)]);
        $catId = (int) ($stmtCat->fetchColumn() ?: 1);

        $userId = (int) ($pdo->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 1);

        if ($id > 0) {
            $stmt = $pdo->prepare("
                UPDATE articles
                SET category_id = ?, title = ?, slug = ?, excerpt = ?, content_body = ?,
                    featured_image = ?, caption_image = ?, status = ?, is_headline = ?,
                    updated_at = datetime('now')
                WHERE id = ?
            ");
            $stmt->execute([$catId, $title, $slug, $excerpt, $contentBody, $featuredImage, $captionImage, $status, $isHeadline, $id]);
        } else {
            // Pastikan slug unik
            $checkSlug = $pdo->prepare("SELECT COUNT(*) FROM articles WHERE slug = ?");
            $checkSlug->execute([$slug]);
            if ((int) $checkSlug->fetchColumn() > 0) {
                $slug .= '-' . time();
            }

            $stmt = $pdo->prepare("
                INSERT INTO articles (
                    user_id, category_id, title, slug, excerpt, content_body,
                    featured_image, caption_image, status, is_headline, is_trending, views_count, published_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, datetime('now'))
            ");
            $stmt->execute([$userId, $catId, $title, $slug, $excerpt, $contentBody, $featuredImage, $captionImage, $status, $isHeadline]);
            $id = (int) $pdo->lastInsertId();
        }

        echo json_encode(['status' => 'success', 'message' => 'Artikel berhasil disimpan!', 'id' => $id]);
        exit;
    }

    if (preg_match('#^/api/v1/admin/articles/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        $pdo->prepare("DELETE FROM articles WHERE id = ?")->execute([(int) $m[1]]);
        echo json_encode(['status' => 'success', 'message' => 'Artikel berhasil dihapus.']);
        exit;
    }

    // =========================================================================
    // CRUD ADMIN: POST / DELETE /api/v1/admin/categories
    // =========================================================================
    if ($uri === '/api/v1/admin/categories' && $method === 'POST') {
        $body = getJsonBody();
        $id = isset($body['id']) ? (int) $body['id'] : 0;
        $name = trim((string) ($body['name'] ?? ''));
        $slug = trim((string) ($body['slug'] ?? ''));
        $desc = trim((string) ($body['desc'] ?? $body['description'] ?? ''));
        $active = !empty($body['active']) || !empty($body['is_active']) ? 1 : 0;

        if ($id > 0) {
            $pdo->prepare("UPDATE categories SET name = ?, slug = ?, description = ?, is_active = ?, updated_at = datetime('now') WHERE id = ?")
                ->execute([$name, $slug, $desc, $active, $id]);
        } else {
            $pdo->prepare("INSERT INTO categories (name, slug, description, is_active) VALUES (?, ?, ?, ?)")
                ->execute([$name, $slug, $desc, $active]);
        }

        echo json_encode(['status' => 'success', 'message' => 'Kategori berhasil disimpan!']);
        exit;
    }

    if (preg_match('#^/api/v1/admin/categories/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([(int) $m[1]]);
        echo json_encode(['status' => 'success', 'message' => 'Kategori berhasil dihapus.']);
        exit;
    }

    // =========================================================================
    // CRUD ADMIN: POST / DELETE /api/v1/admin/users
    // =========================================================================
    if ($uri === '/api/v1/admin/users' && $method === 'POST') {
        $body = getJsonBody();
        $id = isset($body['id']) ? (int) $body['id'] : 0;
        $name = trim((string) ($body['name'] ?? ''));
        $email = trim((string) ($body['email'] ?? ''));
        $role = in_array($body['role'] ?? 'journalist', ['super_admin', 'editor', 'journalist'], true) ? $body['role'] : 'journalist';

        if ($id > 0) {
            if (!empty($body['password'])) {
                $password = password_hash((string) $body['password'], PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ?, password = ?, updated_at = datetime('now') WHERE id = ?")
                    ->execute([$name, $email, $role, $password, $id]);
            } else {
                $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ?, updated_at = datetime('now') WHERE id = ?")
                    ->execute([$name, $email, $role, $id]);
            }
        } else {
            $password = !empty($body['password'])
                ? password_hash((string) $body['password'], PASSWORD_BCRYPT)
                : password_hash('password123', PASSWORD_BCRYPT);
            $pdo->prepare("INSERT INTO users (name, email, password, role, email_verified_at) VALUES (?, ?, ?, ?, datetime('now'))")
                ->execute([$name, $email, $password, $role]);
        }

        echo json_encode(['status' => 'success', 'message' => 'Akun pengguna berhasil disimpan!']);
        exit;
    }

    if (preg_match('#^/api/v1/admin/users/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([(int) $m[1]]);
        echo json_encode(['status' => 'success', 'message' => 'Akun pengguna berhasil dihapus.']);
        exit;
    }

    // =========================================================================
    // ENDPOINT PUBLIK & ADMIN: ADVERTISEMENTS (PENGELOLAAN SLOT IKLAN)
    // =========================================================================
    if ($uri === '/api/v1/advertisements' && $method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM advertisements WHERE is_active = 1 ORDER BY id ASC");
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
        exit;
    }

    if (preg_match('#^/api/v1/advertisements/(\d+)/click$#', $uri, $m) && $method === 'POST') {
        $adId = (int) $m[1];
        $pdo->prepare("UPDATE advertisements SET clicks_count = clicks_count + 1 WHERE id = ?")->execute([$adId]);
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($uri === '/api/v1/admin/advertisements' && $method === 'POST') {
        $body = getJsonBody();
        $id = isset($body['id']) ? (int) $body['id'] : 0;
        $title = trim((string) ($body['title'] ?? ''));
        $sponsor = trim((string) ($body['sponsor_name'] ?? ''));
        $position = in_array($body['position'] ?? 'leaderboard_top', ['leaderboard_top', 'infeed_home', 'sidebar_home', 'sidebar_article'], true)
            ? $body['position']
            : 'leaderboard_top';
        $adType = in_array($body['ad_type'] ?? 'image', ['image', 'html'], true) ? $body['ad_type'] : 'image';
        $imageUrl = trim((string) ($body['image_url'] ?? ''));
        $targetUrl = trim((string) ($body['target_url'] ?? ''));
        $htmlCode = (string) ($body['html_code'] ?? '');
        $active = !empty($body['active']) || !empty($body['is_active']) ? 1 : 0;

        if ($id > 0) {
            $pdo->prepare("
                UPDATE advertisements
                SET title = ?, sponsor_name = ?, position = ?, ad_type = ?, image_url = ?, target_url = ?, html_code = ?, is_active = ?, updated_at = datetime('now')
                WHERE id = ?
            ")->execute([$title, $sponsor, $position, $adType, $imageUrl, $targetUrl, $htmlCode, $active, $id]);
        } else {
            $pdo->prepare("
                INSERT INTO advertisements (title, sponsor_name, position, ad_type, image_url, target_url, html_code, is_active, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
            ")->execute([$title, $sponsor, $position, $adType, $imageUrl, $targetUrl, $htmlCode, $active]);
            $id = (int) $pdo->lastInsertId();
        }

        echo json_encode(['status' => 'success', 'message' => 'Slot iklan berhasil disimpan ke database!', 'id' => $id]);
        exit;
    }

    if (preg_match('#^/api/v1/admin/advertisements/(\d+)/toggle$#', $uri, $m) && $method === 'POST') {
        $adId = (int) $m[1];
        $pdo->prepare("UPDATE advertisements SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END, updated_at = datetime('now') WHERE id = ?")
            ->execute([$adId]);
        echo json_encode(['status' => 'success', 'message' => 'Status tayang iklan diperbarui.']);
        exit;
    }

    if (preg_match('#^/api/v1/admin/advertisements/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        $pdo->prepare("DELETE FROM advertisements WHERE id = ?")->execute([(int) $m[1]]);
        echo json_encode(['status' => 'success', 'message' => 'Slot iklan berhasil dihapus.']);
        exit;
    }

    // =========================================================================
    // ENDPOINT INSPEKSI DATABASE LENGKAP (phpMyAdmin / SQLite Web Viewer)
    // =========================================================================
    if ($uri === '/api/v1/admin/db-inspect' && $method === 'GET') {
        $tablesRaw = $pdo->query("
            SELECT name, sql
            FROM sqlite_master
            WHERE type = 'table' AND name NOT LIKE 'sqlite_%'
            ORDER BY name ASC
        ")->fetchAll();

        $tables = [];
        foreach ($tablesRaw as $t) {
            $tName = $t['name'];
            $cols = $pdo->query("PRAGMA table_info(\"$tName\")")->fetchAll();
            $count = (int) $pdo->query("SELECT COUNT(*) FROM \"$tName\"")->fetchColumn();
            $rows = $pdo->query("SELECT * FROM \"$tName\" LIMIT 200")->fetchAll();
            $tables[] = [
                'name' => $tName,
                'ddl' => $t['sql'],
                'count' => $count,
                'columns' => $cols,
                'rows' => $rows,
            ];
        }

        echo json_encode([
            'status' => 'success',
            'database' => [
                'driver' => 'SQLite 3 (' . $pdo->query('SELECT sqlite_version()')->fetchColumn() . ')',
                'file_path' => realpath($dbFile) ?: $dbFile,
                'sql_dump_path' => realpath(__DIR__ . '/database/josjis_news_dump.sql') ?: (__DIR__ . '/database/josjis_news_dump.sql'),
                'file_size_kb' => round((filesize($dbFile) ?: 0) / 1024, 2),
                'last_modified' => date('Y-m-d H:i:s', filemtime($dbFile) ?: time()),
                'tables_count' => count($tables),
            ],
            'tables' => $tables,
        ]);
        exit;
    }

    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Endpoint API tidak ditemukan: ' . $uri]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
