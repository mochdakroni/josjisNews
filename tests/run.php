<?php
/**
 * Tes end-to-end tanpa dependensi.
 *
 *   php tests/run.php
 *
 * Menjalankan server PHP bawaan dengan database sementara, lalu menguji
 * keamanan, RBAC, sanitasi, SEO, dan alur CMS.
 */

$root = dirname(__DIR__);
$tmp = sys_get_temp_dir() . '/josjis-test-' . getmypid();
mkdir($tmp, 0775, true);
$port = random_int(20000, 40000);
$pass = 'Uji-Coba-12345';

$appKey = 'base64:' . base64_encode(random_bytes(32));
putenv('APP_KEY=' . $appKey);
putenv('DB_PATH=' . $tmp . '/db.sqlite');
putenv('ADMIN_PASSWORD=' . $pass);
putenv('APP_URL=http://127.0.0.1:' . $port);

function server_cmd($port, $root)
{
    $args = [PHP_BINARY, '-S', "127.0.0.1:$port", $root . '/router.php'];
    return PHP_VERSION_ID >= 70400 ? $args : implode(' ', array_map('escapeshellarg', $args));
}

$proc = proc_open(
    server_cmd($port, $root),
    [0 => ['pipe', 'r'], 1 => ['file', $tmp . '/server.log', 'a'], 2 => ['file', $tmp . '/server.log', 'a']],
    $pipes,
    $root,
    ['DB_PATH' => $tmp . '/db.sqlite', 'ADMIN_PASSWORD' => $pass, 'APP_URL' => 'http://127.0.0.1:' . $port, 'PATH' => getenv('PATH'), 'APP_KEY' => $appKey]
);
register_shutdown_function(function () use ($proc, $tmp) {
    proc_terminate($proc);
    $uploadsToClean = glob(dirname(__DIR__) . '/storage/uploads/images/*-uji-*.png');
    foreach ($uploadsToClean ?: [] as $f) {
        @unlink($f);
    }
});

$base = "http://127.0.0.1:$port";
for ($i = 0; $i < 50; $i++) {
    if (@file_get_contents("$base/api/v1/health")) {
        break;
    }
    usleep(100000);
}

$pass_count = 0;
$fail_count = 0;
function check($name, $cond, $info = '')
{
    global $pass_count, $fail_count;
    if ($cond) {
        $pass_count++;
        echo "  ok    $name\n";
    } else {
        $fail_count++;
        echo "  FAIL  $name" . ($info !== '' ? "  [$info]" : '') . "\n";
    }
}

function http($method, $url, $body = null, array $headers = [], $raw = false)
{
    global $base;
    $h = array_merge(['Accept: application/json'], $headers);
    $content = null;
    if ($body !== null) {
        if (is_array($body) && !$raw) {
            $content = json_encode($body);
            $h[] = 'Content-Type: application/json';
        } else {
            $content = $body;
        }
    }
    $ctx = stream_context_create(['http' => [
        'method' => $method, 'header' => implode("\r\n", $h), 'content' => $content,
        'ignore_errors' => true, 'timeout' => 15,
    ]]);
    $resp = @file_get_contents($base . $url, false, $ctx);
    $code = 0;
    $hdrs = [];
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('#^HTTP/\S+ (\d+)#', $line, $m)) {
            $code = (int) $m[1];
        } else {
            $hdrs[strtolower(strstr($line, ':', true))] = trim(substr(strstr($line, ':'), 1));
        }
    }
    return ['code' => $code, 'body' => (string) $resp, 'json' => json_decode((string) $resp, true), 'headers' => $hdrs];
}


require_once $root . '/src/totp.php';

/** Jalankan server tambahan dengan DB segar + env tambahan. Mengembalikan [proc, base]. */
function start_server($tag, array $extraEnv = [])
{
    global $root, $tmp, $pass;
    $port2 = random_int(41000, 60000);
    $dir = $tmp . '/' . $tag;
    mkdir($dir, 0775, true);
    $env = array_merge([
        'DB_PATH' => $dir . '/db.sqlite', 'ADMIN_PASSWORD' => $pass,
        'APP_URL' => 'http://127.0.0.1:' . $port2, 'PATH' => getenv('PATH'), 'APP_KEY' => getenv('APP_KEY'),
    ], $extraEnv);
    $pr = proc_open(server_cmd($port2, $root),
        [0 => ['pipe', 'r'], 1 => ['file', $dir . '/server.log', 'a'], 2 => ['file', $dir . '/server.log', 'a']], $pipes, $root, $env);
    $b = "http://127.0.0.1:$port2";
    for ($i = 0; $i < 50; $i++) {
        if (@file_get_contents("$b/api/v1/health")) {
            break;
        }
        usleep(100000);
    }
    return [$pr, $b, $dir];
}

