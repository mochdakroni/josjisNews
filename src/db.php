<?php
/**
 * Koneksi & skema database SQLite.
 */

require_once __DIR__ . '/config.php';

function josjis_schema_sql()
{
    return "
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    email_verified_at DATETIME NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(30) NOT NULL DEFAULT 'journalist',
    avatar_url VARCHAR(2048) NULL,
    bio TEXT NULL,
    remember_token VARCHAR(100) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_users_role ON users(role);

CREATE TABLE password_reset_tokens (
    email VARCHAR(255) PRIMARY KEY,
    token VARCHAR(255) NOT NULL,
    created_at DATETIME NULL
);

CREATE TABLE sessions (
    id VARCHAR(255) PRIMARY KEY,
    user_id INTEGER NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    payload TEXT NOT NULL,
    last_activity INTEGER NOT NULL
);

CREATE TABLE categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    description TEXT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_categories_active_slug ON categories(is_active, slug);

CREATE TABLE tags (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE articles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    category_id INTEGER NOT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    excerpt TEXT NULL,
    content_body LONGTEXT NOT NULL,
    featured_image VARCHAR(2048) NULL,
    caption_image VARCHAR(500) NULL,
    status VARCHAR(25) NOT NULL DEFAULT 'draft',
    is_headline INTEGER NOT NULL DEFAULT 0,
    is_trending INTEGER NOT NULL DEFAULT 0,
    views_count INTEGER NOT NULL DEFAULT 0,
    meta_title VARCHAR(255) NULL,
    meta_description VARCHAR(320) NULL,
    meta_keywords VARCHAR(500) NULL,
    published_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
);
CREATE INDEX idx_articles_pub ON articles(status, published_at);
CREATE INDEX idx_articles_headline ON articles(status, is_headline, published_at);
CREATE INDEX idx_articles_views ON articles(status, views_count);

CREATE TABLE article_tag (
    article_id INTEGER NOT NULL,
    tag_id INTEGER NOT NULL,
    PRIMARY KEY (article_id, tag_id),
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
);

CREATE TABLE article_views (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    article_id INTEGER NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(512) NULL,
    viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE
);
CREATE INDEX idx_article_views_lookup ON article_views(article_id, viewed_at);
";
}

