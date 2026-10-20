<?php
/**
 * Generator Database SQLite Bersih Tanpa Artikel Dummy
 * Menghasilkan file database/database.sqlite yang siap diisi berita asli dari nol.
 */

declare(strict_types=1);

$dbPath = __DIR__ . '/database.sqlite';
if (file_exists($dbPath)) {
    unlink($dbPath);
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON;');

// 1. Buat Tabel Users, Sessions, Password Reset Tokens
$pdo->exec("
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
");

// 2. Buat Tabel Categories
$pdo->exec("
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
");

// 3. Buat Tabel Tags
$pdo->exec("
CREATE TABLE tags (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
");

// 4. Buat Tabel Articles
$pdo->exec("
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
");

// 5. Buat Tabel Pivot article_tag & article_views
$pdo->exec("
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
");

// 6. Buat Tabel Cache & Jobs
$pdo->exec("
CREATE TABLE cache (
    key VARCHAR(255) PRIMARY KEY,
    value MEDIUMTEXT NOT NULL,
    expiration INTEGER NOT NULL
);

CREATE TABLE jobs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    queue VARCHAR(255) NOT NULL,
    payload LONGTEXT NOT NULL,
    attempts INTEGER NOT NULL,
    reserved_at INTEGER NULL,
    available_at INTEGER NOT NULL,
    created_at INTEGER NOT NULL
);

CREATE TABLE advertisements (
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
CREATE INDEX idx_ads_position_active ON advertisements(position, is_active);
");

// =========================================================================
// INISIALISASI AKUN ADMIN UTAMA & KATEGORI KANAL DASAR (0 ARTIKEL DUMMY)
// =========================================================================
$passwordHash = password_hash('password123', PASSWORD_BCRYPT);

$stmtUser = $pdo->prepare("
    INSERT INTO users (id, name, email, email_verified_at, password, role, bio, created_at, updated_at)
    VALUES (?, ?, ?, datetime('now'), ?, ?, ?, datetime('now'), datetime('now'))
");
$stmtUser->execute([
    1,
    'Administrator Redaksi',
    'admin@josjisnews.com',
    $passwordHash,
    'super_admin',
    'Akun Administrator Utama Portal Berita JosjisNews.'
]);

$categories = [
    [1, 'Nasional', 'nasional', 'Kabar kedadeyan nasional, aturan pemerintah, lan pembangunan daerah.', 1],
    [2, 'Politik & Hukum', 'politik', 'Kabar parlemen, pemerintahan, aturan hukum, lan politik.', 1],
    [3, 'Ekonomi & Bisnis', 'bisnis', 'Kabar ekonomi, dagang, perbankan, investasi, lan usaha rakyat.', 1],
    [4, 'Teknologi & Sains', 'teknologi', 'Info teknologi anyar, internet, gadget, lan inovasi digital.', 1],
    [5, 'Otomotif & EV', 'otomotif', 'Info dunia otomotif, motor, mobil listrik, lan tunggangan anyar.', 1],
    [6, 'Olahraga', 'olahraga', 'Kabar bal-balan lan pertandingan olahraga nasional utawa internasional.', 1],
];

$stmtCat = $pdo->prepare("
    INSERT INTO categories (id, name, slug, description, is_active, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, datetime('now'), datetime('now'))
");
foreach ($categories as $cat) {
    $stmtCat->execute($cat);
}

if (PHP_SAPI === 'cli') {
    echo "Database SQLite bersih berhasil dibuat di: {$dbPath}\n";
    echo "Total Artikel: " . $pdo->query("SELECT COUNT(*) FROM articles")->fetchColumn() . " (Bersih dari dummy)\n";
    echo "Total Kategori: " . $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn() . "\n";
    echo "Total User: " . $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() . "\n";
}
