<?php
/**
 * Autentikasi dua faktor (TOTP) untuk akun staf.
 *
 * Alur login:  email+password  ->  (jika 2FA aktif) tantangan  ->  kode TOTP / kode pemulihan  ->  sesi.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/totp.php';

const CHALLENGE_TTL = 300;      // 5 menit untuk memasukkan kode
const CHALLENGE_MAX_TRIES = 5;

function tf_row(PDO $pdo, $userId)
{
    $st = $pdo->prepare('SELECT * FROM user_2fa WHERE user_id = ?');
    $st->execute([(int) $userId]);
    return $st->fetch() ?: null;
}

function tf_enabled(PDO $pdo, $userId)
{
    $r = tf_row($pdo, $userId);
    return $r && (int) $r['enabled'] === 1;
}

/** Kebijakan: wajib 2FA untuk super_admin & editor bila REQUIRE_2FA=1. */
function tf_required_for(array $user)
{
    return env('REQUIRE_2FA') === '1' && in_array($user['role'], ['super_admin', 'editor'], true);
}

function tf_kill_other_sessions(PDO $pdo, $userId, $keepToken = null)
{
    if ($keepToken) {
        $pdo->prepare('DELETE FROM sessions WHERE user_id = ? AND id != ?')->execute([$userId, session_key($keepToken)]);
    } else {
        $pdo->prepare('DELETE FROM sessions WHERE user_id = ?')->execute([$userId]);
    }
}

/**
 * Periksa kode TOTP atau kode pemulihan. Mengembalikan 'totp' | 'recovery' | false.
 * Kode TOTP yang sudah dipakai tidak bisa dipakai lagi (anti-replay).
 */
function tf_check_code(PDO $pdo, $userId, $code)
{
    $row = tf_row($pdo, $userId);
    if (!$row || (int) $row['enabled'] !== 1) {
        return false;
    }
    $code = trim((string) $code);

    if (preg_match('/^\s*\d{3}\s?\d{3}\s*$/', $code)) {
        $secret = secret_decrypt($row['secret_enc']);
        $step = $secret === '' ? null : totp_verify($secret, $code, (int) $row['last_step']);
        if ($step === null) {
            return false;
        }
        // Update atomik: hanya berhasil bila langkah ini belum dipakai request lain.
        $st = $pdo->prepare('UPDATE user_2fa SET last_step = ? WHERE user_id = ? AND last_step < ?');
        $st->execute([$step, $userId, $step]);
        return $st->rowCount() === 1 ? 'totp' : false;
    }

    $norm = recovery_normalize($code);
    if (strlen($norm) === 10) {
        $hashes = json_decode((string) $row['recovery'], true) ?: [];
        $h = recovery_hash($norm);
        foreach ($hashes as $i => $stored) {
            if (hash_equals($stored, $h)) {
                unset($hashes[$i]);
                $pdo->prepare('UPDATE user_2fa SET recovery = ? WHERE user_id = ?')->execute([json_encode(array_values($hashes)), $userId]);
                return 'recovery';
            }
        }
    }
    return false;
}

function tf_create_challenge(PDO $pdo, $userId)
{
    $pdo->prepare('DELETE FROM auth_challenges WHERE expires_at < ?')->execute([time()]);
    $token = bin2hex(random_bytes(24));
    $pdo->prepare('INSERT INTO auth_challenges (id, user_id, expires_at, attempts) VALUES (?,?,?,0)')
        ->execute([hash('sha256', $token), (int) $userId, time() + CHALLENGE_TTL]);
    return $token;
}

