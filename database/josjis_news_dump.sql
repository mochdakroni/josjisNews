-- ============================================================================
-- JOSJISNEWS DATABASE DUMP (MariaDB / MySQL)
-- Database Name: josjis_news
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `josjis_news`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `josjis_news`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `advertisements`;
DROP TABLE IF EXISTS `article_views`;
DROP TABLE IF EXISTS `article_tag`;
DROP TABLE IF EXISTS `articles`;
DROP TABLE IF EXISTS `tags`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `sessions`;
DROP TABLE IF EXISTS `password_reset_tokens`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `cache`;
DROP TABLE IF EXISTS `jobs`;

-- 1. TABEL USERS
CREATE TABLE `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` VARCHAR(30) NOT NULL DEFAULT 'journalist',
  `avatar_url` VARCHAR(2048) NULL DEFAULT NULL,
  `bio` TEXT NULL DEFAULT NULL,
  `remember_token` VARCHAR(100) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_reset_tokens` (
  `email` VARCHAR(255) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sessions` (
  `id` VARCHAR(255) NOT NULL,
  `user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `ip_address` VARCHAR(45) NULL DEFAULT NULL,
  `user_agent` TEXT NULL DEFAULT NULL,
  `payload` LONGTEXT NOT NULL,
  `last_activity` INT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. TABEL CATEGORIES
CREATE TABLE `categories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `slug` VARCHAR(140) NOT NULL,
  `description` TEXT NULL DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categories_slug_unique` (`slug`),
  KEY `idx_categories_active_slug` (`is_active`, `slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. TABEL TAGS
CREATE TABLE `tags` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(120) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tags_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. TABEL ARTICLES
CREATE TABLE `articles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `category_id` BIGINT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `excerpt` TEXT NULL DEFAULT NULL,
  `content_body` LONGTEXT NOT NULL,
  `featured_image` VARCHAR(2048) NULL DEFAULT NULL,
  `caption_image` VARCHAR(500) NULL DEFAULT NULL,
  `status` VARCHAR(25) NOT NULL DEFAULT 'draft',
  `is_headline` TINYINT(1) NOT NULL DEFAULT 0,
  `is_trending` TINYINT(1) NOT NULL DEFAULT 0,
  `views_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `meta_title` VARCHAR(255) NULL DEFAULT NULL,
  `meta_description` VARCHAR(320) NULL DEFAULT NULL,
  `meta_keywords` VARCHAR(500) NULL DEFAULT NULL,
  `published_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `articles_slug_unique` (`slug`),
  KEY `idx_articles_pub` (`status`, `published_at`),
  KEY `idx_articles_headline` (`status`, `is_headline`, `published_at`),
  KEY `idx_articles_views` (`status`, `views_count`),
  CONSTRAINT `fk_articles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_articles_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. TABEL PIVOT ARTICLE_TAG & ARTICLE_VIEWS
CREATE TABLE `article_tag` (
  `article_id` BIGINT UNSIGNED NOT NULL,
  `tag_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`article_id`, `tag_id`),
  CONSTRAINT `fk_at_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_at_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `article_views` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `article_id` BIGINT UNSIGNED NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `user_agent` VARCHAR(512) NULL DEFAULT NULL,
  `viewed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_article_views_lookup` (`article_id`, `viewed_at`),
  CONSTRAINT `fk_av_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. TABEL ADVERTISEMENTS (PENGELOLAAN SLOT IKLAN HALAMAN DEPAN)
CREATE TABLE `advertisements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(180) NOT NULL,
  `sponsor_name` VARCHAR(120) NULL DEFAULT NULL,
  `position` VARCHAR(50) NOT NULL,
  `ad_type` VARCHAR(20) NOT NULL DEFAULT 'image',
  `image_url` VARCHAR(2048) NULL DEFAULT NULL,
  `target_url` VARCHAR(2048) NULL DEFAULT NULL,
  `html_code` TEXT NULL DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `views_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `clicks_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `start_date` TIMESTAMP NULL DEFAULT NULL,
  `end_date` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ads_position_active` (`position`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. TABEL CACHE & JOBS
CREATE TABLE `cache` (
  `key` VARCHAR(255) NOT NULL,
  `value` MEDIUMTEXT NOT NULL,
  `expiration` INT NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `queue` VARCHAR(255) NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `attempts` TINYINT UNSIGNED NOT NULL,
  `reserved_at` INT UNSIGNED NULL DEFAULT NULL,
  `available_at` INT UNSIGNED NOT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- DATA AWAL: 1 ADMIN UTAMA, 6 KATEGORI KANAL, & 4 SLOT ADVERTISE HALAMAN DEPAN
-- ============================================================================

INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `role`, `bio`) VALUES
(1, 'Administrator Redaksi', 'admin@josjisnews.com', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', 'Akun Administrator Utama Portal Berita JosjisNews.');

INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `is_active`) VALUES
(1, 'Nasional', 'nasional', 'Berita peristiwa nasional, kebijakan publik, dan pembangunan daerah.', 1),
(2, 'Politik & Hukum', 'politik', 'Liputan parlemen, pemerintahan, regulasi hukum, dan diplomasi.', 1),
(3, 'Ekonomi & Bisnis', 'bisnis', 'Berita makroekonomi, perbankan, investasi, pasar modal, dan industri.', 1),
(4, 'Teknologi & Sains', 'teknologi', 'Inovasi teknologi, transformasi digital, telekomunikasi, dan sains.', 1),
(5, 'Otomotif & EV', 'otomotif', 'Perkembangan industri otomotif, kendaraan listrik, dan transportasi.', 1),
(6, 'Olahraga', 'olahraga', 'Kabar kompetisi olahraga nasional dan internasional.', 1);

INSERT INTO `advertisements` (`id`, `title`, `sponsor_name`, `position`, `ad_type`, `image_url`, `target_url`, `html_code`, `is_active`) VALUES
(1, 'Slot Leaderboard Utama Atas Beranda (970×90)', 'Space Iklan JosjisNews', 'leaderboard_top', 'html', '', 'https://josjisnews.com/pasang-iklan', '<div class="flex flex-col items-center justify-center py-2 text-center"><span class="text-[10px] font-bold uppercase tracking-[0.2em] text-brand-600">SPONSORED • LEADERBOARD UTAMA (970×90)</span><p class="mt-1 text-xs font-semibold text-zinc-700 dark:text-zinc-200">Pasang Banner Kampanye Brand &amp; Institusi Anda di Posisi Paling Strategis Halaman Depan JosjisNews</p></div>', 1),
(2, 'Slot In-Feed Native Beranda (Di Bawah Berita Terkini)', 'Space Iklan JosjisNews', 'infeed_home', 'html', '', 'https://josjisnews.com/pasang-iklan', '<div class="flex flex-col items-center justify-center py-3 text-center"><span class="text-[10px] font-bold uppercase tracking-[0.2em] text-brand-600">ADVERTISEMENT • IN-FEED NATIVE BANNER</span><p class="mt-1 text-xs font-semibold text-zinc-700 dark:text-zinc-200">Slot Iklan Kontekstual Menyatu di Alur Indeks Berita Utama</p></div>', 1),
(3, 'Slot Sidebar Kanan Beranda (300×250 Medium Rectangle)', 'Space Iklan JosjisNews', 'sidebar_home', 'html', '', 'https://josjisnews.com/pasang-iklan', '<div class="flex flex-col items-center justify-center py-6 text-center"><span class="text-[10px] font-bold uppercase tracking-[0.2em] text-brand-600">ADVERTISEMENT • 300×250</span><p class="mt-2 text-xs font-semibold text-zinc-700 dark:text-zinc-200">Sidebar Banner Kanan Beranda (Sticky di Bawah Berita Terpopuler)</p></div>', 1),
(4, 'Slot Sidebar Halaman Baca Artikel (300×250)', 'Space Iklan JosjisNews', 'sidebar_article', 'html', '', 'https://josjisnews.com/pasang-iklan', '<div class="flex flex-col items-center justify-center py-6 text-center"><span class="text-[10px] font-bold uppercase tracking-[0.2em] text-brand-600">ADVERTISEMENT • SIDEBAR ARTIKEL (300×250)</span><p class="mt-2 text-xs font-semibold text-zinc-700 dark:text-zinc-200">Tampil di Samping Artikel Saat Pembaca Membaca Berita Lengkap</p></div>', 1);

SET FOREIGN_KEY_CHECKS = 1;
