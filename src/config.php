<?php
/**
 * Konfigurasi aplikasi JosjisNews.
 *
 * Nilai dibaca dari berkas `.env` (lihat `.env.example`) atau variabel lingkungan.
 * Kode kompatibel PHP 7.4 sampai 8.x.
 */

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle)
    {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle)
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('mb_strlen')) {
    function mb_strlen($s) { return strlen(utf8_decode((string) $s)); }
    function mb_substr($s, $start, $len = null) { return $len === null ? substr($s, $start) : substr($s, $start, $len); }
}

define('JOSJIS_ROOT', dirname(__DIR__));

/** Muat berkas .env sederhana (KEY=VALUE) sekali saja. */
function josjis_load_env()
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;
    $file = JOSJIS_ROOT . '/.env';
    if (!is_file($file)) {
        return;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        list($k, $v) = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0]) {
            $v = substr($v, 1, -1);
        }
        if (getenv($k) === false) {
            putenv($k . '=' . $v);
        }
    }
}

/** Hash kata sandi dengan biaya tetap (tidak bergantung default versi PHP). */
const PASSWORD_COST = 12;
function hash_password($plain)
{
    return password_hash($plain, PASSWORD_BCRYPT, ['cost' => PASSWORD_COST]);
}

function env($key, $default = null)
{
    josjis_load_env();
    $v = getenv($key);
    return ($v === false || $v === '') ? $default : $v;
}

/** Identitas situs & perusahaan (satu sumber kebenaran untuk seluruh halaman). */
function site()
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = [
            'name'        => env('SITE_NAME', 'JosjisNews'),
            'tagline'     => env('SITE_TAGLINE', 'Bloko Sutho'),
            'company'     => env('COMPANY_NAME', 'PT Sendhiko Dawuh Multiguna'),
            'url'         => rtrim((string) env('APP_URL', ''), '/'),
            'email'       => env('CONTACT_EMAIL', 'redaksi@josjisnews.com'),
            'ads_email'   => env('ADS_EMAIL', 'iklan@josjisnews.com'),
            'phone'       => env('CONTACT_PHONE', ''),
            'address'     => env('CONTACT_ADDRESS', ''),
            'editor_in_chief' => env('EDITOR_IN_CHIEF', ''),
            'description' => 'Portal kabar digital nganggo basa Jawa ngoko: cetha, blaka, lan keneng dipercaya.',
            'year'        => (int) date('Y'),
        ];
    }
    return $cfg;
}

/** URL dasar situs; jika APP_URL kosong, ambil dari request. */
function base_url()
{
    $cfg = site();
    if ($cfg['url'] !== '') {
        return $cfg['url'];
    }
    $https = is_https();
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (!preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $host)) {
        $host = 'localhost';
    }
    return ($https ? 'https://' : 'http://') . $host;
}

function is_https()
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    // X-Forwarded-Proto hanya dipercaya bila TRUSTED_PROXY=1 (mudah dipalsukan klien).
    return env('TRUSTED_PROXY') === '1' && (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function db_path()
{
    $p = env('DB_PATH');
    if ($p) {
        return $p[0] === '/' ? $p : JOSJIS_ROOT . '/' . $p;
    }
    return JOSJIS_ROOT . '/storage/data/database.sqlite';
}

function upload_dir()
{
    return JOSJIS_ROOT . '/storage/uploads';
}
