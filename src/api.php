<?php
/**
 * REST API /api/v1/*
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/repository.php';
require_once __DIR__ . '/media.php';
require_once __DIR__ . '/twofactor.php';

// Hash bcrypt valid (bukan milik siapa pun) agar waktu respons login seragam saat email tak dikenal.
const DUMMY_BCRYPT_HASH = '$2y$12$9uuCle4bjJksIpcJEyC.d.auqtwGwA2Zd3BBwenxTiZjAWtVi7/MC';
const EDITORIAL = ['super_admin', 'editor'];
const ALL_STAFF = ['super_admin', 'editor', 'journalist'];

function handle_api($uri, $method)
{
    $pdo = db();
    enforce_same_origin();

    // Pembatas laju umum: 240 request/menit/IP untuk API; pencarian lebih ketat.
    if (!rate_limit($pdo, 'api:' . client_ip(), 240, 60)) {
        header('Retry-After: 60');
        json_error('Terlalu akeh request. Alon-alon sik.', 429);
    }
    if (isset($_GET['search']) && $_GET['search'] !== '' && !rate_limit($pdo, 'search:' . client_ip(), 30, 60)) {
        header('Retry-After: 60');
        json_error('Pencarian kakehan. Tunggu sedhela.', 429);
    }

    // ------------------------------------------------------------------ auth
    if ($uri === '/api/v1/admin/login' && $method === 'POST') {
        return api_login($pdo);
    }
    if ($uri === '/api/v1/admin/login/2fa' && $method === 'POST') {
        return tf_complete_login($pdo);
    }
    if ($uri === '/api/v1/admin/me' && $method === 'GET') {
        $admin = require_admin($pdo, [], true);
        $admin['two_factor'] = tf_enabled($pdo, $admin['id']);
        $admin['two_factor_required'] = tf_required_for($admin);
        unset($admin['token']);
        json_out(['status' => 'success', 'user' => $admin]);
    }
    if ($uri === '/api/v1/admin/logout' && $method === 'POST') {
        $token = session_token();
        if ($token !== '') {
            $pdo->prepare('DELETE FROM sessions WHERE id = ?')->execute([session_key($token)]);
        }
        set_session_cookie('', time() - 3600);
        json_out(['status' => 'success', 'message' => 'Berhasil keluar dan sesi login telah dihapus.']);
    }
    if ($uri === '/api/v1/admin/password' && $method === 'POST') {
        return api_change_password($pdo);
    }

    // Semua /admin/* wajib login (kecuali yang ditangani di atas)
    if (str_starts_with($uri, '/api/v1/admin/')) {
        $admin = require_admin($pdo, [], strpos($uri, '/api/v1/admin/2fa/') === 0);
        return api_admin($pdo, $admin, $uri, $method);
    }

    // ---------------------------------------------------------------- publik
    if ($method === 'GET') {
        if ($uri === '/api/v1/health') {
            $pdo->query('SELECT 1');
            json_out(['status' => 'success', 'time' => gmdate('c')]);
        }
        if ($uri === '/api/v1/bootstrap') {
            return api_bootstrap($pdo);
        }
        if ($uri === '/api/v1/articles') {
            [$items, $meta] = query_published($pdo, [
                'page'     => $_GET['page'] ?? 1,
                'per_page' => $_GET['per_page'] ?? 12,
                'category' => $_GET['category'] ?? null,
                'tag'      => $_GET['tag'] ?? null,
                'search'   => $_GET['search'] ?? null,
                'sort'     => $_GET['sort'] ?? 'latest',
                'with_body' => false,
            ]);
            json_out(['status' => 'success', 'data' => $items, 'meta' => $meta]);
        }
        if ($uri === '/api/v1/articles/headline') {
            $st = $pdo->query(article_select_sql() . ' WHERE ' . PUBLISHED_SQL . ' AND a.is_headline = 1 ORDER BY a.published_at DESC LIMIT 5');
            json_out(['status' => 'success', 'data' => hydrate_articles($pdo, $st->fetchAll(), false, false)]);
        }
        if ($uri === '/api/v1/articles/trending') {
            json_out(['status' => 'success', 'data' => trending_articles($pdo, $_GET['hours'] ?? 48, $_GET['limit'] ?? 5)]);
        }
        if ($uri === '/api/v1/categories') {
            json_out(['status' => 'success', 'data' => active_categories($pdo)]);
        }
        if ($uri === '/api/v1/tags/popular') {
            json_out(['status' => 'success', 'data' => popular_tags($pdo, $_GET['limit'] ?? 10)]);
        }
        if (preg_match('#^/api/v1/pages/([a-z0-9\-]+)$#', $uri, $m)) {
            require_once __DIR__ . '/seo.php';
            $pages = static_pages();
            if (!isset($pages[$m[1]])) {
                json_error('Halaman ora ditemokake.', 404);
            }
            json_out(['status' => 'success', 'data' => ['slug' => $m[1], 'title' => $pages[$m[1]][0], 'html' => $pages[$m[1]][1]]]);
        }
        if ($uri === '/api/v1/advertisements') {
            json_out(['status' => 'success', 'data' => public_ads($pdo)]);
        }
        if (preg_match('#^/api/v1/articles/([^/]+)$#', $uri, $m)) {
            return api_article_detail($pdo, urldecode($m[1]));
        }
    }

    if ($method === 'POST' && preg_match('#^/api/v1/advertisements/(\d+)/click$#', $uri, $m)) {
        if (rate_limit($pdo, 'adclick:' . client_ip() . ':' . $m[1], 3, 600)) {
            $pdo->prepare('UPDATE advertisements SET clicks_count = clicks_count + 1 WHERE id = ? AND is_active = 1')->execute([(int) $m[1]]);
        }
        json_out(['status' => 'success']);
    }

    json_error('Endpoint API tidak ditemukan.', 404);
}

/* ===================================================================== */

