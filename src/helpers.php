<?php
/**
 * Fungsi bantu umum: JSON, validasi, rate limit, sanitasi HTML, audit.
 */

require_once __DIR__ . '/config.php';

function e($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_out($payload, $code = 200)
{
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
    }
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | (defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0);
    echo json_encode($payload, $flags);
    exit;
}

function json_error($message, $code = 400, array $extra = [])
{
    json_out(array_merge(['status' => 'error', 'message' => $message], $extra), $code);
}

function request_json()
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $raw = file_get_contents('php://input');
    if ($raw !== false && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $cache = $decoded;
        }
    }
    return $cache = $_POST;
}

function client_ip()
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    // Hanya percaya X-Forwarded-For bila TRUSTED_PROXY=1 (di belakang Nginx/Cloudflare).
    if (env('TRUSTED_PROXY') === '1') {
        $xff = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($xff !== '') {
            $first = trim(explode(',', $xff)[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }
    }
    return $ip;
}

/**
 * Pembatas laju berbasis SQLite. Mengembalikan true bila masih diizinkan.
 */
function rate_limit(PDO $pdo, $bucket, $max, $windowSeconds)
{
    $now = time();
    $bucket = substr($bucket, 0, 190);
    if (mt_rand(1, 50) === 1) {
        $pdo->prepare('DELETE FROM rate_limits WHERE window_start < ?')->execute([$now - 86400]);
    }
    $st = $pdo->prepare('SELECT hits, window_start FROM rate_limits WHERE bucket = ?');
    $st->execute([$bucket]);
    $row = $st->fetch();
    if (!$row || ($now - (int) $row['window_start']) >= $windowSeconds) {
        $pdo->prepare('INSERT OR REPLACE INTO rate_limits (bucket, hits, window_start) VALUES (?, 1, ?)')->execute([$bucket, $now]);
        return true;
    }
    if ((int) $row['hits'] >= $max) {
        return false;
    }
    $pdo->prepare('UPDATE rate_limits SET hits = hits + 1 WHERE bucket = ?')->execute([$bucket]);
    return true;
}

function audit(PDO $pdo, $userId, $action, $detail = '')
{
    try {
        $pdo->prepare('INSERT INTO audit_logs (user_id, action, detail, ip_address) VALUES (?,?,?,?)')
            ->execute([$userId, $action, substr((string) $detail, 0, 500), client_ip()]);
    } catch (Throwable $e) {
        // Audit tidak boleh menggagalkan aksi utama.
    }
}

function slugify($text)
{
    $text = trim((string) $text);
    if (function_exists('iconv')) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($t !== false && $t !== '') {
            $text = $t;
        }
    }
    $text = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $text));
    $text = trim($text, '-');
    return substr($text !== '' ? $text : 'kabar', 0, 120);
}

function unique_slug(PDO $pdo, $table, $slug, $ignoreId = 0)
{
    $base = $slug;
    $i = 1;
    $st = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE slug = ? AND id != ?");
    while (true) {
        $st->execute([$slug, $ignoreId]);
        if ((int) $st->fetchColumn() === 0) {
            return $slug;
        }
        $i++;
        $slug = $base . '-' . $i;
    }
}

function relative_time($datetime = null)
{
    if (!$datetime) {
        return 'Mentas wae';
    }
    $ts = strtotime((string) $datetime . ' UTC');
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
    if ($days < 30) {
        return $days . ' dina kepungkur';
    }
    return date('d-m-Y', $ts);
}

function human_size($bytes)
{
    $kb = max(1, (int) round($bytes / 1024));
    return $kb >= 1024 ? round($kb / 1024, 1) . ' MB' : $kb . ' KB';
}

/* ------------------------------------------------------------------ */
/* Sanitasi HTML artikel (allowlist)                                   */
/* ------------------------------------------------------------------ */

function safe_url($url, $allowRelative = true, array $extraSchemes = [])
{
    $url = trim((string) $url);
    $clean = preg_replace('/[\x00-\x20\x7f]+/', '', $url);
    if ($clean === '') {
        return '';
    }
    if ($allowRelative && $clean[0] === '/' && (strlen($clean) === 1 || $clean[1] !== '/')) {
        return $url;
    }
    if ($allowRelative && $clean[0] === '#') {
        return $url;
    }
    $schemes = array_merge(['http', 'https'], $extraSchemes);
    if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $clean, $m)) {
        return in_array(strtolower($m[1]), $schemes, true) ? $url : '';
    }
    return '';
}

