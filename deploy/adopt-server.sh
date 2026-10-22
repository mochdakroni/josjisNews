#!/usr/bin/env bash
# Memasang JosjisNews (kode baru) di SERVER YANG SUDAH ADA tanpa instal ulang
# (mis. Ubuntu 16.04 + Nginx + PHP-FPM 7.0 hasil pemasangan sebelumnya).
#
#   ssh root@SERVER "DEPLOY_PUBKEY='ISI_josjis_deploy.pub' bash -s" < deploy/adopt-server.sh
#
# Yang dilakukan (aman diulang):
#   1. cek PHP & ekstensi, pasang paket yang kurang
#   2. buat pengguna "deploy" (SSH key saja) + folder /var/www/josjisnews
#   3. ambil kode dari GitHub (repo publik) dan pasang
#   4. buat .env (APP_URL, ALLOWED_HOSTS, ...)
#   5. cadangkan konfigurasi Nginx lama, pasang vhost baru yang MEMAKAI SERTIFIKAT HTTPS YANG SUDAH ADA
#   6. uji `nginx -t` + health check; bila gagal, KEMBALIKAN konfigurasi lama otomatis
#
# Folder lama (/var/www/josjisNews) tidak disentuh, jadi rollback tinggal mengembalikan Nginx.
# Seluruh logika ada di fungsi main agar stdin skrip tidak "termakan" perintah di dalamnya.