function api_login(PDO $pdo)
{
    $body = request_json();
    $email = strtolower(trim((string) ($body['email'] ?? '')));
    $password = (string) ($body['password'] ?? '');
    $ip = client_ip();

    // 8 percobaan / 15 menit per IP, 5 per email: cegah brute force.
    if (!rate_limit($pdo, 'login-ip:' . $ip, 8, 900) || !rate_limit($pdo, 'login-em:' . sha1($email), 5, 900)) {
        audit($pdo, null, 'login_blocked', $email);
        json_error('Terlalu akeh percobaan login. Coba maneh sawise 15 menit.', 429);
    }

    $st = $pdo->prepare('SELECT id, name, email, password, role FROM users WHERE LOWER(email) = ? LIMIT 1');
    $st->execute([$email]);
    $user = $st->fetch();

    // Tetap jalankan verifikasi hash agar waktu respons seragam.
    $hash = $user ? $user['password'] : DUMMY_BCRYPT_HASH;
    $ok = password_verify($password, (string) $hash);
    if (!$user || !$ok) {
        audit($pdo, null, 'login_failed', $email);
        json_error('Email atau password tidak sesuai.', 401);
    }

    if (password_needs_rehash($user['password'], PASSWORD_BCRYPT, ['cost' => PASSWORD_COST])) {
        $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([hash_password($password), $user['id']]);
    }

    if (tf_enabled($pdo, (int) $user['id'])) {
        audit($pdo, (int) $user['id'], 'login_password_ok_2fa_pending', '');
        json_out(['status' => '2fa_required', 'challenge' => tf_create_challenge($pdo, (int) $user['id']),
            'message' => 'Masukake kode 6 digit saka aplikasi autentikator.']);
    }

    start_session($pdo, $user, [
        'must_change_password' => password_problem($password) !== null,
    ]);
}

