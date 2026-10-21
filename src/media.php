<?php
/**
 * Pustaka media: unggah aman, daftar, hapus, dan penyajian berkas (dengan Range).
 */

require_once __DIR__ . '/helpers.php';

/** ekstensi => [folder, mime, ukuran maks (byte)] */
function media_rules()
{
    return [
        'jpg'  => ['images', 'image/jpeg', 8 * 1048576],
        'jpeg' => ['images', 'image/jpeg', 8 * 1048576],
        'png'  => ['images', 'image/png', 8 * 1048576],
        'gif'  => ['images', 'image/gif', 8 * 1048576],
        'webp' => ['images', 'image/webp', 8 * 1048576],
        'mp4'  => ['videos', 'video/mp4', 100 * 1048576],
        'webm' => ['videos', 'video/webm', 100 * 1048576],
        'pdf'  => ['documents', 'application/pdf', 20 * 1048576],
    ];
}

function media_type_of($folder)
{
    return ['images' => 'image', 'videos' => 'video', 'documents' => 'pdf'][$folder] ?? 'file';
}

function scan_media()
{
    $items = [];
    foreach (['images', 'videos', 'documents'] as $folder) {
        $dir = upload_dir() . '/' . $folder;
        if (!is_dir($dir)) {
            continue;
        }
        foreach (scandir($dir) ?: [] as $f) {
            $full = $dir . '/' . $f;
            if ($f[0] === '.' || !is_file($full)) {
                continue;
            }
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (!isset(media_rules()[$ext])) {
                continue;
            }
            $items[] = [
                'name'  => $f,
                'type'  => media_type_of($folder),
                'size'  => human_size(filesize($full)),
                'path'  => 'storage/uploads/' . $folder . '/' . $f,
                'url'   => '/storage/uploads/' . $folder . '/' . rawurlencode($f),
                'mtime' => filemtime($full) ?: 0,
            ];
        }
    }
    usort($items, function ($a, $b) {
        return $b['mtime'] <=> $a['mtime'];
    });
    return $items;
}

function save_uploaded_media($file)
{
    if (!$file || !isset($file['error']) || is_array($file['error'])) {
        return ['error' => 'Ora ana berkas sing diunggah.'];
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        return ['error' => 'Ukuran berkas ngluwihi watesan server.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return ['error' => 'Unggahan gagal. Coba maneh.'];
    }

    $orig = (string) $file['name'];
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $rules = media_rules();
    if (!isset($rules[$ext])) {
        return ['error' => 'Jinis berkas ora diidini. Diidini: ' . implode(', ', array_keys($rules)) . '.'];
    }
    list($folder, $expectedMime, $maxBytes) = $rules[$ext];
    $size = (int) $file['size'];
    if ($size <= 0 || $size > $maxBytes) {
        return ['error' => 'Ukuran berkas maksimal ' . round($maxBytes / 1048576) . ' MB kanggo jinis iki.'];
    }

    // Verifikasi isi berkas sebenarnya, bukan hanya ekstensi.
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $realMime = $finfo->file($file['tmp_name']);
    $okMime = $realMime === $expectedMime;
    if (!$okMime) {
        return ['error' => 'Isi berkas ora cocog karo ekstensine (' . $realMime . ').'];
    }
    if ($folder === 'images' && @getimagesize($file['tmp_name']) === false) {
        return ['error' => 'Berkas gambar rusak utawa ora valid.'];
    }

    $base = slugify(pathinfo($orig, PATHINFO_FILENAME));
    $name = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '-' . substr($base, 0, 60) . '.' . $ext;
    $dir = upload_dir() . '/' . $folder;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['error' => 'Gagal nyimpen berkas ing server.'];
    }
    @chmod($dest, 0644);

    return [
        'name' => $name,
        'type' => media_type_of($folder),
        'size' => human_size(filesize($dest)),
        'path' => 'storage/uploads/' . $folder . '/' . $name,
        'url'  => '/storage/uploads/' . $folder . '/' . rawurlencode($name),
    ];
}

function delete_media($path)
{
    if (!preg_match('#^(?:storage/uploads/)?(images|videos|documents)/([A-Za-z0-9._\-]+)$#', $path, $m)) {
        return false;
    }
    $full = upload_dir() . '/' . $m[1] . '/' . $m[2];
    return is_file($full) && unlink($full);
}

/** Sajikan berkas dari /storage/uploads/{folder}/{nama} dengan header aman & dukungan Range. */
function serve_upload($uri)
{
    if (!preg_match('#^/storage/uploads/(images|videos|documents)/([^/]+)$#', $uri, $m)) {
        return false;
    }
    $name = rawurldecode($m[2]);
    if (!preg_match('/^[A-Za-z0-9._\-]+$/', $name) || $name[0] === '.') {
        return false;
    }
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $rules = media_rules();
    $full = upload_dir() . '/' . $m[1] . '/' . $name;
    if (!isset($rules[$ext]) || $rules[$ext][0] !== $m[1] || !is_file($full)) {
        return false;
    }

    $size = filesize($full);
    $start = 0;
    $end = $size - 1;
    $code = 200;
    if (!empty($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $r)) {
        if ($r[1] !== '') {
            $start = (int) $r[1];
            $end = $r[2] !== '' ? min((int) $r[2], $size - 1) : $size - 1;
        } elseif ($r[2] !== '') {
            $start = max(0, $size - (int) $r[2]);
        }
        if ($start > $end || $start >= $size) {
            http_response_code(416);
            header("Content-Range: bytes */$size");
            exit;
        }
        $code = 206;
    }

    http_response_code($code);
    header('Content-Type: ' . $rules[$ext][1]);
    header('X-Content-Type-Options: nosniff');
    if ($ext !== 'pdf') {
        header("Content-Security-Policy: default-src 'none'; img-src 'self'; media-src 'self'; style-src 'unsafe-inline'; sandbox");
    }
    header('Cache-Control: public, max-age=2592000, immutable');
    header('Accept-Ranges: bytes');
    header('Content-Length: ' . ($end - $start + 1));
    if ($code === 206) {
        header("Content-Range: bytes $start-$end/$size");
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
        exit;
    }
    $fp = fopen($full, 'rb');
    fseek($fp, $start);
    $left = $end - $start + 1;
    while ($left > 0 && !feof($fp)) {
        $chunk = fread($fp, min(8192, $left));
        echo $chunk;
        $left -= strlen($chunk);
    }
    fclose($fp);
    exit;
}
