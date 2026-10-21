<?php
/**
 * Pembuat basis data SQLite bersih (CLI).
 *
 *   php database/build_sqlite_db.php            # buat jika belum ada
 *   php database/build_sqlite_db.php --force    # timpa (HAPUS semua data!)
 *
 * Kata sandi admin: env ADMIN_PASSWORD, atau dibuat acak dan dicetak di sini.
 */

require_once __DIR__ . '/../src/db.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$path = db_path();
$force = in_array('--force', $argv, true);

if (is_file($path) && !$force) {
    fwrite(STDERR, "Basis data sudah ada di {$path}. Pakai --force untuk menimpa.\n");
    exit(1);
}
if (is_file($path) && $force) {
    unlink($path);
}

$res = josjis_build_database($path);
echo "Basis data dibuat: {$path}\n";
echo "Admin  : {$res['email']}\n";
echo "Sandi  : {$res['password']}\n";
echo "(Disimpan juga di {$res['note']}; hapus setelah login pertama.)\n";