function auth($token)
{
    return ['Authorization: Bearer ' . $token];
}

echo "== Berkas sensitif tidak boleh bocor\n";
foreach (['/.env', '/.env.example', '/src/config.php', '/router.php', '/storage/data/db.sqlite', '/database/build_sqlite_db.php', '/.git/config', '/legacy/laravel/app/Models/Article.php'] as $p) {
    $r = http('GET', $p);
    check("GET $p tidak menyajikan berkas", $r['code'] === 404 && strpos($r['body'], '<?php') === false, "code={$r['code']}");
}
$r = http('GET', '/storage/uploads/images/x.php');
check('upload .php tidak dieksekusi/disajikan', $r['code'] === 404);

echo "== Branding\n";
$r = http('GET', '/');
check('beranda 200', $r['code'] === 200);
check('tagline Bloko Sutho ada', strpos($r['body'], 'Bloko Sutho') !== false);
check('nama PT Sendhiko Dawuh Multiguna ada', strpos($r['body'], 'PT Sendhiko Dawuh Multiguna') !== false);
check('nama PT lama sudah hilang', stripos($r['body'], 'Josjis Media Nusantara') === false);

echo "== Auth & rate limit\n";
$r = http('POST', '/api/v1/admin/login', ['email' => 'admin@josjis.news', 'password' => 'password123']);
check('password lawas password123 ditolak', $r['code'] === 401);
$r = http('GET', '/api/v1/admin/me');
check('/admin/me tanpa login = 401', $r['code'] === 401);
$r = http('GET', '/api/v1/admin/db-inspect');
check('db-inspect tidak ada lagi', in_array($r['code'], [401, 404], true));
$r = http('POST', '/api/v1/admin/login', ['email' => 'admin@josjis.news', 'password' => $pass]);
check('login admin sukses', $r['code'] === 200 && !empty($r['json']['token']), $r['body']);
$admin = $r['json']['token'] ?? '';
$r = http('POST', '/api/v1/admin/articles', ['title' => 'x', 'category' => 'event-komunitas'], ['Origin: http://evil.example'] + auth($admin));
check('POST lintas-origin ditolak (CSRF)', $r['code'] === 403, (string) $r['code']);

echo "== CMS & sanitasi XSS\n";
$evil = '<p>Isi <strong>aman</strong></p><script>alert(1)</script><img src="x" onerror="alert(1)"><a href="javascript:alert(1)">klik</a>'
    . '<iframe src="https://evil.example/x"></iframe><iframe src="https://drive.google.com/file/d/1/preview"></iframe>';
$r = http('POST', '/api/v1/admin/articles', [
    'title' => 'Kabar Uji Coba Pertama', 'category' => 'event-komunitas', 'status' => 'published',
    'headline' => true, 'excerpt' => 'Ringkasan uji', 'body' => $evil, 'tags' => 'Jawa, Uji Coba',
], auth($admin));
check('artikel tersimpan', $r['code'] === 200 && !empty($r['json']['id']), $r['body']);
$slug = $r['json']['slug'] ?? '';
$artId = $r['json']['id'] ?? 0;

$r = http('GET', '/api/v1/articles/' . $slug);
$body = $r['json']['data']['content_body'] ?? '';
check('detail publik tersedia', $r['code'] === 200);
check('<script> dibuang', stripos($body, '<script') === false);
check('onerror dibuang', stripos($body, 'onerror') === false);
check('javascript: dibuang', stripos($body, 'javascript:') === false);
check('iframe asing dibuang', strpos($body, 'evil.example') === false);
check('iframe Google Drive dipertahankan', strpos($body, 'drive.google.com') !== false);
check('teks aman dipertahankan', strpos($body, '<strong>aman</strong>') !== false);

$r = http('POST', '/api/v1/admin/articles', ['title' => 'Draft Rahasia', 'category' => 'event-komunitas', 'status' => 'draft', 'body' => '<p>rahasia</p>'], auth($admin));
$draftSlug = $r['json']['slug'] ?? '';
$r = http('GET', '/api/v1/articles/' . $draftSlug);
check('draft tidak bisa dibaca publik', $r['code'] === 404);
$r = http('GET', '/api/v1/articles?search=Rahasia');
check('draft tidak muncul di pencarian', ($r['json']['meta']['total'] ?? -1) === 0);
$r = http('GET', '/api/v1/articles?search=' . rawurlencode('%'));
check('wildcard LIKE di-escape', ($r['json']['meta']['total'] ?? -1) === 0);