/** Buat sesi + cookie, lalu kirim respons login. */
function start_session(PDO $pdo, array $user, array $extra = [])
{
    $ip = client_ip();
    $token = bin2hex(random_bytes(32));
    $pdo->prepare('INSERT INTO sessions (id, user_id, ip_address, user_agent, payload, last_activity) VALUES (?,?,?,?,?,?)')
        ->execute([
            session_key($token), (int) $user['id'], $ip,
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300),
            json_encode(['login_at' => date('c'), 'login_ts' => time()]), time(),
        ]);
    // Batasi 5 sesi aktif per pengguna.
    $pdo->prepare('DELETE FROM sessions WHERE user_id = ? AND id NOT IN
        (SELECT id FROM sessions WHERE user_id = ? ORDER BY last_activity DESC LIMIT 5)')
        ->execute([$user['id'], $user['id']]);

    set_session_cookie($token, time() + SESSION_TTL);
    audit($pdo, (int) $user['id'], 'login', '');

    $userOut = ['id' => (int) $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $user['role']];
    json_out(array_merge([
        'status' => 'success',
        'token'  => $token,
        'user'   => $userOut,
        'must_enable_2fa' => tf_required_for($userOut) && !tf_enabled($pdo, (int) $user['id']),
    ], $extra));
}

function api_change_password(PDO $pdo)
{
    $admin = require_admin($pdo, [], true);
    $body = request_json();
    $current = (string) ($body['current_password'] ?? '');
    $new = (string) ($body['new_password'] ?? '');

    $st = $pdo->prepare('SELECT password FROM users WHERE id = ?');
    $st->execute([$admin['id']]);
    if (!password_verify($current, (string) $st->fetchColumn())) {
        json_error('Password lawas ora sesuai.', 422);
    }
    if ($problem = password_problem($new)) {
        json_error($problem, 422);
    }
    $pdo->prepare("UPDATE users SET password = ?, updated_at = datetime('now') WHERE id = ?")
        ->execute([hash_password($new), $admin['id']]);
    // Akhiri sesi lain.
    $pdo->prepare('DELETE FROM sessions WHERE user_id = ? AND id != ?')->execute([$admin['id'], session_key($admin['token'])]);
    // Hapus berkas sandi awal bila masih ada.
    $note = dirname(db_path()) . '/INITIAL_ADMIN_PASSWORD.txt';
    if (is_file($note)) {
        @unlink($note);
    }
    audit($pdo, $admin['id'], 'password_changed', '');
    json_out(['status' => 'success', 'message' => 'Password berhasil diganti.']);
}