/** POST /api/v1/admin/login/2fa  { challenge, code } */
function tf_complete_login(PDO $pdo)
{
    $b = request_json();
    $challenge = (string) ($b['challenge'] ?? '');
    $code = (string) ($b['code'] ?? '');
    $ip = client_ip();

    if (!rate_limit($pdo, 'tf-ip:' . $ip, 15, 900)) {
        json_error('Terlalu akeh percobaan kode. Coba maneh sawise 15 menit.', 429);
    }
    if (!preg_match('/^[a-f0-9]{48}$/', $challenge)) {
        json_error('Tantangan login ora valid. Ulangi login saka awal.', 400);
    }
    $key = hash('sha256', $challenge);
    $st = $pdo->prepare('SELECT * FROM auth_challenges WHERE id = ?');
    $st->execute([$key]);
    $ch = $st->fetch();
    if (!$ch || (int) $ch['expires_at'] < time()) {
        json_error('Tantangan login kedaluwarsa. Ulangi login saka awal.', 401);
    }
    if ((int) $ch['attempts'] >= CHALLENGE_MAX_TRIES) {
        $pdo->prepare('DELETE FROM auth_challenges WHERE id = ?')->execute([$key]);
        json_error('Kakehan salah. Ulangi login saka awal.', 429);
    }
    $pdo->prepare('UPDATE auth_challenges SET attempts = attempts + 1 WHERE id = ?')->execute([$key]);

    $userId = (int) $ch['user_id'];
    $method = tf_check_code($pdo, $userId, $code);
    if (!$method) {
        audit($pdo, $userId, '2fa_failed', '');
        json_error('Kode ora bener utawa wis kedaluwarsa.', 401);
    }
    $pdo->prepare('DELETE FROM auth_challenges WHERE id = ?')->execute([$key]);

    $st = $pdo->prepare('SELECT id, name, email, role FROM users WHERE id = ?');
    $st->execute([$userId]);
    $user = $st->fetch();
    if (!$user) {
        json_error('Akun ora ditemokake.', 401);
    }
    audit($pdo, $userId, $method === 'recovery' ? '2fa_recovery_used' : '2fa_ok', '');
    $remaining = 0;
    if ($method === 'recovery') {
        $r = tf_row($pdo, $userId);
        $remaining = count(json_decode((string) $r['recovery'], true) ?: []);
    }
    start_session($pdo, $user, ['used_recovery_code' => $method === 'recovery', 'recovery_remaining' => $remaining]);
}

