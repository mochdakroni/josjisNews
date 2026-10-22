<?php
/**
 * Akses data artikel/kategori/tag (read model) yang dipakai API dan halaman SEO.
 */

require_once __DIR__ . '/helpers.php';

function article_select_sql()
{
    return "SELECT a.*, u.name AS author_name, u.role AS author_role,
                   c.name AS category_name, c.slug AS category_slug
            FROM articles a
            LEFT JOIN users u ON u.id = a.user_id
            LEFT JOIN categories c ON c.id = a.category_id";
}

const PUBLISHED_SQL = "a.status = 'published' AND a.published_at IS NOT NULL AND a.published_at <= datetime('now')";

function wib_label($utc)
{
    if (!$utc) {
        return '';
    }
    $ts = strtotime($utc . ' UTC');
    if (!$ts) {
        return '';
    }
    $dt = new DateTime('@' . $ts);
    $dt->setTimezone(new DateTimeZone('Asia/Jakarta'));
    $dina = ['Minggu', 'Senen', 'Selasa', 'Rebo', 'Kemis', 'Jemuwah', 'Setu'];
    $sasi = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return $dina[(int) $dt->format('w')] . ', ' . $dt->format('j') . ' ' . $sasi[(int) $dt->format('n') - 1]
        . ' ' . $dt->format('Y') . ' • ' . $dt->format('H.i') . ' WIB';
}

function iso_date($utc)
{
    $ts = $utc ? strtotime($utc . ' UTC') : false;
    return $ts ? gmdate('c', $ts) : null;
}

/** Ambil tag untuk banyak artikel sekaligus (hindari N+1). */
function tags_for_articles(PDO $pdo, array $ids)
{
    if (!$ids) {
        return [];
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT at.article_id, t.id, t.name, t.slug
        FROM article_tag at INNER JOIN tags t ON t.id = at.tag_id
        WHERE at.article_id IN ($in) ORDER BY t.name");
    $st->execute(array_values($ids));
    $map = [];
    foreach ($st->fetchAll() as $r) {
        $map[(int) $r['article_id']][] = ['id' => (int) $r['id'], 'name' => $r['name'], 'slug' => $r['slug']];
    }
    return $map;
}

function hydrate_articles(PDO $pdo, array $rows, $admin = false, $withBody = true)
{
    if (!$rows) {
        return [];
    }
    $tagMap = tags_for_articles($pdo, array_map(function ($r) {
        return (int) $r['id'];
    }, $rows));

    $out = [];
    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $body = (string) ($row['content_body'] ?? '');
        $words = str_word_count(strip_tags($body)) ?: count(preg_split('/\s+/', trim(strip_tags($body)), -1, PREG_SPLIT_NO_EMPTY));
        $item = [
            'id'                 => $id,
            'title'              => $row['title'],
            'slug'               => $row['slug'],
            'url'                => '/artikel/' . $row['slug'],
            'excerpt'            => $row['excerpt'] ?? '',
            'featured_image_url' => $row['featured_image'] ?? '',
            'image'              => $row['featured_image'] ?? '',
            'caption_image'      => $row['caption_image'] ?? '',
            'status'             => $row['status'],
            'is_headline'        => (bool) $row['is_headline'],
            'headline'           => (bool) $row['is_headline'],
            'views_count'        => (int) $row['views_count'],
            'views'              => (int) $row['views_count'],
            'reading_time_minutes' => max(1, (int) ceil($words / 200)),
            'published_at'       => iso_date($row['published_at']),
            'published_label'    => wib_label($row['published_at']),
            'published_rel'      => relative_time($row['published_at'] ?: $row['created_at']),
            'updated_at'         => iso_date($row['updated_at'] ?? null),
            'tags'               => $tagMap[$id] ?? [],
        ];
        if ($withBody) {
            $item['content_body'] = $body;
            $item['body'] = $body;
        }
        if ($admin) {
            $item['author'] = $row['author_name'] ?? 'Redaksi';
            $item['author_id'] = (int) $row['user_id'];
            $item['category'] = $row['category_name'] ?? '';
        } else {
            $item['author'] = [
                'id'   => (int) $row['user_id'],
                'name' => $row['author_name'] ?? 'Redaksi',
                'role' => $row['author_role'] ?? 'journalist',
            ];
            $item['category'] = [
                'id'   => (int) $row['category_id'],
                'name' => $row['category_name'] ?? 'Event & Komunitas',
                'slug' => $row['category_slug'] ?? 'event-komunitas',
            ];
        }
        $out[] = $item;
    }
    return $out;
}

function like_escape($s)
{
    return '%' . addcslashes(trim((string) $s), '%_\\') . '%';
}

/**
 * Daftar artikel terbit dengan filter + paginasi.
 * @return array [items, meta]
 */