/** Tabel & indeks tambahan (idempotent) agar basis data lama otomatis ikut ter-upgrade. */
function josjis_ensure_extras(PDO $pdo)
{
    $pdo->exec("
CREATE TABLE IF NOT EXISTS advertisements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title VARCHAR(180) NOT NULL,
    sponsor_name VARCHAR(120) NULL,
    position VARCHAR(50) NOT NULL,
    ad_type VARCHAR(20) NOT NULL DEFAULT 'image',
    image_url VARCHAR(2048) NULL,
    target_url VARCHAR(2048) NULL,
    html_code TEXT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    views_count INTEGER NOT NULL DEFAULT 0,
    clicks_count INTEGER NOT NULL DEFAULT 0,
    start_date DATETIME NULL,
    end_date DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_ads_position_active ON advertisements(position, is_active);

CREATE TABLE IF NOT EXISTS rate_limits (
    bucket VARCHAR(190) PRIMARY KEY,
    hits INTEGER NOT NULL DEFAULT 0,
    window_start INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NULL,
    action VARCHAR(60) NOT NULL,
    detail TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_audit_created ON audit_logs(created_at);
CREATE INDEX IF NOT EXISTS idx_sessions_activity ON sessions(last_activity);

CREATE TABLE IF NOT EXISTS user_2fa (
    user_id INTEGER PRIMARY KEY,
    secret_enc TEXT NOT NULL,
    enabled INTEGER NOT NULL DEFAULT 0,
    last_step INTEGER NOT NULL DEFAULT 0,
    recovery TEXT NOT NULL DEFAULT '[]',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    enabled_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS auth_challenges (
    id VARCHAR(64) PRIMARY KEY,
    user_id INTEGER NOT NULL,
    expires_at INTEGER NOT NULL,
    attempts INTEGER NOT NULL DEFAULT 0
);
");

    if ((int) $pdo->query('SELECT COUNT(*) FROM advertisements')->fetchColumn() === 0) {
        $url = site()['ads_email'];
        $slots = [
            ['Slot Leaderboard Utama (970×90)', 'leaderboard_top', 'IKLAN • LEADERBOARD UTAMA (970×90)', 'Pasang spanduk usahamu ning panggonan paling strategis ngarep dewe.'],
            ['Slot In-Feed Native', 'infeed_home', 'IKLAN • IN-FEED NATIVE', 'Lapak iklan nyawiji langsung ning tengah daftar kabar utama.'],
            ['Slot Pinggir Omah (300×250)', 'sidebar_home', 'IKLAN • 300×250', 'Banner pinggir tengen ning laman ngarep.'],
            ['Slot Pinggir Kabar (300×250)', 'sidebar_article', 'IKLAN • PINGGIR KABAR (300×250)', 'Ketok pas ning sebelahe kabar nalika wong lagi asyik maca.'],
        ];
        $st = $pdo->prepare('INSERT INTO advertisements (title, sponsor_name, position, ad_type, image_url, target_url, html_code, is_active) VALUES (?,?,?,?,?,?,?,1)');
        foreach ($slots as $s) {
            $html = '<div class="flex flex-col items-center justify-center py-4 text-center"><span class="text-[10px] font-bold uppercase tracking-[0.2em] text-brand-600">'
                . htmlspecialchars($s[2], ENT_QUOTES, 'UTF-8') . '</span><p class="mt-1 text-xs font-semibold text-zinc-700 dark:text-zinc-200">'
                . htmlspecialchars($s[3], ENT_QUOTES, 'UTF-8') . '</p></div>';
            $st->execute([$s[0], 'Lapak Iklan ' . site()['name'], $s[1], 'html', '', '/halaman/pasang-iklan', $html]);
        }
        unset($url);
    }
}

/**
 * Bangun basis data baru. Mengembalikan kata sandi admin awal.
 * Kata sandi berasal dari $adminPassword / ADMIN_PASSWORD, atau dibuat acak.
 */
function josjis_build_database($path, $adminPassword = null)
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    @chmod($dir, 0700);
    $pdo = new PDO('sqlite:' . $path);
    @chmod($path, 0600);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON;');
    $pdo->exec(josjis_schema_sql());

    $adminPassword = $adminPassword ?: env('ADMIN_PASSWORD');
    if (!$adminPassword) {
        $adminPassword = bin2hex(random_bytes(9));
    }
    $adminEmail = strtolower((string) env('ADMIN_EMAIL', 'admin@josjis.news'));

    $pdo->prepare("INSERT INTO users (id, name, email, email_verified_at, password, role, bio) VALUES (1, ?, ?, datetime('now'), ?, 'super_admin', ?)")
        ->execute(['Administrator Redaksi', $adminEmail, hash_password($adminPassword), 'Akun administrator utama ' . site()['name'] . '.']);

    $categories = [
        [1, 'Event & Komunitas', 'event-komunitas', 'Kabar acara seru, kopdar, kolaborasi, lan kegiatan komunitas kreatif.'],
        [2, 'Personal Branding', 'personal-branding', 'Tips mbangun citra dhiri, karir, portofolio, lan cara tampil beda nang era digital.'],
        [3, 'Lifestyle', 'lifestyle', 'Gaya urip kekinian, nongkrong, kuliner, fashion, lan tren saben dina.'],
        [4, 'Seputar Gen Z', 'seputar-gen-z', 'Rembugan bloko sutho babagan bocah Gen Z, tren medsos, kesehatan mental, lan pop culture.'],
    ];
    $st = $pdo->prepare('INSERT INTO categories (id, name, slug, description, is_active) VALUES (?,?,?,?,1)');
    foreach ($categories as $c) {
        $st->execute($c);
    }
    josjis_ensure_extras($pdo);

    // Simpan kata sandi awal di luar webroot publik (storage/data diblokir oleh router).
    $note = dirname($path) . '/INITIAL_ADMIN_PASSWORD.txt';
    @file_put_contents($note, "Email   : {$adminEmail}\nPassword: {$adminPassword}\n\nLogin lalu ganti password, kemudian HAPUS berkas ini.\n");
    @chmod($note, 0600);

    return ['email' => $adminEmail, 'password' => $adminPassword, 'note' => $note];
}

function db()
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $path = db_path();
    if (!is_file($path)) {
        josjis_build_database($path);
    }
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON;');
    $pdo->exec('PRAGMA journal_mode = WAL;');
    $pdo->exec('PRAGMA busy_timeout = 5000;');
    josjis_ensure_extras($pdo);
    return $pdo;
}