main() {
  set -euo pipefail

  APP_DIR="${APP_DIR:-/var/www/josjisnews}"
  OLD_DIR="${OLD_DIR:-/var/www/josjisNews}"
  REPO="${REPO:-https://github.com/mochdakroni/josjisNews.git}"
  CODE_REF="${CODE_REF:-claude/pelajari-josjisnews-dfb465}"
  DOMAIN="${DOMAIN:-josjis.news}"
  SERVER_NAMES="${SERVER_NAMES:-josjis.news www.josjis.news server1.josjis.news}"
  DEPLOY_USER="${DEPLOY_USER:-deploy}"
  DEPLOY_PUBKEY="${DEPLOY_PUBKEY:-}"
  TS="$(date +%Y%m%d-%H%M%S)"
  BACKUP="/root/nginx-backup-$TS"

  say() { printf '\n\033[1m>> %s\033[0m\n' "$*"; }
  die() { printf '\n\033[31mGAGAL: %s\033[0m\n' "$*" >&2; exit 1; }

  [ "$(id -u)" -eq 0 ] || die "jalankan sebagai root"
  command -v nginx >/dev/null || die "nginx tidak ditemukan"
  command -v php >/dev/null || die "php tidak ditemukan"
  [ -n "$DEPLOY_PUBKEY" ] || die "isi DEPLOY_PUBKEY (isi berkas josjis_deploy.pub)"

  # ---------------------------------------------------------------- 1. PHP
  say "1. Memeriksa PHP & ekstensi"
  PHP_ID="$(php -r 'echo PHP_VERSION_ID;')"
  PHP_VER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
  echo "PHP $PHP_VER"
  [ "$PHP_ID" -ge 70000 ] || die "PHP >= 7.0 diperlukan"
  export DEBIAN_FRONTEND=noninteractive
  apt-get update -y </dev/null >/dev/null 2>&1 || true
  apt-get install -y git rsync curl php-sqlite3 php-mbstring php-xml php-curl </dev/null >/dev/null 2>&1 || true
  for ext in pdo_sqlite mbstring dom fileinfo openssl json; do
    php -m | grep -qix "$ext" || die "ekstensi PHP '$ext' belum terpasang (apt-get install php-$ext)"
  done
  FPM_SOCK="$(ls /run/php/php*-fpm.sock 2>/dev/null | head -n 1 || true)"
  [ -n "$FPM_SOCK" ] || die "socket php-fpm tidak ditemukan di /run/php/"
  FPM_SVC="$(basename "$FPM_SOCK" .sock)"
  echo "php-fpm: $FPM_SOCK ($FPM_SVC)"

  # ------------------------------------------------------ 2. pengguna & folder
  say "2. Pengguna deploy & folder aplikasi"
  id "$DEPLOY_USER" >/dev/null 2>&1 || adduser --disabled-password --gecos "" "$DEPLOY_USER" </dev/null
  install -d -m 700 -o "$DEPLOY_USER" -g "$DEPLOY_USER" "/home/$DEPLOY_USER/.ssh"
  AK="/home/$DEPLOY_USER/.ssh/authorized_keys"
  touch "$AK"
  grep -qxF "$DEPLOY_PUBKEY" "$AK" || echo "$DEPLOY_PUBKEY" >> "$AK"
  chmod 600 "$AK"; chown "$DEPLOY_USER:$DEPLOY_USER" "$AK"

  install -d -o "$DEPLOY_USER" -g www-data -m 750 "$APP_DIR"
  install -d -o www-data -g www-data -m 700 "$APP_DIR/storage/data"
  for d in images videos documents; do install -d -o www-data -g www-data -m 755 "$APP_DIR/storage/uploads/$d"; done

  # ------------------------------------------------------------ 3. kode
  say "3. Mengambil kode ($CODE_REF)"
  TMP="$(mktemp -d)"
  trap 'rm -rf "$TMP"' EXIT
  git clone --depth 1 --branch "$CODE_REF" "$REPO" "$TMP/src" </dev/null
  rsync -a --exclude '.git/' --exclude '.github/' --exclude 'tests/' --exclude 'legacy/' --exclude 'deploy/' \
        --exclude 'storage/' --exclude '.env' --exclude 'database/*.sqlite' --exclude 'database/seed_demo.php' \
        "$TMP/src/" "$APP_DIR/"
  chown -R "$DEPLOY_USER:www-data" "$APP_DIR"
  chown -R www-data:www-data "$APP_DIR/storage"
  find "$APP_DIR" -path "$APP_DIR/storage" -prune -o -type d -exec chmod 750 {} +
  find "$APP_DIR" -path "$APP_DIR/storage" -prune -o -type f -exec chmod 640 {} +
  echo "kode terpasang: $(cd "$TMP/src" && git log --oneline -1)"

  # ------------------------------------------------------------ 4. .env
  say "4. Konfigurasi aplikasi (.env)"
  if [ ! -f "$APP_DIR/.env" ]; then
    cat > "$APP_DIR/.env" <<ENV
APP_URL=https://$DOMAIN
ALLOWED_HOSTS=$(echo "$SERVER_NAMES" | tr ' ' ',')
SITE_NAME=JosjisNews
SITE_TAGLINE="Bloko Sutho"
COMPANY_NAME="PT Sendhiko Dawuh Multiguna"
CONTACT_EMAIL=redaksi@$DOMAIN
ADS_EMAIL=iklan@$DOMAIN
TRUSTED_PROXY=0
REQUIRE_2FA=0
ENV
    chown "$DEPLOY_USER:www-data" "$APP_DIR/.env"; chmod 640 "$APP_DIR/.env"
    echo ".env dibuat"
  else
    echo ".env sudah ada, dibiarkan"
  fi

  # batas unggah PHP (selaras 100 MB video)
  INI_DIR="/etc/php/$PHP_VER/fpm/conf.d"
  if [ -d "$INI_DIR" ]; then
    printf 'upload_max_filesize = 100M\npost_max_size = 110M\nexpose_php = Off\ndisplay_errors = Off\nlog_errors = On\n' > "$INI_DIR/99-josjis.ini"
  fi

  # ------------------------------------------------------------ 5. Nginx
  say "5. Nginx: cadangkan konfigurasi lama & pasang vhost baru"
  mkdir -p "$BACKUP/disabled"
  cp -a /etc/nginx "$BACKUP/etc-nginx"
  echo "cadangan: $BACKUP"

  # temukan sertifikat HTTPS yang sudah dipakai situs lama
  CERT=""; KEY=""
  OLD_CONF="$(grep -rIl -iE 'josjis' /etc/nginx/sites-enabled /etc/nginx/conf.d 2>/dev/null || true)"
  if [ -n "$OLD_CONF" ]; then
    CERT="$(grep -hoE 'ssl_certificate[[:space:]]+[^;]+;' $OLD_CONF 2>/dev/null | head -n 1 | sed -E 's/ssl_certificate[[:space:]]+//; s/;$//' || true)"
    KEY="$(grep -hoE 'ssl_certificate_key[[:space:]]+[^;]+;' $OLD_CONF 2>/dev/null | head -n 1 | sed -E 's/ssl_certificate_key[[:space:]]+//; s/;$//' || true)"
  fi
  if [ -n "$CERT" ] && [ -f "$CERT" ] && [ -n "$KEY" ] && [ -f "$KEY" ]; then
    echo "sertifikat HTTPS ditemukan: $CERT"
    HAVE_TLS=1
  else
    echo "sertifikat HTTPS tidak ditemukan; situs dilayani lewat HTTP dulu"
    HAVE_TLS=0
  fi

  NEW_CONF="/etc/nginx/sites-available/josjisnews"
  COMMON="
    root $APP_DIR;
    client_max_body_size 110m;
    server_tokens off;

    location ~ /\\.(?!well-known) { deny all; }
    location ~ ^/(src|database|storage/data|legacy|tests|deploy|\\.github)/ { deny all; }
    location ~ \\.(sqlite|sql|env|md)\$ { deny all; }

    location ^~ /.well-known/acme-challenge/ {
        root $OLD_DIR;
        try_files \$uri =404;
    }

    location / {
        fastcgi_pass unix:$FPM_SOCK;
        include fastcgi_params;
        fastcgi_param HTTPS \$https if_not_empty;
        fastcgi_param SCRIPT_FILENAME \$document_root/router.php;
        fastcgi_param SCRIPT_NAME /router.php;
        fastcgi_param REQUEST_URI \$request_uri;
        fastcgi_read_timeout 60s;
    }"
  if [ "$HAVE_TLS" = 1 ]; then
    cat > "$NEW_CONF" <<NGX
server {
    listen 80;
    server_name $SERVER_NAMES;
    location ^~ /.well-known/acme-challenge/ { root $OLD_DIR; try_files \$uri =404; }
    location / { return 301 https://\$host\$request_uri; }
}
server {
    listen 443 ssl;
    server_name $SERVER_NAMES;
    ssl_certificate $CERT;
    ssl_certificate_key $KEY;
    ssl_protocols TLSv1.2;
    ssl_prefer_server_ciphers on;
$COMMON
}
NGX
  else
    cat > "$NEW_CONF" <<NGX
server {
    listen 80;
    server_name $SERVER_NAMES;
$COMMON
}
NGX
  fi

  rollback() {
    printf '\n\033[31m!! Mengembalikan konfigurasi Nginx lama...\033[0m\n'
    rm -f /etc/nginx/sites-enabled/josjisnews
    for f in "$BACKUP"/disabled/*; do
      [ -e "$f" ] || continue
      case "$(cat "$BACKUP/disabled/.origin-$(basename "$f")" 2>/dev/null)" in
        "") ;;
        *) cp -a "$f" "$(cat "$BACKUP/disabled/.origin-$(basename "$f")")" ;;
      esac
    done
    nginx -t 2>/dev/null && systemctl reload nginx || true
    echo "Konfigurasi lama dipulihkan. Cadangan: $BACKUP"
  }

  # nonaktifkan konfigurasi lama yang menyebut josjis (disimpan di cadangan, bisa dipulihkan)
  for f in $OLD_CONF; do
    base="$(basename "$f")"
    cp -a "$f" "$BACKUP/disabled/$base"
    printf '%s' "$f" > "$BACKUP/disabled/.origin-$base"
    rm -f "$f"
  done
  ln -sf "$NEW_CONF" /etc/nginx/sites-enabled/josjisnews

  if ! nginx -t; then rollback; die "nginx -t gagal (konfigurasi lama sudah dipulihkan)"; fi
  systemctl reload "$FPM_SVC" || service "$FPM_SVC" reload || true
  systemctl reload nginx

  # ------------------------------------------------------- 6. health check
  say "6. Health check"
  sleep 2
  if [ "$HAVE_TLS" = 1 ]; then URL="https://127.0.0.1/api/v1/health"; else URL="http://127.0.0.1/api/v1/health"; fi
  OK=0
  for i in 1 2 3 4 5; do
    if curl -fsSk --max-time 15 -H "Host: $DOMAIN" "$URL" | grep -q '"status":"success"'; then OK=1; break; fi
    sleep 3
  done
  if [ "$OK" != 1 ]; then
    echo "--- log galat nginx:"; tail -n 15 /var/log/nginx/error.log 2>/dev/null || true
    rollback
    die "health check gagal (konfigurasi lama dipulihkan; folder lama tidak disentuh)"
  fi

  say "SELESAI: kode baru terpasang dan situs merespons"
  echo "Situs      : https://$DOMAIN"
  echo "Panel      : https://$DOMAIN/admin.html"
  if [ -f "$APP_DIR/storage/data/INITIAL_ADMIN_PASSWORD.txt" ]; then
    echo "Sandi awal : $APP_DIR/storage/data/INITIAL_ADMIN_PASSWORD.txt  (baca: cat berkas itu, lalu hapus setelah ganti password)"
  fi
  echo "Cadangan   : $BACKUP (konfigurasi Nginx lama)"
  echo "Rollback   : rm /etc/nginx/sites-enabled/josjisnews && cp -a $BACKUP/disabled/* <lokasi asal> && nginx -t && systemctl reload nginx"
}

main "$@"
exit