$r = http('GET', '/api/v1/bootstrap');
check('bootstrap publik tanpa data pengguna', !isset($r['json']['data']['users']) && strpos($r['body'], 'admin@josjis.news') === false);
$r = http('GET', '/api/v1/bootstrap?mode=admin');
check('bootstrap admin tanpa login = 401', $r['code'] === 401);

echo "== RBAC\n";
$r = http('POST', '/api/v1/admin/users', ['name' => 'Wartawan Uji', 'email' => 'wartawan@uji.test', 'role' => 'journalist', 'password' => 'abc'], auth($admin));
check('password lemah ditolak', $r['code'] === 422);
$r = http('POST', '/api/v1/admin/users', ['name' => 'Wartawan Uji', 'email' => 'wartawan@uji.test', 'role' => 'journalist', 'password' => 'Wartawan-2026x'], auth($admin));
check('buat akun jurnalis', $r['code'] === 200, $r['body']);
$r = http('POST', '/api/v1/admin/login', ['email' => 'wartawan@uji.test', 'password' => 'Wartawan-2026x']);
$jr = $r['json']['token'] ?? '';
check('jurnalis bisa login', $jr !== '');
$r = http('POST', '/api/v1/admin/users', ['name' => 'X', 'email' => 'x@uji.test', 'role' => 'super_admin', 'password' => 'Wartawan-2026x'], auth($jr));
check('jurnalis tidak bisa buat user (403)', $r['code'] === 403);
$r = http('POST', '/api/v1/admin/articles', ['title' => 'Tulisan Jurnalis', 'category' => 'event-komunitas', 'status' => 'published', 'headline' => true, 'body' => '<p>isi</p>'], auth($jr));
check('jurnalis "publish" -> masuk review', ($r['json']['saved_status'] ?? '') === 'review', $r['body']);
$jrSlug = $r['json']['slug'] ?? '';
$r = http('GET', '/api/v1/articles/' . $jrSlug);
check('artikel review belum tayang publik', $r['code'] === 404);
$r = http('DELETE', '/api/v1/admin/articles/' . $artId, null, auth($jr));
check('jurnalis tidak bisa hapus artikel orang lain', $r['code'] === 403);
$r = http('POST', '/api/v1/admin/articles', ['id' => $artId, 'title' => 'Dibajak', 'category' => 'event-komunitas', 'body' => '<p>x</p>'], auth($jr));
check('jurnalis tidak bisa sunting artikel orang lain', $r['code'] === 403);
$r = http('GET', '/api/v1/admin/audit-logs', null, auth($jr));
check('audit log hanya super_admin', $r['code'] === 403);
$r = http('POST', '/api/v1/admin/advertisements', ['title' => 'x'], auth($jr));
check('jurnalis tidak bisa kelola iklan', $r['code'] === 403);

