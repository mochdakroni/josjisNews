<?php
/**
 * Autentikasi sesi admin & kontrol akses berbasis peran.
 *
 * Peran: super_admin > editor > journalist
 */

require_once __DIR__ . '/helpers.php';

const SESSION_COOKIE = 'josjis_admin_session';
const SESSION_TTL = 28800;      // 8 jam tanpa aktivitas
const SESSION_MAX_AGE = 86400;  // batas mutlak 24 jam sejak login

/** Token mentah hanya ada di cookie; basis data menyimpan hash-nya (bocor DB != sesi dibajak). */
function session_key($token)
{
    return hash('sha256', $token);
}

function session_token()
{
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+([a-f0-9]{64})$/i', trim($auth), $m)) {
        return strtolower($m[1]);
    }
    $custom = trim($_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '');
    if (preg_match('/^[a-f0-9]{64}$/i', $custom)) {
        return strtolower($custom);
    }
    $cookie = trim($_COOKIE[SESSION_COOKIE] ?? '');
    if (preg_match('/^[a-f0-9]{64}$/i', $cookie)) {
        return strtolower($cookie);
    }
    return '';
}

function set_session_cookie($token, $expires)
{
    $secure = is_https();
    if (PHP_VERSION_ID >= 70300) {
        setcookie(SESSION_COOKIE, $token, [
            'expires'  => $expires,
            'path'     => '/',
            'httponly' => true,
            'secure'   => $secure,
            'samesite' => 'Lax',
        ]);
    } else {
        setcookie(SESSION_COOKIE, $token, $expires, '/; samesite=Lax', '', $secure, true);
    }
}

function current_admin(PDO $pdo)
{
    static $cached = false;
    if ($cached !== false) {
        return $cached;
    }
    $token = session_token();
    if ($token === '') {
        return $cached = null;
    }
    $now = time();
    if (mt_rand(1, 20) === 1) {
        $pdo->prepare('DELETE FROM sessions WHERE last_activity < ?')->execute([$now - SESSION_TTL]);
    }
    $key = session_key($token);
    $st = $pdo->prepare('SELECT u.id, u.name, u.email, u.role, s.last_activity, s.payload
        FROM sessions s INNER JOIN users u ON u.id = s.user_id WHERE s.id = ? LIMIT 1');
    $st->execute([$key]);
    $row = $st->fetch();
    if (!$row || ($now - (int) $row['last_activity']) > SESSION_TTL) {
        return $cached = null;
    }
    $payload = json_decode((string) $row['payload'], true);
    $loginTs = (int) ($payload['login_ts'] ?? 0);
    if ($loginTs > 0 && ($now - $loginTs) > SESSION_MAX_AGE) {
        $pdo->prepare('DELETE FROM sessions WHERE id = ?')->execute([$key]);
        return $cached = null;
    }
    $pdo->prepare('UPDATE sessions SET last_activity = ? WHERE id = ?')->execute([$now, $key]);
    return $cached = [
        'id'    => (int) $row['id'],
        'name'  => $row['name'],
        'email' => $row['email'],
        'role'  => $row['role'],
        'token' => $token,
    ];
}

function require_admin(PDO $pdo, array $roles = [], $allowPending2fa = false)
{
    $admin = current_admin($pdo);
    if (!$admin) {
        json_error('Sesi login telah berakhir atau Anda sudah logout. Silakan login kembali.', 401);
    }
    // Kebijakan REQUIRE_2FA berlaku untuk SEMUA endpoint admin (termasuk bootstrap),
    // kecuali rute pendaftaran 2FA, /me, dan ganti password.
    if (!$allowPending2fa && tf_required_for($admin) && !tf_enabled($pdo, $admin['id'])) {
        json_error('Peran sampeyan wajib nganggo 2FA. Aktifake 2FA dhisik.', 403, ['code' => '2fa_setup_required']);
    }
    if ($roles && !in_array($admin['role'], $roles, true)) {
        json_error('Peran Anda (' . $admin['role'] . ') tidak diizinkan untuk aksi ini.', 403);
    }
    return $admin;
}

/** Tolak request tulis lintas-origin (pertahanan CSRF tambahan di luar SameSite). */
function enforce_same_origin()
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        return;
    }
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '') {
        $site = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
        if ($site !== '' && !in_array($site, ['same-origin', 'none'], true)) {
            json_error('Permintaan lintas-situs ditolak.', 403);
        }
        return;
    }
    $oHost = strtolower((string) parse_url($origin, PHP_URL_HOST));
    $oPort = parse_url($origin, PHP_URL_PORT);
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $oFull = $oPort ? $oHost . ':' . $oPort : $oHost;
    $allowed = env('CORS_ORIGIN');
    if ($oFull !== $host && !($allowed && rtrim($allowed, '/') === rtrim($origin, '/'))) {
        json_error('Permintaan lintas-situs ditolak.', 403);
    }
}

function password_problem($password)
{
    if (strlen($password) < 10) {
        return 'Password minimal 10 karakter.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'Password harus berisi huruf dan angka.';
    }
    if (in_array(strtolower($password), ['password123', 'password1234', '1234567890', 'josjisnews123'], true)) {
        return 'Password terlalu mudah ditebak.';
    }
    return null;
}

function can_manage_article(array $admin, array $article)
{
    if (in_array($admin['role'], ['super_admin', 'editor'], true)) {
        return true;
    }
    return (int) $article['user_id'] === (int) $admin['id'];
}