function iframe_src_allowed($src)
{
    $src = safe_url($src, true);
    if ($src === '') {
        return '';
    }
    if ($src[0] === '/') {
        return str_starts_with($src, '/storage/uploads/') ? $src : '';
    }
    $host = strtolower((string) parse_url($src, PHP_URL_HOST));
    $allowed = ['drive.google.com', 'docs.google.com', 'www.youtube.com', 'www.youtube-nocookie.com', 'player.vimeo.com'];
    return (in_array($host, $allowed, true) && str_starts_with(strtolower($src), 'https://')) ? $src : '';
}

/**
 * Saring atribut style: hanya properti & nilai yang aman (allowlist ketat).
 * Dibutuhkan editor (rata kanan-kiri, warna, ukuran huruf, lebar gambar/tabel, dsb).
 * Tidak ada url(), expression(), position, atau apa pun yang bisa menimpa UI situs.
 */
function sanitize_css($css)
{
    $css = (string) $css;
    if (strlen($css) > 1200 || preg_match('/(?:url\s*\(|expression|javascript|vbscript|behavior|binding|@|\\\\|\/\*|<|>)/i', $css)) {
        return '';
    }
    $len = '-?\d+(?:\.\d+)?(?:px|em|rem|%|pt|ex|ch|vw|vh)?';
    $lenAuto = '(?:' . $len . '|auto)';
    $color = '(?:#[0-9a-f]{3,8}|(?:rgb|hsl)a?\(\s*[\d.%\s,\/-]+\)|[a-z]{3,20})';
    $boxLen = '(?:' . $lenAuto . '\s*){1,4}';
    $loose = '[A-Za-z0-9 #%.,()\/-]{1,80}';
    $fontFam = '[A-Za-z0-9 ,\'"-]{1,100}';
    $borderStyle = '(?:none|solid|dotted|dashed|double|groove|ridge|inset|outset|hidden)';

    $rules = [
        'text-align'       => '(?:left|right|center|justify|start|end)',
        'color'            => $color,
        'background-color' => $color,
        'font-size'        => '(?:' . $len . '|xx-small|x-small|small|medium|large|x-large|xx-large|smaller|larger)',
        'font-family'      => $fontFam,
        'font-weight'      => '(?:normal|bold|bolder|lighter|[1-9]00)',
        'font-style'       => '(?:normal|italic|oblique)',
        'text-decoration'  => '(?:(?:none|underline|line-through|overline)\s*)+',
        'line-height'      => '(?:\d+(?:\.\d+)?|' . $len . '|normal)',
        'width'            => $lenAuto,
        'height'           => $lenAuto,
        'min-width'        => $lenAuto,
        'max-width'        => '(?:' . $len . '|none)',
        'margin'           => $boxLen,
        'margin-left'      => $lenAuto,
        'margin-right'     => $lenAuto,
        'margin-top'       => $lenAuto,
        'margin-bottom'    => $lenAuto,
        'padding'          => $boxLen,
        'padding-left'     => $len,
        'padding-right'    => $len,
        'padding-top'      => $len,
        'padding-bottom'   => $len,
        'float'            => '(?:left|right|none)',
        'display'          => '(?:block|inline|inline-block|table|table-cell|none)',
        'vertical-align'   => '(?:top|middle|bottom|baseline|super|sub|text-top|text-bottom)',
        'list-style-type'  => '[a-z-]{3,30}',
        'border'           => $loose,
        'border-top'       => $loose,
        'border-bottom'    => $loose,
        'border-left'      => $loose,
        'border-right'     => $loose,
        'border-style'     => '(?:' . $borderStyle . '\s*){1,4}',
        'border-width'     => $boxLen,
        'border-color'     => '(?:' . $color . '\s*){1,4}',
        'border-collapse'  => '(?:collapse|separate)',
        'aspect-ratio'     => '\d+(?:\.\d+)?\s*\/\s*\d+(?:\.\d+)?',
        'object-fit'       => '(?:fill|contain|cover|none|scale-down)',
        'white-space'      => '(?:normal|nowrap|pre|pre-wrap|pre-line)',
    ];

    $out = '';
    foreach (explode(';', $css) as $decl) {
        if (strpos($decl, ':') === false) {
            continue;
        }
        $parts = explode(':', $decl, 2);
        $prop = strtolower(trim($parts[0]));
        $val = trim($parts[1]);
        if ($val === '' || !isset($rules[$prop])) {
            continue;
        }
        if (preg_match('/^' . $rules[$prop] . '$/i', $val)) {
            $out .= $prop . ':' . $val . ';';
        }
    }
    return $out;
}