/** Rute 2FA yang butuh login. Mengembalikan true bila ditangani (json_out sudah exit). */
function tf_handle_admin(PDO $pdo, array $admin, $uri, $method)
{
    $uid = $admin['id'];

    if ($uri === '/api/v1/admin/2fa/status' && $method === 'GET') {
        $row = tf_row($pdo, $uid);
        $enabled = $row && (int) $row['enabled'] === 1;
        json_out(['status' => 'success', 'data' => [
            'enabled'            => $enabled,
            'recovery_remaining' => $enabled ? count(json_decode((string) $row['recovery'], true) ?: []) : 0,
            'required'           => tf_required_for($admin),
        ]]);
    }

    if ($uri === '/api/v1/admin/2fa/setup' && $method === 'POST') {
        if (tf_enabled($pdo, $uid)) {
            json_error('2FA wis aktif. Nonaktifake dhisik yen arep ngatur ulang.', 409);
        }
        $secret = totp_generate_secret();
        $pdo->prepare("INSERT OR REPLACE INTO user_2fa (user_id, secret_enc, enabled, last_step, recovery, created_at)
            VALUES (?, ?, 0, 0, '[]', datetime('now'))")->execute([$uid, secret_encrypt($secret)]);
        $issuer = site()['name'];
        json_out(['status' => 'success', 'data' => [
            'secret'      => $secret,
            'secret_text' => trim(chunk_split($secret, 4, ' ')),
            'uri'         => totp_uri($secret, $admin['email'], $issuer),
            'issuer'      => $issuer,
            'account'     => $admin['email'],
        ]]);
    }

    if ($uri === '/api/v1/admin/2fa/enable' && $method === 'POST') {
        if (!rate_limit($pdo, 'tf-user:' . $uid, 10, 900)) {
            json_error('Terlalu akeh percobaan. Coba maneh mengko.', 429);
        }
        $row = tf_row($pdo, $uid);
        if (!$row || (int) $row['enabled'] === 1) {
            json_error('Ora ana pendaftaran 2FA sing lagi mlaku. Mulai saka "Aktifake 2FA".', 409);
        }
        $secret = secret_decrypt($row['secret_enc']);
        if ($secret === '' || totp_verify($secret, (string) (request_json()['code'] ?? '')) === null) {
            json_error('Kode ora bener. Priksa jam ponsel lan coba maneh.', 422);
        }
        $codes = recovery_generate(8);
        $pdo->prepare("UPDATE user_2fa SET enabled = 1, enabled_at = datetime('now'), recovery = ? WHERE user_id = ?")
            ->execute([json_encode(array_map('recovery_hash', $codes)), $uid]);
        tf_kill_other_sessions($pdo, $uid, $admin['token']);
        audit($pdo, $uid, '2fa_enabled', '');
        json_out(['status' => 'success', 'message' => '2FA aktif. Simpen kode pemulihan ing panggonan aman.', 'data' => ['recovery_codes' => $codes]]);
    }

    if (($uri === '/api/v1/admin/2fa/disable' || $uri === '/api/v1/admin/2fa/recovery-codes') && $method === 'POST') {
        if (!rate_limit($pdo, 'tf-user:' . $uid, 10, 900)) {
            json_error('Terlalu akeh percobaan. Coba maneh mengko.', 429);
        }
        if (!tf_enabled($pdo, $uid)) {
            json_error('2FA durung aktif.', 409);
        }
        $b = request_json();
        $st = $pdo->prepare('SELECT password FROM users WHERE id = ?');
        $st->execute([$uid]);
        if (!password_verify((string) ($b['password'] ?? ''), (string) $st->fetchColumn())) {
            json_error('Password ora sesuai.', 422);
        }
        if (!tf_check_code($pdo, $uid, (string) ($b['code'] ?? ''))) {
            json_error('Kode 2FA ora bener.', 422);
        }
        if ($uri === '/api/v1/admin/2fa/disable') {
            if (tf_required_for($admin)) {
                json_error('Peran sampeyan wajib nganggo 2FA, ora bisa dinonaktifake.', 403);
            }
            $pdo->prepare('DELETE FROM user_2fa WHERE user_id = ?')->execute([$uid]);
            tf_kill_other_sessions($pdo, $uid, $admin['token']);
            audit($pdo, $uid, '2fa_disabled', '');
            json_out(['status' => 'success', 'message' => '2FA dinonaktifake.']);
        }
        $codes = recovery_generate(8);
        $pdo->prepare('UPDATE user_2fa SET recovery = ? WHERE user_id = ?')->execute([json_encode(array_map('recovery_hash', $codes)), $uid]);
        audit($pdo, $uid, '2fa_recovery_regenerated', '');
        json_out(['status' => 'success', 'message' => 'Kode pemulihan anyar digawe; sing lawas ora kanggo maneh.', 'data' => ['recovery_codes' => $codes]]);
    }

    // Super admin: setel ulang 2FA pengguna lain yang kehilangan perangkat.
    if ($method === 'POST' && preg_match('#^/api/v1/admin/users/(\d+)/2fa-reset$#', $uri, $m)) {
        require_admin($pdo, ['super_admin']);
        $target = (int) $m[1];
        if ($target === $uid) {
            json_error('Gunakan "Nonaktifake 2FA" kanggo akun dhewe.', 422);
        }
        $pdo->prepare('DELETE FROM user_2fa WHERE user_id = ?')->execute([$target]);
        tf_kill_other_sessions($pdo, $target);
        audit($pdo, $uid, '2fa_reset_by_admin', 'user ' . $target);
        json_out(['status' => 'success', 'message' => '2FA pengguna disetel ulang; sesine diakhiri.']);
    }

    return false;
}