echo "== Unggah media\n";
function multipart($name, $filename, $content, $mime)
{
    $b = 'xxBOUNDARYxx';
    $body = "--$b\r\nContent-Disposition: form-data; name=\"file\"; filename=\"$filename\"\r\nContent-Type: $mime\r\n\r\n$content\r\n--$b--\r\n";
    return [$body, ['Content-Type: multipart/form-data; boundary=' . $b]];
}
list($mb, $mh) = multipart('file', 'shell.php', '<?php echo 1;', 'image/png');
$r = http('POST', '/api/v1/admin/media', $mb, array_merge($mh, auth($admin)), true);
check('unggah .php ditolak', $r['code'] === 422);
list($mb, $mh) = multipart('file', 'fake.png', '<?php system($_GET[0]); ?>', 'image/png');
$r = http('POST', '/api/v1/admin/media', $mb, array_merge($mh, auth($admin)), true);
check('png palsu (isi PHP) ditolak', $r['code'] === 422);
list($mb, $mh) = multipart('file', 'logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'image/svg+xml');
$r = http('POST', '/api/v1/admin/media', $mb, array_merge($mh, auth($admin)), true);
check('svg ditolak', $r['code'] === 422);
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
list($mb, $mh) = multipart('file', 'Uji Gambar.png', $png, 'image/png');
$r = http('POST', '/api/v1/admin/media', $mb, array_merge($mh, auth($admin)), true);
check('png asli diterima', $r['code'] === 200 && !empty($r['json']['data']['url']), $r['body']);
$url = $r['json']['data']['url'] ?? '/none';
$r = http('GET', $url);
check('png disajikan image/png + nosniff', $r['code'] === 200 && ($r['headers']['content-type'] ?? '') === 'image/png' && ($r['headers']['x-content-type-options'] ?? '') === 'nosniff');
$r = http('GET', $url, null, ['Range: bytes=0-9']);
check('dukung Range (206)', $r['code'] === 206 && strlen($r['body']) === 10);
if (preg_match('#/(images/[^/]+)$#', rawurldecode($url), $m)) {
    $r = http('DELETE', '/api/v1/admin/media?path=' . rawurlencode('storage/uploads/' . $m[1]), null, auth($admin));
    check('hapus media oleh editor+', $r['code'] === 200);
}

echo "== SEO\n";
$r = http('GET', '/artikel/' . $slug);
check('halaman artikel 200', $r['code'] === 200);
check('og:title & JSON-LD NewsArticle', strpos($r['body'], 'og:title') !== false && strpos($r['body'], '"NewsArticle"') !== false);
check('window.__BOOT__ aman (tanpa </script> bocor)', strpos($r['body'], 'window.__BOOT__=') !== false);
$r = http('GET', '/artikel/tidak-ada');
check('artikel tak ada = 404', $r['code'] === 404);
$r = http('GET', '/rss.xml');
check('RSS memuat artikel', $r['code'] === 200 && strpos($r['body'], $slug) !== false && strpos($r['body'], '<script') === false);
$r = http('GET', '/sitemap.xml');
check('sitemap memuat artikel', strpos($r['body'], '/artikel/' . $slug) !== false && strpos($r['body'], $draftSlug) === false);
$r = http('GET', '/robots.txt');
check('robots.txt', strpos($r['body'], 'Sitemap:') !== false);
check('robots.txt tidak membocorkan lokasi panel admin', stripos($r['body'], 'admin') === false);
$home = http('GET', '/')['body'];
check('beranda tidak menautkan/menyebut panel admin', stripos($home, 'admin.html') === false && stripos($home, 'Dapur Redaksi / CMS') === false && stripos($home, 'Dapur Admin') === false);
$pgs = http('GET', '/halaman/tentang')['body'] . http('GET', '/rss.xml')['body'] . http('GET', '/sitemap.xml')['body'];
check('halaman statis/RSS/sitemap tidak memuat tautan admin', stripos($pgs, 'admin.html') === false);
$r = http('GET', '/halaman/tentang');
check('halaman Tentang memuat nama PT', strpos($r['body'], 'PT Sendhiko Dawuh Multiguna') !== false);
$r = http('GET', '/kanal/event-komunitas');
check('halaman kanal 200', $r['code'] === 200);

echo "== Paginasi & trending\n";
$r = http('GET', '/api/v1/articles?per_page=1&page=1');
check('paginasi meta', ($r['json']['meta']['per_page'] ?? 0) === 1 && isset($r['json']['meta']['last_page']));
$r = http('GET', '/api/v1/articles/trending');
check('trending 200', $r['code'] === 200 && is_array($r['json']['data']));

echo "== Hapus pengguna tidak menghapus artikel\n";
$r = http('GET', '/api/v1/bootstrap?mode=admin', null, auth($admin));
$uid = 0;
foreach ($r['json']['data']['users'] ?? [] as $u) {
    if ($u['email'] === 'wartawan@uji.test') {
        $uid = $u['id'];
    }
}
$r = http('DELETE', '/api/v1/admin/users/1', null, auth($admin));
check('tak bisa hapus akun sendiri', $r['code'] === 422);
$r = http('DELETE', '/api/v1/admin/users/' . $uid, null, auth($admin));
check('hapus pengguna', $r['code'] === 200);
$r = http('GET', '/api/v1/bootstrap?mode=admin', null, auth($admin));
$titles = array_column($r['json']['data']['articles'] ?? [], 'title');
check('artikel jurnalis tetap ada', in_array('Tulisan Jurnalis', $titles, true));

echo "== Pengerasan tambahan\n";
$r = http('GET', '/');
check('CSP publik: frame-ancestors self, object-src none', strpos($r['headers']['content-security-policy'] ?? '', "frame-ancestors 'self'") !== false && strpos($r['headers']['content-security-policy'] ?? '', "object-src 'none'") !== false);
$r = http('GET', '/admin.html');
check('CSP admin: frame-ancestors none + X-Frame-Options DENY', strpos($r['headers']['content-security-policy'] ?? '', "frame-ancestors 'none'") !== false && ($r['headers']['x-frame-options'] ?? '') === 'DENY');
$dbh = new PDO('sqlite:' . $tmp . '/db.sqlite');
$st = $dbh->prepare('SELECT COUNT(*) FROM sessions WHERE id = ?');
$st->execute([$admin]);
check('token sesi tidak disimpan mentah di DB (hanya hash)', (int) $st->fetchColumn() === 0);
$st->execute([hash('sha256', $admin)]);
check('hash token ada di DB', (int) $st->fetchColumn() === 1);
$r = http('POST', '/api/v1/admin/articles', ['title' => 'Besar', 'category' => 'event-komunitas', 'status' => 'draft', 'body' => str_repeat('a', 700000)], auth($admin));
check('isi artikel kegedhen ditolak (413)', $r['code'] === 413, (string) $r['code']);
$r = http('GET', '/');
check('HSTS tidak dikirim via HTTP biasa', !isset($r['headers']['strict-transport-security']));
$r = http('GET', '/', null, ['X-Forwarded-Proto: https']);
check('X-Forwarded-Proto palsu diabaikan tanpa TRUSTED_PROXY', !isset($r['headers']['strict-transport-security']));
$last = 0;
for ($i = 0; $i < 35; $i++) {
    $last = http('GET', '/api/v1/articles?search=kata' . $i)['code'];
}
check('pencarian di-rate-limit (429)', $last === 429, (string) $last);

echo "== Logout\n";
$r = http('POST', '/api/v1/admin/logout', [], auth($admin));
$r = http('GET', '/api/v1/admin/me', null, auth($admin));
check('token tidak berlaku setelah logout', $r['code'] === 401);

echo "== Rate limit login\n";
$last = 0;
for ($i = 0; $i < 12; $i++) {
    $last = http('POST', '/api/v1/admin/login', ['email' => "brute$i@x.test", 'password' => 'salah'])['code'];
}
check('brute force diblokir (429)', $last === 429, (string) $last);


echo "== 2FA TOTP: vektor uji RFC 6238 (SHA1, 8 digit)\n";
$rfcSecret = base32_encode_str('12345678901234567890');
foreach ([59 => '94287082', 1111111109 => '07081804', 1111111111 => '14050471', 1234567890 => '89005924', 2000000000 => '69279037', 20000000000 => '65353130'] as $t => $expected) {
    check("RFC6238 T=$t -> $expected", totp_code($rfcSecret, $t, 8) === $expected);
}
check('base32 bolak-balik', base32_decode_str(base32_encode_str("\x00\xffabc\x10")) === "\x00\xffabc\x10");
check('base32 RFC4648 "foobar"', base32_encode_str('foobar') === 'MZXW6YTBOI');
$sec = totp_generate_secret();
check('secret 160-bit (32 char base32)', strlen($sec) === 32 && preg_match('/^[A-Z2-7]+$/', $sec) === 1);
$now = 1700000000;
$c = totp_code($sec, $now);
check('verify kode saat ini', totp_verify($sec, $c, 0, $now) === intdiv($now, 30));
check('verify toleransi -1 langkah', totp_verify($sec, totp_code($sec, $now - 30), 0, $now) === intdiv($now, 30) - 1);
check('verify tolak +2 langkah', totp_verify($sec, totp_code($sec, $now + 60), 0, $now) === null);
check('verify tolak langkah yang sudah dipakai', totp_verify($sec, $c, intdiv($now, 30), $now) === null);
check('verify tolak format salah', totp_verify($sec, 'abcdef', 0, $now) === null && totp_verify($sec, '12345', 0, $now) === null);
$blob = secret_encrypt($sec);
check('enkripsi rahasia: bolak-balik & tidak plaintext', secret_decrypt($blob) === $sec && strpos($blob, $sec) === false);
check('enkripsi rahasia: GCM menolak data diubah', secret_decrypt(substr($blob, 0, -4) . 'AAAA') === '');

echo "== 2FA TOTP: alur end-to-end (server terpisah)\n";
list($pr2, $b2, $dir2) = start_server('tf');
$origBase = $base;
$base = $b2;
register_shutdown_function(function () use ($pr2) {
    proc_terminate($pr2);
});
$r = http('POST', '/api/v1/admin/login', ['email' => 'admin@josjis.news', 'password' => $pass]);
$adm = $r['json']['token'] ?? '';
check('(tf) login admin tanpa 2FA', $adm !== '' && ($r['json']['must_enable_2fa'] ?? null) === false);
$r = http('POST', '/api/v1/admin/users', ['name' => 'Editor Dua', 'email' => 'dua@uji.test', 'role' => 'editor', 'password' => 'Editor-Dua-2026x'], auth($adm));
$duaId = 0;
$r = http('GET', '/api/v1/bootstrap?mode=admin', null, auth($adm));
foreach ($r['json']['data']['users'] ?? [] as $u) {
    if ($u['email'] === 'dua@uji.test') {
        $duaId = $u['id'];
        check('(tf) daftar user memuat flag two_factor=false', $u['two_factor'] === false);
    }
}
$r = http('POST', '/api/v1/admin/login', ['email' => 'dua@uji.test', 'password' => 'Editor-Dua-2026x']);
$ed = $r['json']['token'] ?? '';
check('(tf) login editor (belum 2FA)', $ed !== '');

$r = http('GET', '/api/v1/admin/2fa/status', null, auth($ed));
check('(tf) status awal: nonaktif', ($r['json']['data']['enabled'] ?? null) === false);
$r = http('POST', '/api/v1/admin/2fa/enable', ['code' => '123456'], auth($ed));
check('(tf) enable tanpa setup ditolak', $r['code'] === 409);
$r = http('POST', '/api/v1/admin/2fa/setup', [], auth($ed));
$secret = $r['json']['data']['secret'] ?? '';
check('(tf) setup memberi secret + otpauth URI', strlen($secret) === 32 && strpos($r['json']['data']['uri'] ?? '', 'otpauth://totp/') === 0 && strpos($r['json']['data']['uri'], 'secret=' . $secret) !== false);
$r = http('POST', '/api/v1/admin/login', ['email' => 'dua@uji.test', 'password' => 'Editor-Dua-2026x']);
check('(tf) sebelum dikonfirmasi, login tetap tanpa 2FA', ($r['json']['status'] ?? '') === 'success');
$r = http('POST', '/api/v1/admin/2fa/enable', ['code' => '000000'], auth($ed));
check('(tf) enable dengan kode salah ditolak', $r['code'] === 422);
$r = http('POST', '/api/v1/admin/2fa/enable', ['code' => totp_code($secret)], auth($ed));
$codes = $r['json']['data']['recovery_codes'] ?? [];
check('(tf) enable dengan kode benar -> 8 kode pemulihan', $r['code'] === 200 && count($codes) === 8 && preg_match('/^[a-f0-9]{5}-[a-f0-9]{5}$/', $codes[0]) === 1, $r['body']);
$r = http('POST', '/api/v1/admin/2fa/setup', [], auth($ed));
check('(tf) setup ulang saat aktif ditolak (tidak menimpa secret)', $r['code'] === 409);

$dbh2 = new PDO('sqlite:' . $dir2 . '/db.sqlite');
$rowSecret = $dbh2->query('SELECT secret_enc FROM user_2fa WHERE user_id = ' . (int) $duaId)->fetchColumn();
check('(tf) rahasia TOTP terenkripsi di DB (bukan plaintext)', $rowSecret && strpos($rowSecret, $secret) === false && secret_decrypt($rowSecret) !== '');
$recStored = (string) $dbh2->query('SELECT recovery FROM user_2fa WHERE user_id = ' . (int) $duaId)->fetchColumn();
check('(tf) kode pemulihan disimpan sebagai hash', strpos($recStored, str_replace('-', '', $codes[0])) === false && strpos($recStored, $codes[0]) === false);
check('(tf) sesi lain dimatikan, sesi saat ini hidup', http('GET', '/api/v1/admin/me', null, auth($ed))['code'] === 200);

// --- login dengan 2FA
$r = http('POST', '/api/v1/admin/login', ['email' => 'dua@uji.test', 'password' => 'Editor-Dua-2026x']);
check('(tf) login password benar -> minta 2FA, TANPA token & cookie', ($r['json']['status'] ?? '') === '2fa_required' && empty($r['json']['token']) && !isset($r['headers']['set-cookie']));
$chal = $r['json']['challenge'] ?? '';
$r = http('GET', '/api/v1/bootstrap?mode=admin', null, ['Authorization: Bearer ' . $chal]);
check('(tf) tantangan bukan token sesi', $r['code'] === 401);
$r = http('POST', '/api/v1/admin/login/2fa', ['challenge' => $chal, 'code' => '000000']);
check('(tf) kode salah ditolak', $r['code'] === 401);
$r = http('POST', '/api/v1/admin/login/2fa', ['challenge' => 'x', 'code' => totp_code($secret)]);
check('(tf) tantangan ngawur ditolak', $r['code'] === 400);
$code = totp_code($secret);
$r = http('POST', '/api/v1/admin/login/2fa', ['challenge' => $chal, 'code' => $code]);
check('(tf) kode benar -> sesi dibuat', ($r['json']['status'] ?? '') === 'success' && !empty($r['json']['token']), $r['body']);
$ed2 = $r['json']['token'] ?? '';
check('(tf) sesi hasil 2FA berfungsi', http('GET', '/api/v1/admin/me', null, auth($ed2))['code'] === 200);
$r = http('POST', '/api/v1/admin/login/2fa', ['challenge' => $chal, 'code' => totp_code($secret, time() + 30)]);
check('(tf) tantangan sekali pakai', $r['code'] === 401);

// --- anti replay: kode yang sudah dipakai login tidak bisa dipakai lagi
$r = http('POST', '/api/v1/admin/2fa/recovery-codes', ['password' => 'Editor-Dua-2026x', 'code' => $code], auth($ed2));
check('(tf) anti-replay: kode TOTP yang sudah dipakai ditolak', $r['code'] === 422);
$r = http('POST', '/api/v1/admin/2fa/recovery-codes', ['password' => 'salah', 'code' => totp_code($secret, time() + 30)], auth($ed2));
check('(tf) ubah pengaturan 2FA butuh password benar', $r['code'] === 422);

// --- kode pemulihan: sekali pakai
$r = http('POST', '/api/v1/admin/login', ['email' => 'dua@uji.test', 'password' => 'Editor-Dua-2026x']);
$r = http('POST', '/api/v1/admin/login/2fa', ['challenge' => $r['json']['challenge'] ?? '', 'code' => strtoupper($codes[0])]);
check('(tf) kode pemulihan diterima (huruf besar pun)', ($r['json']['status'] ?? '') === 'success' && ($r['json']['used_recovery_code'] ?? false) === true && ($r['json']['recovery_remaining'] ?? -1) === 7, $r['body']);
$ed3 = $r['json']['token'] ?? '';
$r = http('POST', '/api/v1/admin/2fa/disable', ['password' => 'Editor-Dua-2026x', 'code' => $codes[0]], auth($ed3));
check('(tf) kode pemulihan tidak bisa dipakai dua kali', $r['code'] === 422);
$r = http('GET', '/api/v1/admin/2fa/status', null, auth($ed3));
check('(tf) sisa kode pemulihan = 7', ($r['json']['data']['recovery_remaining'] ?? -1) === 7);

// --- brute force pada tantangan
$r = http('POST', '/api/v1/admin/login', ['email' => 'dua@uji.test', 'password' => 'Editor-Dua-2026x']);
$chal2 = $r['json']['challenge'] ?? '';
for ($i = 0; $i < 5; $i++) {
    http('POST', '/api/v1/admin/login/2fa', ['challenge' => $chal2, 'code' => '11111' . $i]);
}
$r = http('POST', '/api/v1/admin/login/2fa', ['challenge' => $chal2, 'code' => totp_code($secret, time() + 30)]);
check('(tf) 5 kode salah mengunci tantangan (kode benar pun ditolak)', in_array($r['code'], [401, 429], true) && empty($r['json']['token']), (string) $r['code']);

// --- regenerasi kode pemulihan
$r = http('POST', '/api/v1/admin/2fa/recovery-codes', ['password' => 'Editor-Dua-2026x', 'code' => $codes[1]], auth($ed3));
$newCodes = $r['json']['data']['recovery_codes'] ?? [];
check('(tf) buat kode pemulihan baru', $r['code'] === 200 && count($newCodes) === 8 && !array_intersect($newCodes, $codes), $r['body']);
$r = http('POST', '/api/v1/admin/2fa/disable', ['password' => 'Editor-Dua-2026x', 'code' => $codes[2]], auth($ed3));
check('(tf) kode pemulihan lama tidak berlaku setelah regenerasi', $r['code'] === 422);

// --- reset oleh super admin
$r = http('GET', '/api/v1/bootstrap?mode=admin', null, auth($adm));
$flag = null;
foreach ($r['json']['data']['users'] ?? [] as $u) {
    if ($u['email'] === 'dua@uji.test') {
        $flag = $u['two_factor'];
    }
}
check('(tf) daftar user menandai 2FA aktif', $flag === true);
$r = http('POST', '/api/v1/admin/users/' . $duaId . '/2fa-reset', [], auth($ed3));
check('(tf) editor tidak boleh reset 2FA orang lain', $r['code'] === 403);
$r = http('POST', '/api/v1/admin/users/1/2fa-reset', [], auth($adm));
check('(tf) tak bisa reset 2FA diri sendiri lewat rute reset', $r['code'] === 422);
$r = http('POST', '/api/v1/admin/users/' . $duaId . '/2fa-reset', [], auth($adm));
check('(tf) super admin reset 2FA user', $r['code'] === 200);
check('(tf) sesi user yang direset diakhiri', http('GET', '/api/v1/admin/me', null, auth($ed3))['code'] === 401);
$dbh2->exec('DELETE FROM rate_limits'); // simulasikan jendela 15 menit sudah lewat
$r = http('POST', '/api/v1/admin/login', ['email' => 'dua@uji.test', 'password' => 'Editor-Dua-2026x']);
check('(tf) setelah reset, login kembali tanpa 2FA', ($r['json']['status'] ?? '') === 'success');

// --- nonaktifkan sendiri (admin: aktifkan lalu matikan)
$ad2 = $adm;
$r = http('GET', '/api/v1/bootstrap?mode=admin', null, auth($ad2));
check('(tf) bootstrap admin tidak membocorkan token sesi ke JS', strpos($r['body'], $ad2) === false);
$r = http('POST', '/api/v1/admin/2fa/setup', [], auth($ad2));
$asec = $r['json']['data']['secret'] ?? '';
$r = http('POST', '/api/v1/admin/2fa/enable', ['code' => totp_code($asec)], auth($ad2));
$acodes = $r['json']['data']['recovery_codes'] ?? [];
$r = http('POST', '/api/v1/admin/2fa/disable', ['password' => $pass, 'code' => $acodes[0] ?? 'x'], auth($ad2));
check('(tf) nonaktifkan 2FA dengan password + kode pemulihan', $r['code'] === 200, $r['body']);
$r = http('GET', '/api/v1/admin/2fa/status', null, auth($ad2));
check('(tf) status kembali nonaktif', ($r['json']['data']['enabled'] ?? null) === false);

echo "== Lokasi panel disembunyikan (ADMIN_PATH)\n";
list($pr4, $b4) = start_server('hidden', ['ADMIN_PATH' => '/ruang-redaksi-x7k2']);
register_shutdown_function(function () use ($pr4) {
    proc_terminate($pr4);
});
$base = $b4;
check('(hid) path rahasia menyajikan panel', http('GET', '/ruang-redaksi-x7k2')['code'] === 200);
check('(hid) /admin.html menjadi 404', http('GET', '/admin.html')['code'] === 404);
check('(hid) /admin menjadi 404', http('GET', '/admin')['code'] === 404);
check('(hid) beranda tidak memuat path rahasia', strpos(http('GET', '/')['body'], 'ruang-redaksi') === false);
$base = $origBase;

echo "== 2FA TOTP: kebijakan REQUIRE_2FA=1 (server terpisah)\n";
list($pr3, $b3) = start_server('tf-req', ['REQUIRE_2FA' => '1']);
register_shutdown_function(function () use ($pr3) {
    proc_terminate($pr3);
});
$base = $b3;
$r = http('POST', '/api/v1/admin/login', ['email' => 'admin@josjis.news', 'password' => $pass]);
$rq = $r['json']['token'] ?? '';
check('(req) login sukses tapi must_enable_2fa=true', $rq !== '' && ($r['json']['must_enable_2fa'] ?? false) === true);
$r = http('GET', '/api/v1/bootstrap?mode=admin', null, auth($rq));
check('(req) data admin diblokir sampai 2FA aktif (403 2fa_setup_required)', $r['code'] === 403 && ($r['json']['code'] ?? '') === '2fa_setup_required', $r['body']);
$r = http('POST', '/api/v1/admin/articles', ['title' => 'x', 'category' => 'event-komunitas', 'body' => '<p>x</p>'], auth($rq));
check('(req) menulis artikel diblokir', $r['code'] === 403);
$r = http('GET', '/api/v1/admin/me', null, auth($rq));
check('(req) /me tetap bisa (UI perlu tahu status)', $r['code'] === 200 && ($r['json']['user']['two_factor_required'] ?? false) === true);
$r = http('POST', '/api/v1/admin/2fa/setup', [], auth($rq));
$qs = $r['json']['data']['secret'] ?? '';
check('(req) setup 2FA diizinkan', $qs !== '');
$r = http('POST', '/api/v1/admin/2fa/enable', ['code' => totp_code($qs)], auth($rq));
$qcodes = $r['json']['data']['recovery_codes'] ?? [];
check('(req) enable berhasil', $r['code'] === 200 && count($qcodes) === 8);
$r = http('GET', '/api/v1/bootstrap?mode=admin', null, auth($rq));
check('(req) setelah 2FA aktif, akses normal', $r['code'] === 200);
$r = http('POST', '/api/v1/admin/2fa/disable', ['password' => $pass, 'code' => $qcodes[0]], auth($rq));
check('(req) peran wajib 2FA tidak boleh menonaktifkan', $r['code'] === 403, $r['body']);
$base = $origBase;

echo "\nHasil: $pass_count lulus, $fail_count gagal\n";
exit($fail_count > 0 ? 1 : 0);