/** Tulis node DOM yang sudah bersih menjadi HTML (deterministik, tanpa pemformatan libxml). */
function sanitize_serialize(DOMNode $n)
{
    static $void = ['br' => 1, 'hr' => 1, 'img' => 1, 'input' => 1, 'col' => 1, 'source' => 1];
    if ($n->nodeType === XML_TEXT_NODE) {
        return str_replace("\xC2\xA0", '&nbsp;', htmlspecialchars($n->nodeValue, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }
    if ($n->nodeType !== XML_ELEMENT_NODE) {
        return '';
    }
    $tag = strtolower($n->nodeName);
    $out = '<' . $tag;
    foreach ($n->attributes as $a) {
        $out .= ' ' . $a->nodeName . '="' . htmlspecialchars($a->nodeValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
    }
    $out .= '>';
    if (isset($void[$tag])) {
        return $out;
    }
    foreach ($n->childNodes as $c) {
        $out .= sanitize_serialize($c);
    }
    return $out . '</' . $tag . '>';
}

function sanitize_html($html)
{
    $html = (string) $html;
    if (trim($html) === '') {
        return '';
    }

    $allowedTags = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'a', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'blockquote', 'figure', 'figcaption', 'img', 'video', 'source', 'iframe', 'table', 'thead',
        'tbody', 'tfoot', 'tr', 'th', 'td', 'caption', 'colgroup', 'col', 'hr', 'span', 'div', 'code', 'pre', 'mark',
        'sub', 'sup', 'small', 'label', 'input', 'del', 'ins',
    ];
    $dropWithContent = ['script', 'style', 'object', 'embed', 'applet', 'link', 'meta', 'form',
        'button', 'textarea', 'select', 'svg', 'math', 'noscript', 'template', 'base', 'title'];
    $globalAttrs = ['class', 'style'];
    $tagAttrs = [
        'a'          => ['href', 'target', 'rel', 'title'],
        'img'        => ['src', 'alt', 'width', 'height', 'loading', 'title'],
        'video'      => ['src', 'controls', 'preload', 'poster', 'width', 'height'],
        'source'     => ['src', 'type'],
        'iframe'     => ['src', 'width', 'height', 'allowfullscreen', 'allow', 'loading', 'title'],
        'th'         => ['colspan', 'rowspan'],
        'td'         => ['colspan', 'rowspan'],
        'col'        => ['span'],
        'ol'         => ['start', 'reversed'],
        'input'      => ['type', 'checked', 'disabled'],
        'div'        => ['data-oembed-url'],
    ];

    $prev = libxml_use_internal_errors(true);
    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->loadHTML('<?xml encoding="UTF-8"><body><div id="josjis-root">' . $html . '</div></body>', LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    $root = $doc->getElementById('josjis-root');
    if (!$root) {
        return e(strip_tags($html));
    }

    $clean = function (DOMNode $node) use (&$clean, $allowedTags, $dropWithContent, $globalAttrs, $tagAttrs) {
        $children = [];
        foreach ($node->childNodes as $c) {
            $children[] = $c;
        }
        foreach ($children as $child) {
            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
                $node->removeChild($child);
                continue;
            }
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }
            $tag = strtolower($child->nodeName);

            if (in_array($tag, $dropWithContent, true)) {
                $node->removeChild($child);
                continue;
            }
            $clean($child);

            if (!in_array($tag, $allowedTags, true)) {
                // Buka bungkusnya, pertahankan isi yang sudah bersih.
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            $permitted = array_merge($globalAttrs, $tagAttrs[$tag] ?? []);
            $attrs = [];
            foreach ($child->attributes as $a) {
                $attrs[] = $a->nodeName;
            }
            foreach ($attrs as $name) {
                $lname = strtolower($name);
                $val = $child->getAttribute($name);
                $child->removeAttribute($name);
                if (!in_array($lname, $permitted, true)) {
                    continue;
                }
                if ($lname === 'style') {
                    $val = sanitize_css($val);
                    if ($val === '') {
                        continue;
                    }
                } elseif ($lname === 'data-oembed-url') {
                    $val = iframe_src_allowed($val);
                    if ($val === '') {
                        continue;
                    }
                } elseif ($lname === 'class') {
                    $val = trim(preg_replace('/[^A-Za-z0-9_\- ]+/', '', $val));
                    if ($val === '') {
                        continue;
                    }
                } elseif ($lname === 'href') {
                    $val = safe_url($val, true, ['mailto', 'tel']);
                    if ($val === '') {
                        continue;
                    }
                } elseif ($lname === 'src' || $lname === 'poster') {
                    $val = $tag === 'iframe' ? iframe_src_allowed($val) : safe_url($val, true);
                    if ($val === '') {
                        continue;
                    }
                } elseif (in_array($lname, ['width', 'height', 'colspan', 'rowspan', 'start'], true)) {
                    if (!preg_match('/^\d{1,4}%?$/', $val)) {
                        continue;
                    }
                } elseif ($lname === 'target') {
                    $val = $val === '_blank' ? '_blank' : '';
                    if ($val === '') {
                        continue;
                    }
                } elseif ($lname === 'loading') {
                    $val = $val === 'eager' ? 'eager' : 'lazy';
                } elseif ($lname === 'preload') {
                    $val = in_array($val, ['none', 'metadata', 'auto'], true) ? $val : 'metadata';
                } elseif ($lname === 'type') {
                    if ($tag === 'input') {
                        if ($val !== 'checkbox') {
                            continue;
                        }
                    } elseif (!preg_match('#^(video|audio)/[a-z0-9.+\-]+$#i', $val)) {
                        continue;
                    }
                } elseif ($lname === 'allow') {
                    $val = trim(preg_replace('/[^a-z0-9;\- ]+/i', '', $val));
                }
                $child->setAttribute($lname, $val);
            }

            if ($tag === 'input') {
                if (strtolower($child->getAttribute('type')) !== 'checkbox') {
                    $node->removeChild($child);
                    continue;
                }
                $child->setAttribute('type', 'checkbox');
                $child->setAttribute('disabled', 'disabled');
            } elseif ($tag === 'a') {
                $href = $child->getAttribute('href');
                if ($href === '') {
                    // Tautan tanpa href yang aman: jadikan teks biasa.
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    continue;
                }
                if (preg_match('#^https?://#i', $href)) {
                    $child->setAttribute('rel', 'noopener noreferrer nofollow ugc');
                    $child->setAttribute('target', '_blank');
                }
            } elseif ($tag === 'iframe') {
                if ($child->getAttribute('src') === '') {
                    $node->removeChild($child);
                    continue;
                }
                $child->setAttribute('sandbox', 'allow-scripts allow-same-origin allow-presentation allow-popups');
                $child->setAttribute('referrerpolicy', 'no-referrer');
                if (!$child->hasAttribute('loading')) {
                    $child->setAttribute('loading', 'lazy');
                }
            } elseif ($tag === 'img' && $child->getAttribute('src') === '') {
                $node->removeChild($child);
                continue;
            } elseif ($tag === 'video' && $child->getAttribute('src') === '' && !$child->getElementsByTagName('source')->length) {
                $node->removeChild($child);
                continue;
            } elseif ($tag === 'img' && !$child->hasAttribute('loading')) {
                $child->setAttribute('loading', 'lazy');
            }
        }
    };
    $clean($root);

    // Penulis HTML sendiri: hasil identik di semua versi PHP/libxml (saveHTML() PHP 7.0 menyisipkan baris baru).
    $out = '';
    foreach ($root->childNodes as $c) {
        $out .= sanitize_serialize($c);
    }
    return trim($out);
}

function plain_excerpt($html, $max = 160)
{
    $t = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $html), ENT_QUOTES, 'UTF-8')));
    if (function_exists('mb_strlen') && mb_strlen($t) > $max) {
        return rtrim(mb_substr($t, 0, $max - 1)) . '…';
    }
    return strlen($t) > $max ? rtrim(substr($t, 0, $max - 1)) . '…' : $t;
}