function public_ads(PDO $pdo)
{
    $rows = $pdo->query("SELECT * FROM advertisements WHERE is_active = 1
        AND (start_date IS NULL OR start_date <= datetime('now'))
        AND (end_date IS NULL OR end_date >= datetime('now')) ORDER BY id")->fetchAll();
    return array_map('ad_row', $rows);
}

function ad_row(array $ad)
{
    return [
        'id'           => (int) $ad['id'],
        'title'        => $ad['title'],
        'sponsor_name' => $ad['sponsor_name'] ?? '',
        'position'     => $ad['position'],
        'ad_type'      => $ad['ad_type'] ?? 'image',
        'image_url'    => $ad['image_url'] ?? '',
        'target_url'   => $ad['target_url'] ?? '',
        'html_code'    => $ad['html_code'] ?? '',
        'active'       => (bool) $ad['is_active'],
        'is_active'    => (int) $ad['is_active'],
        'views_count'  => (int) $ad['views_count'],
        'clicks_count' => (int) $ad['clicks_count'],
        'start_date'   => $ad['start_date'],
        'end_date'     => $ad['end_date'],
    ];
}

function category_row(array $c)
{
    return [
        'id' => (int) $c['id'], 'name' => $c['name'], 'slug' => $c['slug'],
        'desc' => $c['description'] ?? '', 'description' => $c['description'] ?? '',
        'active' => (bool) $c['is_active'], 'is_active' => (int) $c['is_active'],
        'articles_count' => (int) ($c['articles_count'] ?? 0),
    ];
}

function api_bootstrap(PDO $pdo)
{
    $isAdmin = isset($_GET['mode']) && $_GET['mode'] === 'admin';

    if (!$isAdmin) {
        [$articles, $meta] = query_published($pdo, ['per_page' => 40, 'with_body' => false]);
        // Hitung tayangan iklan maksimal sekali per 30 menit per pengunjung.
        $ads = public_ads($pdo);
        if ($ads && rate_limit($pdo, 'adview:' . client_ip(), 1, 1800)) {
            $pdo->exec('UPDATE advertisements SET views_count = views_count + 1 WHERE is_active = 1');
        }
        json_out(['status' => 'success', 'data' => [
            'articles'       => $articles,
            'articles_meta'  => $meta,
            'categories'     => array_map('category_row', active_categories($pdo)),
            'tags'           => popular_tags($pdo, 10),
            'trending'       => trending_articles($pdo, 48, 5),
            'advertisements' => $ads,
            'site'           => public_site_info(),
        ]]);
    }

    // ---- mode admin
    $admin = require_admin($pdo);
    $isEditorial = in_array($admin['role'], EDITORIAL, true);

    $sql = article_select_sql();
    $params = [];
    if (!$isEditorial) {
        $sql .= ' WHERE a.user_id = ?';
        $params[] = $admin['id'];
    }
    $st = $pdo->prepare($sql . ' ORDER BY a.id DESC');
    $st->execute($params);
    $articles = hydrate_articles($pdo, $st->fetchAll(), true, true);

    $cats = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM articles a WHERE a.category_id = c.id AND a.status = 'published') AS articles_count
        FROM categories c ORDER BY c.id")->fetchAll();

    $users = [];
    if ($admin['role'] === 'super_admin') {
        foreach ($pdo->query('SELECT u.id, u.name, u.email, u.role,
            (SELECT COUNT(*) FROM articles a WHERE a.user_id = u.id) AS articles_count FROM users u ORDER BY u.id')->fetchAll() as $u) {
            $users[] = ['id' => (int) $u['id'], 'name' => $u['name'], 'email' => $u['email'], 'role' => $u['role'],
                'articles' => (int) $u['articles_count'], 'articles_count' => (int) $u['articles_count'],
                'two_factor' => tf_enabled($pdo, (int) $u['id'])];
        }
    }

    $ads = $isEditorial ? array_map('ad_row', $pdo->query('SELECT * FROM advertisements ORDER BY id')->fetchAll()) : [];

    $daily = [];
    $names = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
    $stDay = $pdo->prepare('SELECT COUNT(*) FROM article_views WHERE date(viewed_at) = ?');
    for ($i = 6; $i >= 0; $i--) {
        $d = gmdate('Y-m-d', strtotime("-{$i} days"));
        $stDay->execute([$d]);
        $daily[] = ['date' => $d, 'label' => $i === 0 ? 'Hari Ini' : $names[(int) gmdate('w', strtotime($d))], 'views' => (int) $stDay->fetchColumn()];
    }

    json_out(['status' => 'success', 'data' => [
        'articles'       => $articles,
        'categories'     => array_map('category_row', $cats),
        'tags'           => popular_tags($pdo, 50),
        'users'          => $users,
        'advertisements' => $ads,
        'media'          => scan_media(),
        'stats'          => [
            'total_views' => (int) $pdo->query('SELECT COALESCE(SUM(views_count),0) FROM articles')->fetchColumn(),
            'daily_views' => $daily,
        ],
        'me'             => array_diff_key($admin, ['token' => 1]), // token sesi tidak boleh sampai ke JS
    ]]);
}

function public_site_info()
{
    $s = site();
    return ['name' => $s['name'], 'tagline' => $s['tagline'], 'company' => $s['company']];
}

function api_article_detail(PDO $pdo, $identifier)
{
    $row = find_published_article($pdo, $identifier);
    if (!$row) {
        json_error('Artikel tidak ditemukan.', 404);
    }
    // Catat kunjungan maksimal sekali per 30 menit per IP+artikel.
    $recorded = false;
    if (rate_limit($pdo, 'view:' . client_ip() . ':' . $row['id'], 1, 1800)) {
        $pdo->prepare('UPDATE articles SET views_count = views_count + 1 WHERE id = ?')->execute([$row['id']]);
        $pdo->prepare("INSERT INTO article_views (article_id, ip_address, user_agent, viewed_at) VALUES (?,?,?,datetime('now'))")
            ->execute([$row['id'], client_ip(), substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500)]);
        $row['views_count'] = (int) $row['views_count'] + 1;
        $recorded = true;
    }
    $article = hydrate_articles($pdo, [$row])[0];
    [$related] = query_published($pdo, ['category' => $row['category_slug'], 'per_page' => 4, 'exclude_id' => $row['id']]);
    json_out(['status' => 'success', 'data' => $article, 'related' => $related, 'meta' => ['view_recorded' => $recorded]]);
}

