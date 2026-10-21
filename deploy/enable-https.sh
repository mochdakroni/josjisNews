#!/usr/bin/env bash
# Mengaktifkan HTTPS (Let's Encrypt via acme.sh) untuk situs yang dipasang oleh adopt-server.sh.
# Cocok untuk server lama (Ubuntu 16.04) tempat certbot modern tidak tersedia.
#
#   ssh root@SERVER "EMAIL=redaksi@josjis.news bash -s" < deploy/enable-https.sh
#
# Aman diulang. Konfigurasi Nginx dicadangkan dan DIKEMBALIKAN otomatis bila uji/health check gagal.
# Syarat: DNS A record domain sudah mengarah ke IP server, dan port 80/443 terbuka.

main() {
  set -euo pipefail

  DOMAIN="${DOMAIN:-josjis.news}"
  EMAIL="${EMAIL:-}"
  APP_DIR="${APP_DIR:-/var/www/josjisnews}"
  ACME_ROOT="${ACME_ROOT:-/var/www/acme}"
  SSL_DIR="/etc/nginx/ssl/$DOMAIN"
  CONF="/etc/nginx/sites-available/josjisnews"
  TS="$(date +%Y%m%d-%H%M%S)"
  BACKUP="/root/nginx-https-backup-$TS"

  say() { printf '\n\033[1m>> %s\033[0m\n' "$*"; }
  die() { printf '\n\033[31mGAGAL: %s\033[0m\n' "$*" >&2; exit 1; }

  [ "$(id -u)" -eq 0 ] || die "jalankan sebagai root"
  [ -n "$EMAIL" ] || die "isi EMAIL (untuk pendaftaran sertifikat)"
  [ -f "$CONF" ] || die "$CONF tidak ada. Jalankan adopt-server.sh dulu."

  # nama untuk sertifikat: domain utama + www bila DNS-nya ada
  NAMES="$DOMAIN"
  if getent hosts "www.$DOMAIN" >/dev/null 2>&1; then NAMES="$NAMES www.$DOMAIN"; fi
  SERVER_NAMES="$NAMES server1.$DOMAIN"
  FPM_SOCK="$(ls /run/php/php*-fpm.sock 2>/dev/null | head -n 1 || true)"
  [ -n "$FPM_SOCK" ] || die "socket php-fpm tidak ditemukan"

  mkdir -p "$BACKUP" "$ACME_ROOT/.well-known/acme-challenge" "$SSL_DIR"
  chmod 700 "$SSL_DIR"
  cp -a "$CONF" "$BACKUP/josjisnews.conf"

  write_conf() { # $1 = http | tls
    COMMON="
    root $APP_DIR;
    client_max_body_size 110m;
    server_tokens off;

    location ~ /\\.(?!well-known) { deny all; }
    location ~ ^/(src|database|storage/data|legacy|tests|deploy|\\.github)/ { deny all; }
    location ~ \\.(sqlite|sql|env|md)\$ { deny all; }

    location ^~ /.well-known/acme-challenge/ {
        root $ACME_ROOT;
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
    if [ "$1" = tls ]; then
      cat > "$CONF" <<NGX
server {
    listen 80;
    server_name $SERVER_NAMES;
    location ^~ /.well-known/acme-challenge/ { root $ACME_ROOT; try_files \$uri =404; }
    location / { return 301 https://\$host\$request_uri; }
}
server {
    listen 443 ssl;
    server_name $SERVER_NAMES;
    ssl_certificate $SSL_DIR/fullchain.pem;
    ssl_certificate_key $SSL_DIR/key.pem;
    ssl_protocols TLSv1.2;
    ssl_prefer_server_ciphers on;
$COMMON
}
NGX
    else
      cat > "$CONF" <<NGX
server {
    listen 80;
    server_name $SERVER_NAMES;
$COMMON
}
NGX
    fi
  }

  restore() {
    printf '\n\033[31m!! Mengembalikan konfigurasi Nginx sebelumnya...\033[0m\n'
    cp -a "$BACKUP/josjisnews.conf" "$CONF"
    nginx -t 2>/dev/null && systemctl reload nginx || true
  }

  # ---- 1. pastikan jalur validasi ACME terbuka di port 80
  say "1. Menyiapkan jalur validasi di port 80"
  write_conf http
  nginx -t || { restore; die "nginx -t gagal"; }
  systemctl reload nginx

  # ---- 2. acme.sh
  say "2. acme.sh"
  ACME="/root/.acme.sh/acme.sh"
  if [ ! -x "$ACME" ]; then
    command -v git >/dev/null || die "git tidak ada"
    TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
    git clone --depth 1 https://github.com/acmesh-official/acme.sh.git "$TMP/acme" </dev/null
    (cd "$TMP/acme" && ./acme.sh --install -m "$EMAIL" </dev/null)
  fi
  [ -x "$ACME" ] || die "acme.sh gagal dipasang"
  "$ACME" --set-default-ca --server letsencrypt </dev/null || true

  # ---- 3. terbitkan sertifikat
  say "3. Menerbitkan sertifikat untuk: $NAMES"
  ARGS=""
  for n in $NAMES; do ARGS="$ARGS -d $n"; done
  rc=0
  # shellcheck disable=SC2086
  "$ACME" --issue --server letsencrypt $ARGS -w "$ACME_ROOT" </dev/null || rc=$?
  # kode 2 = sertifikat masih berlaku (dilewati) -> tetap lanjut
  if [ "$rc" -ne 0 ] && [ "$rc" -ne 2 ]; then
    restore
    die "penerbitan sertifikat gagal (kode $rc). Pastikan DNS $DOMAIN mengarah ke server ini dan port 80 terbuka dari internet. Konfigurasi dipulihkan."
  fi

  say "4. Memasang sertifikat"
  "$ACME" --install-cert -d "$DOMAIN" \
    --key-file "$SSL_DIR/key.pem" \
    --fullchain-file "$SSL_DIR/fullchain.pem" \
    --reloadcmd "systemctl reload nginx" </dev/null
  chmod 600 "$SSL_DIR/key.pem"
  [ -s "$SSL_DIR/fullchain.pem" ] && [ -s "$SSL_DIR/key.pem" ] || { restore; die "berkas sertifikat kosong"; }

  # ---- 5. aktifkan HTTPS
  say "5. Mengaktifkan HTTPS di Nginx"
  write_conf tls
  nginx -t || { restore; die "nginx -t gagal setelah mengaktifkan HTTPS (konfigurasi dipulihkan)"; }
  systemctl reload nginx

  # ---- 6. verifikasi
  say "6. Verifikasi"
  sleep 2
  OK=0
  for i in 1 2 3 4 5; do
    if curl -fsS --max-time 15 --resolve "$DOMAIN:443:127.0.0.1" "https://$DOMAIN/api/v1/health" | grep -q '"status":"success"'; then OK=1; break; fi
    sleep 3
  done
  if [ "$OK" != 1 ]; then restore; die "health check HTTPS gagal (konfigurasi dipulihkan)"; fi
  curl -fsSI --max-time 10 --resolve "$DOMAIN:80:127.0.0.1" "http://$DOMAIN/" | head -n 3 || true

  say "SELESAI: https://$DOMAIN aktif, HTTP dialihkan ke HTTPS"
  echo "Sertifikat : $SSL_DIR (diperpanjang otomatis oleh cron acme.sh)"
  echo "Cadangan   : $BACKUP"
  echo "Cek jadwal : crontab -l | grep acme"
}

main "$@"
exit
