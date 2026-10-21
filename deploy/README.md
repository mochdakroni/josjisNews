# Deploy otomatis (GitHub Actions → VPS)

Setiap **push ke `main`**: tes dijalankan (PHP 8.2 & 8.4) → jika lulus, kode disalin ke server lewat SSH
(`rsync`) → dicek `/api/v1/health`. Push ke branch lain / PR hanya menjalankan tes.

Server **tidak pernah** ditimpa pada: `.env`, `storage/` (database, `app.key`, unggahan).
Tabel database baru dibuat/di-upgrade otomatis saat aplikasi dijalankan (tidak perlu migrasi manual).

## Persiapan satu kali

### 1. Aktifkan server & amankan akses
1. VPS di panel Rumahweb saat ini berstatus **Suspended**. Aktifkan dulu (restart/unsuspend atau hubungi support).
2. **Ganti password root** dan hapus skrip lama yang memuatnya
   (`~/.gemini/antigravity/brain/<id>/scratch/*.exp`). Jangan lagi memakai login root dengan password.
3. Ubuntu 16.04 sudah tidak didukung dan PHP bawaannya terlalu lama. **Instal ulang ke Ubuntu 24.04 (atau 22.04)**
   dari panel VPS. Cadangkan dulu `database/database.sqlite` & `storage/uploads/` dari server lama bila ada data penting.

### 2. Kunci deploy (di laptop Anda)
```bash
ssh-keygen -t ed25519 -f ~/.ssh/josjis_deploy -N "" -C "github-actions-josjisnews"
ssh-keyscan -t ed25519 103.247.11.59      # simpan keluarannya → DEPLOY_KNOWN_HOSTS
```
Cocokkan sidik jari host dengan yang tampil di konsol VPS sebelum mempercayainya.

### 3. Pasang server (sebagai root, sekali saja)
```bash
scp -r deploy root@103.247.11.59:/root/
ssh root@103.247.11.59
DOMAIN=josjis.news EMAIL=redaksi@josjis.news \
DEPLOY_PUBKEY="ISI_DENGAN_ISI_BERKAS_josjis_deploy.pub" \
OPS_PUBKEY="ISI_DENGAN_ISI_BERKAS_josjis_ops.pub" bash /root/deploy/server-setup.sh
```
Skrip memasang Nginx + PHP-FPM + SQLite, membuat pengguna `deploy` (hanya SSH key), folder `/var/www/josjisnews`,
batas unggah 100 MB, firewall (SSH + web), dan HTTPS Let's Encrypt jika DNS `A record` sudah mengarah ke server.
Setelah kunci berfungsi, nonaktifkan login root & password di `/etc/ssh/sshd_config`.

### 4. Secrets GitHub
Repo → *Settings → Secrets and variables → Actions → New repository secret*:

| Secret | Isi |
|---|---|
| `DEPLOY_HOST` | `103.247.11.59` (atau `josjis.news`) |
| `DEPLOY_USER` | `deploy` |
| `DEPLOY_PATH` | `/var/www/josjisnews` |
| `DEPLOY_PORT` | `22` |
| `DEPLOY_SSH_KEY` | isi **privat** `~/.ssh/josjis_deploy` |
| `DEPLOY_KNOWN_HOSTS` | keluaran `ssh-keyscan` di atas |
| `DEPLOY_HEALTH_URL` | `https://josjis.news/api/v1/health` |

Opsional: *Settings → Environments → production → Required reviewers* agar tiap deploy butuh persetujuan.

### 5. Deploy pertama
Gabungkan PR ke `main` (atau *Actions → Test & Deploy → Run workflow* dari `main`). Lalu:
1. Buka `https://josjis.news/admin.html`; sandi awal ada di `/var/www/josjisnews/storage/data/INITIAL_ADMIN_PASSWORD.txt`.
2. Ganti password, aktifkan **2FA**, hapus berkas sandi awal.
3. Setelah semua staf mendaftarkan 2FA, setel `REQUIRE_2FA=1` di `.env` server.

## Server yang sudah ada (tanpa instal ulang)
Untuk server yang sudah menjalankan Nginx + PHP-FPM (mis. Ubuntu 16.04 + PHP 7.0), pakai `adopt-server.sh`
sebagai ganti `server-setup.sh`. Skrip ini **tidak menulis ulang** konfigurasi secara membabi buta: ia mencadangkan
Nginx lama, memakai sertifikat HTTPS yang sudah ada, menguji `nginx -t` + health check, dan memulihkan konfigurasi
lama otomatis bila gagal. Folder situs lama tidak disentuh.

```bash
ssh -o StrictHostKeyChecking=accept-new root@SERVER "DEPLOY_PUBKEY='$(cat ~/.ssh/josjis_deploy.pub)' bash -s" < deploy/adopt-server.sh
```

Opsi lewat variabel lingkungan: `CODE_REF` (cabang yang dipasang, bawaan cabang PR), `DOMAIN`, `SERVER_NAMES`, `APP_DIR`, `OLD_DIR`.
Setelah itu push berikutnya ke `main` men-deploy lewat GitHub Actions seperti biasa.

## Rollback
`git revert <commit>` lalu push ke `main`, atau jalankan ulang workflow dari commit lama.

## Data server lama
Salin database & unggahan lama bila perlu:
```bash
scp root@SERVER_LAMA:/var/www/josjisNews/database/database.sqlite /tmp/old.sqlite
scp -r root@SERVER_LAMA:/var/www/josjisNews/storage/uploads/ /tmp/old-uploads
# di server baru:
install -o www-data -g www-data -m 600 /tmp/old.sqlite /var/www/josjisnews/storage/data/database.sqlite
cp -a /tmp/old-uploads/. /var/www/josjisnews/storage/uploads/ && chown -R www-data:www-data /var/www/josjisnews/storage
```
Akun admin lama memakai password lemah (`password123`): ganti segera, atau mulai dari database baru.

## Akses Claude ke server (baca-saja, opsional)

Claude tidak butuh akses ke server untuk deploy (itu tugas GitHub Actions). Bila ingin Claude ikut mendiagnosis
(cek status layanan, baca log, health check), pakai kunci terpisah yang di server **hanya bisa menjalankan
`josjis-ops`**: tanpa shell, tanpa sudo, tidak ada perintah yang mengubah data
(`status`, `health`, `version`, `files`, `logs-nginx [N]`, `logs-access [N]`, `logs-php [N]`).

1. Buat kunci di laptop dan berikan `.pub`-nya ke `server-setup.sh` sebagai `OPS_PUBKEY` (lihat langkah 3):
   ```bash
   ssh-keygen -t ed25519 -f ~/.ssh/josjis_ops -N "" -C "claude-josjisnews-ops"
   ```
2. Tambahkan izin di `.claude/settings.local.json` (berkas lokal, tidak di-commit). **Anda sendiri yang menulisnya**:
   ```json
   {
     "permissions": {
       "allow": [
         "Bash(ssh -i ~/.ssh/josjis_ops -o IdentitiesOnly=yes josjis-ops@103.247.11.59:*)"
       ]
     }
   }
   ```
3. Uji dari terminal Anda:
   ```bash
   ssh -i ~/.ssh/josjis_ops -o IdentitiesOnly=yes josjis-ops@103.247.11.59 status
   ```
Karena dibatasi di sisi server, perintah lain (mis. `cat /etc/shadow`, `bash`) ditolak walaupun izin di sisi Claude longgar.
Cabut akses kapan saja dengan menghapus `/home/josjis-ops/.ssh/authorized_keys` di server.
