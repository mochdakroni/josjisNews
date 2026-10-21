# JosjisNews — *Bloko Sutho*

Portal kabar digital berbahasa Jawa ngoko, diterbitkan oleh **PT Sendhiko Dawuh Multiguna**.
Tanpa Composer/Node: cukup **PHP 7.4+** dengan ekstensi `pdo_sqlite`, `fileinfo`, `dom`, `mbstring`.

## Menjalankan

```bash
cp .env.example .env          # sesuaikan nama situs, kontak, APP_URL
php database/build_sqlite_db.php   # buat database + akun admin (sandi acak dicetak sekali)
php -S 0.0.0.0:8080 router.php
```

- Situs publik: `http://localhost:8080/`
- Panel CMS: `http://localhost:8080/admin.html`
- Jika database belum ada, otomatis dibuat saat request pertama; sandi awal ada di
  `storage/data/INITIAL_ADMIN_PASSWORD.txt` (**hapus setelah login & ganti password**).

Produksi & deploy otomatis (GitHub Actions → VPS): lihat [deploy/README.md](deploy/README.md); contoh konfigurasi: [deploy/nginx.conf.example](deploy/nginx.conf.example) (Nginx + PHP-FPM), pasang HTTPS,
dan jadwalkan salinan berkala `storage/data/` dan `storage/uploads/`.

## Struktur

| Jalur | Fungsi |
|---|---|
| `router.php` | Front controller; satu-satunya pintu masuk (tidak ada berkas disajikan langsung) |
| `src/` | `config`, `db`, `auth` (RBAC), `api`, `repository`, `media`, `seo`, `helpers` (sanitizer HTML) |
| `preview.html` | Cangkang situs publik (SPA ringan + meta SEO dirender server) |
| `admin.html` | Panel CMS (CKEditor 5) |
| `storage/data/` | SQLite (diabaikan git) · `storage/uploads/` media unggahan |
| `tests/run.php` | Tes end-to-end: `php tests/run.php` |
| `legacy/` | Cetak biru Laravel/Filament & Next.js lama. **Tidak aktif** — hanya arsip |

## Fitur

- Artikel, kategori, tag, headline, trending 48 jam, pencarian, paginasi ("Muat Kabar Liyane").
- URL bersih & dapat dibagikan: `/artikel/{slug}`, `/kanal/{slug}`, `/halaman/{slug}` dengan Open Graph,
  JSON-LD `NewsArticle`, `sitemap.xml`, `rss.xml` (+ `/kanal/{slug}/rss.xml`), `robots.txt`.
- Halaman redaksi wajib media siber: Tentang, Susunan Redaksi, Pedoman Media Siber, Hak Jawab, Privasi, S&K, Pasang Iklan.
- Peran: **super_admin** (semua) · **editor** (semua konten, kategori, iklan) · **journalist** (menulis; "terbit" otomatis jadi *review*).
- Slot iklan dengan hitungan tayangan/klik yang tahan spam; jejak audit (`GET /api/v1/admin/audit-logs`).

## Keamanan

Sudah diterapkan:

- Daftar putih penyajian berkas: `.env`, SQLite, kode sumber tidak bisa diunduh (router + `.htaccess` + contoh Nginx).
- Unggahan: ekstensi + isi file (finfo/getimagesize) divalidasi, SVG/PHP ditolak, nama acak, batas ukuran, rate limit.
- HTML artikel dan iklan disanitasi di server (allowlist tag/atribut/skema URL, iframe hanya host tepercaya).
- Sesi: cookie `HttpOnly` + `SameSite=Lax` (+`Secure` di HTTPS), **token disimpan sebagai hash SHA-256** di DB, idle 8 jam, batas mutlak 24 jam, maks. 5 sesi/pengguna.
- Login: rate limit per IP dan per email, waktu respons seragam (tanpa bocor email terdaftar), bcrypt cost 12, password min. 10 karakter, tanpa akun bawaan, berkas sandi awal otomatis dihapus saat password diganti.
- **2FA TOTP (RFC 6238)** untuk semua akun staf: login 2 langkah dengan tantangan sekali pakai (5 menit, maks. 5 percobaan), kode anti-replay,
  8 kode pemulihan sekali pakai (disimpan sebagai HMAC), rahasia TOTP terenkripsi AES-256-GCM di DB (kunci: `APP_KEY` atau `storage/data/app.key`).
  Aktifkan di panel: **Keamanan Akun (2FA)**. Setel `REQUIRE_2FA=1` agar super_admin & editor *wajib* 2FA (semua endpoint admin diblokir sampai aktif).
  Perangkat hilang: super admin memakai tombol **Reset 2FA** di Tim Redaksi & User, atau pengguna memakai kode pemulihan.
- RBAC (super_admin/editor/journalist) ditegakkan di server; jejak audit untuk aksi sensitif.
- Anti-CSRF: cek `Origin`/`Sec-Fetch-Site`; CSP (`frame-ancestors`, `object-src 'none'`, `base-uri`, daftar sumber skrip/frame); `X-Frame-Options`, `nosniff`, `Referrer-Policy`, HSTS di HTTPS.
- Rate limit API umum (240/menit/IP), pencarian (30/menit), klik iklan, tayangan; batas ukuran isi artikel.
- `ALLOWED_HOSTS` mencegah host-header poisoning; `X-Forwarded-*` hanya dipercaya bila `TRUSTED_PROXY=1`.
- Direktori `storage/data` mode 0700, database 0600; galat server tidak membocorkan detail.

Checklist produksi: isi `APP_URL` & `ALLOWED_HOSTS`, aktifkan HTTPS, atur `upload_max_filesize`/`post_max_size`
(PHP) dan `client_max_body_size` (Nginx) sesuai batas unggahan, cadangkan `storage/` berkala, jangan jalankan sebagai root.

Belum ada (disarankan berikutnya): kode QR untuk pendaftaran 2FA (saat ini memakai kunci teks + tautan `otpauth://`), dan menghosting CSS Tailwind sendiri
(saat ini memuat `cdn.tailwindcss.com`, skrip pihak ketiga yang berjalan juga di halaman admin).

## Yang perlu Anda isi (data legal)

Atur di `.env`: `CONTACT_ADDRESS`, `CONTACT_PHONE`, `EDITOR_IN_CHIEF`. Dewan Pers mensyaratkan penanggung jawab
dan alamat redaksi tampil jelas. Halaman memakai kalimat **komitmen** terhadap pedoman Dewan Pers; status
terverifikasi Dewan Pers adalah proses terpisah dan tidak diklaim di situs.
