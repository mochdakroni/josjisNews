<?php
/**
 * TOTP (RFC 6238) + penyimpanan rahasia terenkripsi untuk 2FA admin.
 */

require_once __DIR__ . '/config.php';

const TOTP_STEP = 30;
const TOTP_WINDOW = 1; // toleransi ±1 langkah (±30 detik) untuk selisih jam

/* ------------------------------ Base32 (RFC 4648) ------------------------------ */

function base32_encode_str($bin)
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($bin) as $ch) {
        $bits .= str_pad(decbin(ord($ch)), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 5) as $chunk) {
        $out .= $alphabet[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
    }
    return $out;
}

function base32_decode_str($b32)
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(preg_replace('/[\s=\-]+/', '', (string) $b32));
    $bits = '';
    foreach (str_split($b32) as $ch) {
        $pos = strpos($alphabet, $ch);
        if ($pos === false) {
            return '';
        }
        $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $out .= chr(bindec($byte));
        }
    }
    return $out;
}

function totp_generate_secret()
{
    return base32_encode_str(random_bytes(20)); // 160 bit, 32 karakter base32
}

/** Kode TOTP untuk $secretB32 pada waktu $time. */
function totp_code($secretB32, $time = null, $digits = 6, $algo = 'sha1')
{
    $time = $time === null ? time() : (int) $time;
    return hotp_code(base32_decode_str($secretB32), intdiv($time, TOTP_STEP), $digits, $algo);
}

function hotp_code($rawKey, $counter, $digits = 6, $algo = 'sha1')
{
    $bin = pack('N2', ($counter >> 32) & 0xFFFFFFFF, $counter & 0xFFFFFFFF);
    $hash = hash_hmac($algo, $bin, $rawKey, true);
    $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
    $value = ((ord($hash[$offset]) & 0x7F) << 24)
        | ((ord($hash[$offset + 1]) & 0xFF) << 16)
        | ((ord($hash[$offset + 2]) & 0xFF) << 8)
        | (ord($hash[$offset + 3]) & 0xFF);
    return str_pad((string) ($value % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
}

/**
 * Verifikasi kode; mengembalikan langkah waktu (int) yang cocok, atau null.
 * $minStep menolak langkah yang sudah pernah dipakai (anti-replay).
 */
function totp_verify($secretB32, $code, $minStep = 0, $time = null)
{
    $code = preg_replace('/\s+/', '', (string) $code);
    if (!preg_match('/^\d{6}$/', $code)) {
        return null;
    }
    $time = $time === null ? time() : (int) $time;
    $current = intdiv($time, TOTP_STEP);
    $raw = base32_decode_str($secretB32);
    if ($raw === '') {
        return null;
    }
    $matched = null;
    for ($i = -TOTP_WINDOW; $i <= TOTP_WINDOW; $i++) {
        $step = $current + $i;
        if (hash_equals(hotp_code($raw, $step), $code) && $step > $minStep) {
            $matched = $step; // jangan break: waktu eksekusi tetap konstan
        }
    }
    return $matched;
}

function totp_uri($secretB32, $account, $issuer)
{
    return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
        . '?secret=' . $secretB32 . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=' . TOTP_STEP;
}

/* ------------------------- Enkripsi rahasia (AES-256-GCM) ------------------------- */

function app_key()
{
    static $key = null;
    if ($key !== null) {
        return $key;
    }
    $env = env('APP_KEY');
    if ($env) {
        $raw = strpos($env, 'base64:') === 0 ? base64_decode(substr($env, 7), true) : $env;
        if ($raw !== false && strlen($raw) >= 32) {
            return $key = substr(hash('sha256', $raw, true), 0, 32);
        }
    }
    $file = dirname(db_path()) . '/app.key';
    if (!is_file($file)) {
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0700, true);
        }
        file_put_contents($file, base64_encode(random_bytes(32)));
        @chmod($file, 0600);
    }
    return $key = hash('sha256', trim((string) file_get_contents($file)), true);
}

function secret_encrypt($plain)
{
    if (!function_exists('openssl_encrypt')) {
        throw new RuntimeException('Ekstensi openssl diperlukan untuk 2FA.');
    }
    if (PHP_VERSION_ID >= 70100) {
        // Format: "g" . iv(12) . tag(16) . ciphertext   (AES-256-GCM)
        $iv = random_bytes(12);
        $tag = '';
        $ct = openssl_encrypt($plain, 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, $iv, $tag);
        return base64_encode('g' . $iv . $tag . $ct);
    }
    // PHP 7.0: tidak ada parameter tag GCM. Format: "c" . iv(16) . hmac(32) . ciphertext  (CBC + HMAC-SHA256)
    $iv = random_bytes(16);
    $ct = openssl_encrypt($plain, 'aes-256-cbc', app_key(), OPENSSL_RAW_DATA, $iv);
    $mac = hash_hmac('sha256', 'c' . $iv . $ct, app_key(), true);
    return base64_encode('c' . $iv . $mac . $ct);
}

function secret_decrypt($blob)
{
    $raw = base64_decode((string) $blob, true);
    if ($raw === false || strlen($raw) < 30) {
        return '';
    }
    $kind = $raw[0];
    if ($kind === 'g' && PHP_VERSION_ID >= 70100) {
        $plain = openssl_decrypt(substr($raw, 29), 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, substr($raw, 1, 12), substr($raw, 13, 16));
        return $plain === false ? '' : $plain;
    }
    if ($kind === 'c') {
        $iv = substr($raw, 1, 16);
        $mac = substr($raw, 17, 32);
        $ct = substr($raw, 49);
        if (!hash_equals(hash_hmac('sha256', 'c' . $iv . $ct, app_key(), true), $mac)) {
            return '';
        }
        $plain = openssl_decrypt($ct, 'aes-256-cbc', app_key(), OPENSSL_RAW_DATA, $iv);
        return $plain === false ? '' : $plain;
    }
    return '';
}

/* ------------------------------ Kode pemulihan ------------------------------ */

function recovery_generate($count = 8)
{
    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        $h = strtolower(bin2hex(random_bytes(5))); // 40 bit
        $codes[] = substr($h, 0, 5) . '-' . substr($h, 5, 5);
    }
    return $codes;
}

function recovery_normalize($code)
{
    return strtolower(preg_replace('/[^a-f0-9]/i', '', (string) $code));
}

function recovery_hash($code)
{
    return hash_hmac('sha256', recovery_normalize($code), app_key());
}