function query_published(PDO $pdo, array $f)
{
    $where = [PUBLISHED_SQL];
    $params = [];
    if (!empty($f['category']) && $f['category'] !== 'all') {
        $where[] = 'c.slug = ?';
        $params[] = $f['category'];
    }
    if (!empty($f['tag'])) {
        $where[] = 'EXISTS (SELECT 1 FROM article_tag at INNER JOIN tags t ON t.id = at.tag_id
            WHERE at.article_id = a.id AND (t.slug = ? OR LOWER(t.name) = LOWER(?)))';
        $params[] = $f['tag'];
        $params[] = $f['tag'];
    }
    if (!empty($f['search'])) {
        $q = like_escape(substr((string) $f['search'], 0, 100));
        $where[] = "(a.title LIKE ? ESCAPE '\\' OR a.excerpt LIKE ? ESCAPE '\\' OR a.content_body LIKE ? ESCAPE '\\')";
        array_push($params, $q, $q, $q);
    }
    if (!empty($f['exclude_id'])) {
        $where[] = 'a.id != ?';
        $params[] = (int) $f['exclude_id'];
    }
    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $perPage = max(1, min(50, (int) ($f['per_page'] ?? 12)));
    $page = max(1, (int) ($f['page'] ?? 1));
    $sort = $f['sort'] ?? 'latest';
    $order = $sort === 'popular' ? 'a.views_count DESC, a.published_at DESC'
        : ($sort === 'oldest' ? 'a.published_at ASC' : 'a.published_at DESC, a.id DESC');

    $cnt = $pdo->prepare('SELECT COUNT(*) FROM articles a LEFT JOIN categories c ON c.id = a.category_id ' . $whereSql);
    $cnt->execute($params);
    $total = (int) $cnt->fetchColumn();

    $st = $pdo->prepare(article_select_sql() . " $whereSql ORDER BY $order LIMIT $perPage OFFSET " . (($page - 1) * $perPage));
    $st->execute($params);

    return [
        hydrate_articles($pdo, $st->fetchAll(), false, !empty($f['with_body'])),
        [
            'current_page' => $page,
            'per_page'     => $perPage,
            'total'        => $total,
            'last_page'    => max(1, (int) ceil($total / $perPage)),
        ],
    ];
}

function find_published_article(PDO $pdo, $identifier)
{
    if (ctype_digit((string) $identifier)) {
        $st = $pdo->prepare(article_select_sql() . ' WHERE a.id = ? AND ' . PUBLISHED_SQL . ' LIMIT 1');
    } else {
        $st = $pdo->prepare(article_select_sql() . ' WHERE a.slug = ? AND ' . PUBLISHED_SQL . ' LIMIT 1');
    }
    $st->execute([$identifier]);
    return $st->fetch() ?: null;
}

function trending_articles(PDO $pdo, $hours = 48, $limit = 5)
{
    $hours = max(1, min(168, (int) $hours));
    $limit = max(1, min(20, (int) $limit));
    $st = $pdo->prepare(article_select_sql() . ' WHERE ' . PUBLISHED_SQL . "
        ORDER BY (SELECT COUNT(*) FROM article_views v WHERE v.article_id = a.id AND v.viewed_at >= datetime('now', ?)) DESC,
                 a.views_count DESC, a.published_at DESC
        LIMIT $limit");
    $st->execute(['-' . $hours . ' hours']);
    return hydrate_articles($pdo, $st->fetchAll(), false, false);
}

function active_categories(PDO $pdo)
{
    return $pdo->query("SELECT c.*,
        (SELECT COUNT(*) FROM articles a WHERE a.category_id = c.id AND " . PUBLISHED_SQL . ") AS articles_count
        FROM categories c WHERE c.is_active = 1 ORDER BY c.id")->fetchAll();
}

function popular_tags(PDO $pdo, $limit = 10)
{
    $limit = max(1, min(50, (int) $limit));
    return $pdo->query("SELECT t.id, t.name, t.slug,
            (SELECT COUNT(*) FROM article_tag at INNER JOIN articles a ON a.id = at.article_id
              WHERE at.tag_id = t.id AND " . PUBLISHED_SQL . ") AS articles_count
        FROM tags t ORDER BY articles_count DESC, t.name ASC LIMIT $limit")->fetchAll();
}

/** Simpan daftar tag (array / string dipisah koma) untuk sebuah artikel. */
function sync_article_tags(PDO $pdo, $articleId, $tags)
{
    if (is_string($tags)) {
        $tags = explode(',', $tags);
    }
    if (!is_array($tags)) {
        return;
    }
    $names = [];
    foreach ($tags as $t) {
        $n = trim(is_array($t) ? ($t['name'] ?? '') : (string) $t);
        $n = trim(preg_replace('/\s+/', ' ', preg_replace('/[^\p{L}\p{N} \-]+/u', '', ltrim($n, '#'))));
        if ($n !== '' && strlen($n) <= 60) {
            $names[strtolower($n)] = $n;
        }
        if (count($names) >= 10) {
            break;
        }
    }
    $pdo->prepare('DELETE FROM article_tag WHERE article_id = ?')->execute([$articleId]);
    foreach ($names as $name) {
        $slug = slugify($name);
        $st = $pdo->prepare('SELECT id FROM tags WHERE slug = ?');
        $st->execute([$slug]);
        $tagId = $st->fetchColumn();
        if (!$tagId) {
            $pdo->prepare('INSERT INTO tags (name, slug) VALUES (?, ?)')->execute([$name, $slug]);
            $tagId = $pdo->lastInsertId();
        }
        $pdo->prepare('INSERT OR IGNORE INTO article_tag (article_id, tag_id) VALUES (?, ?)')->execute([$articleId, $tagId]);
    }
}