/* ===================================================================== */
/* Admin                                                                  */
/* ===================================================================== */

function api_admin(PDO $pdo, array $admin, $uri, $method)
{
    $role = $admin['role'];

    if (tf_handle_admin($pdo, $admin, $uri, $method)) {
        return;
    }

    // ---- media
    if ($uri === '/api/v1/admin/media') {
        if ($method === 'GET') {
            json_out(['status' => 'success', 'data' => scan_media()]);
        }
        if ($method === 'POST') {
            if (!rate_limit($pdo, 'upload:' . $admin['id'], 40, 600)) {
                json_error('Unggahan kakehan. Tunggu sedhela.', 429);
            }
            $res = save_uploaded_media($_FILES['file'] ?? null);
            if (isset($res['error'])) {
                json_error($res['error'], 422);
            }
            audit($pdo, $admin['id'], 'media_upload', $res['path']);
            json_out(['status' => 'success', 'data' => $res]);
        }
        if ($method === 'DELETE') {
            require_admin($pdo, EDITORIAL);
            $path = (string) ($_GET['path'] ?? '');
            if (!delete_media($path)) {
                json_error('Berkas ora ditemokake.', 404);
            }
            audit($pdo, $admin['id'], 'media_delete', $path);
            json_out(['status' => 'success', 'message' => 'Berkas dihapus.']);
        }
    }

    // ---- artikel
    if ($uri === '/api/v1/admin/articles' && $method === 'POST') {
        return api_save_article($pdo, $admin, request_json());
    }
    if ($method === 'DELETE' && preg_match('#^/api/v1/admin/articles/(\d+)$#', $uri, $m)) {
        $st = $pdo->prepare('SELECT id, user_id, status, title FROM articles WHERE id = ?');
        $st->execute([(int) $m[1]]);
        $a = $st->fetch();
        if (!$a) {
            json_error('Artikel ora ditemokake.', 404);
        }
        if (!can_manage_article($admin, $a) || (!in_array($role, EDITORIAL, true) && $a['status'] === 'published')) {
            json_error('Sampeyan ora duwe hak mbusak artikel iki.', 403);
        }
        $pdo->prepare('DELETE FROM articles WHERE id = ?')->execute([$a['id']]);
        audit($pdo, $admin['id'], 'article_delete', $a['title']);
        json_out(['status' => 'success', 'message' => 'Artikel berhasil dihapus.']);
    }

    // ---- kategori
    if ($uri === '/api/v1/admin/categories' && $method === 'POST') {
        require_admin($pdo, EDITORIAL);
        return api_save_category($pdo, $admin, request_json());
    }
    if ($method === 'DELETE' && preg_match('#^/api/v1/admin/categories/(\d+)$#', $uri, $m)) {
        require_admin($pdo, ['super_admin']);
        $st = $pdo->prepare('SELECT COUNT(*) FROM articles WHERE category_id = ?');
        $st->execute([(int) $m[1]]);
        if ((int) $st->fetchColumn() > 0) {
            json_error('Kategori isih dienggo artikel. Pindahke artikele dhisik.', 409);
        }
        $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([(int) $m[1]]);
        audit($pdo, $admin['id'], 'category_delete', (string) $m[1]);
        json_out(['status' => 'success', 'message' => 'Kategori berhasil dihapus.']);
    }

    // ---- pengguna
    if ($uri === '/api/v1/admin/users' && $method === 'POST') {
        require_admin($pdo, ['super_admin']);
        return api_save_user($pdo, $admin, request_json());
    }
    if ($method === 'DELETE' && preg_match('#^/api/v1/admin/users/(\d+)$#', $uri, $m)) {
        require_admin($pdo, ['super_admin']);
        return api_delete_user($pdo, $admin, (int) $m[1]);
    }

    // ---- iklan
    if ($uri === '/api/v1/admin/advertisements' && $method === 'POST') {
        require_admin($pdo, EDITORIAL);
        return api_save_ad($pdo, $admin, request_json());
    }
    if ($method === 'POST' && preg_match('#^/api/v1/admin/advertisements/(\d+)/toggle$#', $uri, $m)) {
        require_admin($pdo, EDITORIAL);
        $pdo->prepare("UPDATE advertisements SET is_active = 1 - is_active, updated_at = datetime('now') WHERE id = ?")->execute([(int) $m[1]]);
        json_out(['status' => 'success', 'message' => 'Status tayang iklan diperbarui.']);
    }
    if ($method === 'DELETE' && preg_match('#^/api/v1/admin/advertisements/(\d+)$#', $uri, $m)) {
        require_admin($pdo, EDITORIAL);
        $pdo->prepare('DELETE FROM advertisements WHERE id = ?')->execute([(int) $m[1]]);
        audit($pdo, $admin['id'], 'ad_delete', (string) $m[1]);
        json_out(['status' => 'success', 'message' => 'Slot iklan berhasil dihapus.']);
    }

    // ---- jejak audit
    if ($uri === '/api/v1/admin/audit-logs' && $method === 'GET') {
        require_admin($pdo, ['super_admin']);
        $rows = $pdo->query('SELECT l.id, l.action, l.detail, l.ip_address, l.created_at, u.email
            FROM audit_logs l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.id DESC LIMIT 200')->fetchAll();
        json_out(['status' => 'success', 'data' => $rows]);
    }

    json_error('Endpoint API tidak ditemukan.', 404);
}

function api_save_article(PDO $pdo, array $admin, array $b)
{
    $id = (int) ($b['id'] ?? 0);
    $title = trim((string) ($b['title'] ?? ''));
    if ($title === '' || mb_strlen($title) > 200) {
        json_error('Judul wajib diisi (maksimal 200 karakter).', 422);
    }

    $existing = null;
    if ($id > 0) {
        $st = $pdo->prepare('SELECT * FROM articles WHERE id = ?');
        $st->execute([$id]);
        $existing = $st->fetch();
        if (!$existing) {
            json_error('Artikel ora ditemokake.', 404);
        }
        if (!can_manage_article($admin, $existing)) {
            json_error('Sampeyan namung bisa nyunting artikel dhewe.', 403);
        }
    }

    $isEditorial = in_array($admin['role'], EDITORIAL, true);
    $status = $b['status'] ?? 'draft';
    if (!in_array($status, ['draft', 'review', 'published'], true)) {
        $status = 'draft';
    }
    // Jurnalis tidak boleh menerbitkan: otomatis masuk antrean review.
    if (!$isEditorial && $status === 'published') {
        $status = 'review';
    }
    if (!$isEditorial && $existing && $existing['status'] === 'published') {
        json_error('Artikel sing wis tayang namung bisa disunting editor.', 403);
    }
    $isHeadline = $isEditorial ? (!empty($b['headline']) || !empty($b['is_headline']) ? 1 : 0)
        : (int) ($existing['is_headline'] ?? 0);

    $catKey = trim((string) ($b['category'] ?? ''));
    $st = $pdo->prepare('SELECT id FROM categories WHERE name = ? OR slug = ? OR id = ? LIMIT 1');
    $st->execute([$catKey, strtolower($catKey), ctype_digit($catKey) ? (int) $catKey : -1]);
    $catId = (int) $st->fetchColumn();
    if (!$catId) {
        json_error('Kategori ora valid.', 422);
    }

    $rawBody = (string) ($b['body'] ?? $b['content_body'] ?? '');
    if (strlen($rawBody) > 600000) {
        json_error('Isi artikel kegedhen (maksimal ±600 KB).', 413);
    }
    $body = sanitize_html($rawBody);
    if ($status !== 'draft' && trim(strip_tags($body, '<img><video><iframe>')) === '') {
        json_error('Isi artikel ora entuk kosong yen arep ditayangke.', 422);
    }
    $excerpt = trim((string) ($b['excerpt'] ?? ''));
    if ($excerpt === '') {
        $excerpt = plain_excerpt($body, 200);
    }
    $excerpt = mb_substr($excerpt, 0, 500);
    $image = safe_url($b['image'] ?? $b['featured_image'] ?? '', true);
    $caption = mb_substr(trim((string) ($b['image_caption'] ?? $b['caption_image'] ?? '')), 0, 500);

    $slugIn = trim((string) ($b['slug'] ?? ''));
    $slug = unique_slug($pdo, 'articles', slugify($slugIn !== '' ? $slugIn : $title), $id);

    $pdo->beginTransaction();
    try {
        if ($existing) {
            $publishedAt = $existing['published_at'];
            if ($status === 'published' && !$publishedAt) {
                $publishedAt = gmdate('Y-m-d H:i:s');
            }
            $pdo->prepare("UPDATE articles SET category_id=?, title=?, slug=?, excerpt=?, content_body=?, featured_image=?,
                caption_image=?, status=?, is_headline=?, published_at=?, updated_at=datetime('now') WHERE id=?")
                ->execute([$catId, $title, $slug, $excerpt, $body, $image, $caption, $status, $isHeadline, $publishedAt, $id]);
        } else {
            $pdo->prepare('INSERT INTO articles (user_id, category_id, title, slug, excerpt, content_body, featured_image,
                caption_image, status, is_headline, is_trending, views_count, published_at) VALUES (?,?,?,?,?,?,?,?,?,?,0,0,?)')
                ->execute([$admin['id'], $catId, $title, $slug, $excerpt, $body, $image, $caption, $status, $isHeadline,
                    $status === 'published' ? gmdate('Y-m-d H:i:s') : null]);
            $id = (int) $pdo->lastInsertId();
        }
        if (array_key_exists('tags', $b)) {
            sync_article_tags($pdo, $id, $b['tags']);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    audit($pdo, $admin['id'], $existing ? 'article_update' : 'article_create', $title);
    json_out(['status' => 'success', 'message' => $status === 'review' && !$isEditorial
        ? 'Artikel dikirim menyang editor kanggo di-review.' : 'Artikel berhasil disimpan!', 'id' => $id, 'slug' => $slug, 'saved_status' => $status]);
}

function api_save_category(PDO $pdo, array $admin, array $b)
{
    $id = (int) ($b['id'] ?? 0);
    $name = trim((string) ($b['name'] ?? ''));
    if ($name === '' || mb_strlen($name) > 100) {
        json_error('Jeneng kategori wajib diisi.', 422);
    }
    $slug = unique_slug($pdo, 'categories', slugify(trim((string) ($b['slug'] ?? '')) ?: $name), $id);
    $desc = mb_substr(trim((string) ($b['desc'] ?? $b['description'] ?? '')), 0, 500);
    $active = !empty($b['active']) || !empty($b['is_active']) ? 1 : 0;

    if ($id > 0) {
        $pdo->prepare("UPDATE categories SET name=?, slug=?, description=?, is_active=?, updated_at=datetime('now') WHERE id=?")
            ->execute([$name, $slug, $desc, $active, $id]);
    } else {
        $pdo->prepare('INSERT INTO categories (name, slug, description, is_active) VALUES (?,?,?,?)')->execute([$name, $slug, $desc, $active]);
    }
    audit($pdo, $admin['id'], 'category_save', $name);
    json_out(['status' => 'success', 'message' => 'Kategori berhasil disimpan!']);
}

function api_save_user(PDO $pdo, array $admin, array $b)
{
    $id = (int) ($b['id'] ?? 0);
    $name = trim((string) ($b['name'] ?? ''));
    $email = strtolower(trim((string) ($b['email'] ?? '')));
    $role = $b['role'] ?? 'journalist';
    $password = (string) ($b['password'] ?? '');

    if ($name === '' || mb_strlen($name) > 120) {
        json_error('Jeneng wajib diisi.', 422);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_error('Format email ora valid.', 422);
    }
    if (!in_array($role, ALL_STAFF, true)) {
        json_error('Peran ora valid.', 422);
    }
    $dup = $pdo->prepare('SELECT COUNT(*) FROM users WHERE LOWER(email) = ? AND id != ?');
    $dup->execute([$email, $id]);
    if ((int) $dup->fetchColumn() > 0) {
        json_error('Email wis kadhaftar.', 409);
    }

    if ($id > 0) {
        if ($id === $admin['id'] && $role !== 'super_admin') {
            json_error('Sampeyan ora bisa nurunake peran akun dhewe.', 422);
        }
        if ($password !== '') {
            if ($problem = password_problem($password)) {
                json_error($problem, 422);
            }
            $pdo->prepare("UPDATE users SET name=?, email=?, role=?, password=?, updated_at=datetime('now') WHERE id=?")
                ->execute([$name, $email, $role, hash_password($password), $id]);
            $pdo->prepare('DELETE FROM sessions WHERE user_id = ?')->execute([$id]);
        } else {
            $pdo->prepare("UPDATE users SET name=?, email=?, role=?, updated_at=datetime('now') WHERE id=?")
                ->execute([$name, $email, $role, $id]);
        }
    } else {
        if ($problem = password_problem($password)) {
            json_error($problem, 422);
        }
        $pdo->prepare("INSERT INTO users (name, email, password, role, email_verified_at) VALUES (?,?,?,?,datetime('now'))")
            ->execute([$name, $email, hash_password($password), $role]);
    }
    audit($pdo, $admin['id'], 'user_save', $email . ' (' . $role . ')');
    json_out(['status' => 'success', 'message' => 'Akun pengguna berhasil disimpan!']);
}

function api_delete_user(PDO $pdo, array $admin, $id)
{
    if ($id === $admin['id']) {
        json_error('Sampeyan ora bisa mbusak akun dhewe.', 422);
    }
    $st = $pdo->prepare('SELECT email FROM users WHERE id = ?');
    $st->execute([$id]);
    $email = $st->fetchColumn();
    if (!$email) {
        json_error('Pengguna ora ditemokake.', 404);
    }
    $pdo->beginTransaction();
    // Artikel tidak ikut terhapus: dialihkan ke admin yang menghapus.
    $pdo->prepare('UPDATE articles SET user_id = ? WHERE user_id = ?')->execute([$admin['id'], $id]);
    $pdo->prepare('DELETE FROM sessions WHERE user_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    $pdo->commit();
    audit($pdo, $admin['id'], 'user_delete', $email);
    json_out(['status' => 'success', 'message' => 'Akun pengguna dihapus; artikele dialihke menyang sampeyan.']);
}

function api_save_ad(PDO $pdo, array $admin, array $b)
{
    $id = (int) ($b['id'] ?? 0);
    $title = trim((string) ($b['title'] ?? ''));
    if ($title === '') {
        json_error('Judul iklan wajib diisi.', 422);
    }
    $position = in_array($b['position'] ?? '', ['leaderboard_top', 'infeed_home', 'sidebar_home', 'sidebar_article'], true)
        ? $b['position'] : 'leaderboard_top';
    $type = ($b['ad_type'] ?? 'image') === 'html' ? 'html' : 'image';
    $image = safe_url($b['image_url'] ?? '', true);
    $target = safe_url($b['target_url'] ?? '', true);
    // Kode HTML iklan disanitasi: skrip & atribut event dibuang (iklan skrip pihak ketiga tidak didukung).
    $html = sanitize_html((string) ($b['html_code'] ?? ''));
    $active = !empty($b['active']) || !empty($b['is_active']) ? 1 : 0;
    $sponsor = mb_substr(trim((string) ($b['sponsor_name'] ?? '')), 0, 120);

    if ($id > 0) {
        $pdo->prepare("UPDATE advertisements SET title=?, sponsor_name=?, position=?, ad_type=?, image_url=?, target_url=?, html_code=?,
            is_active=?, updated_at=datetime('now') WHERE id=?")
            ->execute([$title, $sponsor, $position, $type, $image, $target, $html, $active, $id]);
    } else {
        $pdo->prepare('INSERT INTO advertisements (title, sponsor_name, position, ad_type, image_url, target_url, html_code, is_active) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$title, $sponsor, $position, $type, $image, $target, $html, $active]);
        $id = (int) $pdo->lastInsertId();
    }
    audit($pdo, $admin['id'], 'ad_save', $title);
    json_out(['status' => 'success', 'message' => 'Slot iklan berhasil disimpan!', 'id' => $id]);
}
