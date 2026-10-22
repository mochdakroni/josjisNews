#!/usr/bin/env bash
# Pemasangan awal server JosjisNews (Ubuntu 22.04 / 24.04, jalankan sebagai root, aman diulang).
#
#   sudo DOMAIN=josjis.news EMAIL=redaksi@josjis.news bash server-setup.sh
#
# Hasil: Nginx + PHP-FPM, pengguna "deploy" (login hanya SSH key), folder /var/www/josjisnews,
# konfigurasi Nginx aman, dan (opsional) HTTPS Let's Encrypt.
set -euo pipefail

DOMAIN="${DOMAIN:?isi DOMAIN, mis. josjis.news}"
EMAIL="${EMAIL:?isi EMAIL untuk sertifikat HTTPS}"
APP_DIR="${APP_DIR:-/var/www/josjisnews}"
DEPLOY_USER="${DEPLOY_USER:-deploy}"
DEPLOY_PUBKEY="${DEPLOY_PUBKEY:-}"          # isi berkas .pub kunci deploy (opsional, bisa ditambah nanti)
OPS_PUBKEY="${OPS_PUBKEY:-}"                # isi berkas .pub kunci diagnosis baca-saja untuk Claude (opsional)
OPS_USER="${OPS_USER:-josjis-ops}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

[ "$(id -u)" -eq 0 ] || { echo "Jalankan sebagai root"; exit 1; }
. /etc/os-release
case "$VERSION_ID" in
  22.04|24.04) ;;
  *) echo "Peringatan: diuji untuk Ubuntu 22.04/24.04, Anda memakai $VERSION_ID (PHP bawaan mungkin < 7.4)";;
esac

export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y nginx php-fpm php-cli php-sqlite3 php-mbstring php-xml php-curl sqlite3 rsync curl ufw certbot python3-certbot-nginx

PHP_VER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
echo ">> PHP $PHP_VER"
php -r 'exit(PHP_VERSION_ID >= 70400 ? 0 : 1);' || { echo "PHP >= 7.4 diperlukan"; exit 1; }

# --- pengguna deploy (tanpa password, hanya SSH key)
id "$DEPLOY_USER" >/dev/null 2>&1 || adduser --disabled-password --gecos "" "$DEPLOY_USER"
install -d -m 700 -o "$DEPLOY_USER" -g "$DEPLOY_USER" "/home/$DEPLOY_USER/.ssh"
if [ -n "$DEPLOY_PUBKEY" ]; then
  grep -qxF "$DEPLOY_PUBKEY" "/home/$DEPLOY_USER/.ssh/authorized_keys" 2>/dev/null || echo "$DEPLOY_PUBKEY" >> "/home/$DEPLOY_USER/.ssh/authorized_keys"
  chmod 600 "/home/$DEPLOY_USER/.ssh/authorized_keys"; chown "$DEPLOY_USER:$DEPLOY_USER" "/home/$DEPLOY_USER/.ssh/authorized_keys"
fi

# --- akses diagnosis baca-saja (forced command, tanpa shell, tanpa sudo)
if [ -f "$SCRIPT_DIR/josjis-ops.sh" ]; then
  install -m 755 -o root -g root "$SCRIPT_DIR/josjis-ops.sh" /usr/local/bin/josjis-ops
  id "$OPS_USER" >/dev/null 2>&1 || adduser --disabled-password --gecos "" "$OPS_USER"
  usermod -aG adm "$OPS_USER"                 # boleh membaca /var/log/nginx & php-fpm
  install -d -m 700 -o "$OPS_USER" -g "$OPS_USER" "/home/$OPS_USER/.ssh"
  if [ -n "$OPS_PUBKEY" ]; then
    LINE="command=\"/usr/local/bin/josjis-ops\",restrict $OPS_PUBKEY"
    echo "$LINE" > "/home/$OPS_USER/.ssh/authorized_keys"
    chmod 600 "/home/$OPS_USER/.ssh/authorized_keys"; chown "$OPS_USER:$OPS_USER" "/home/$OPS_USER/.ssh/authorized_keys"
  fi
else
  echo "(josjis-ops.sh tidak ditemukan di $SCRIPT_DIR, akses diagnosis dilewati; kirim seluruh folder deploy/)"
fi

# --- folder aplikasi: kode milik deploy, data tulis milik www-data
install -d -o "$DEPLOY_USER" -g www-data -m 750 "$APP_DIR"
install -d -o www-data -g www-data -m 700 "$APP_DIR/storage/data"
for d in images videos documents; do install -d -o www-data -g www-data -m 755 "$APP_DIR/storage/uploads/$d"; done
if [ ! -f "$APP_DIR/.env" ]; then
  cat > "$APP_DIR/.env" <<ENV
APP_URL=https://$DOMAIN
ALLOWED_HOSTS=$DOMAIN,www.$DOMAIN
SITE_NAME=JosjisNews
SITE_TAGLINE="Bloko Sutho"
COMPANY_NAME="PT Sendhiko Dawuh Multiguna"
CONTACT_EMAIL=redaksi@$DOMAIN
ADS_EMAIL=iklan@$DOMAIN
TRUSTED_PROXY=0
REQUIRE_2FA=0
ENV
  chown "$DEPLOY_USER:www-data" "$APP_DIR/.env"; chmod 640 "$APP_DIR/.env"
fi

# --- batas unggahan PHP (selaras dengan 100 MB video)
cat > "/etc/php/$PHP_VER/fpm/conf.d/99-josjis.ini" <<INI
upload_max_filesize = 100M
post_max_size = 110M
expose_php = Off
display_errors = Off
log_errors = On
INI

# --- Nginx
cat > /etc/nginx/sites-available/josjisnews <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN www.$DOMAIN;
    root $APP_DIR;
    client_max_body_size 110m;
    server_tokens off;

    location ~ /\.(?!well-known) { deny all; }
    location ~ ^/(src|database|storage/data|legacy|tests|deploy|\.github)/ { deny all; }
    location ~ \.(sqlite|sql|env|md)\$ { deny all; }

    location / {
        fastcgi_pass unix:/run/php/php$PHP_VER-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root/router.php;
        fastcgi_param SCRIPT_NAME /router.php;
        fastcgi_param REQUEST_URI \$request_uri;
        fastcgi_read_timeout 60s;
    }
}
NGINX
ln -sf /etc/nginx/sites-available/josjisnews /etc/nginx/sites-enabled/josjisnews
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl enable --now "php$PHP_VER-fpm" nginx
systemctl reload "php$PHP_VER-fpm"; systemctl reload nginx

# --- firewall: hanya SSH + web
ufw allow OpenSSH >/dev/null; ufw allow 'Nginx Full' >/dev/null; ufw --force enable >/dev/null

# --- HTTPS (butuh DNS A record domain -> IP server sudah aktif)
if getent hosts "$DOMAIN" >/dev/null; then
  certbot --nginx -d "$DOMAIN" -d "www.$DOMAIN" -m "$EMAIL" --agree-tos --no-eff-email --redirect -n \
    || echo "!! Certbot gagal (cek DNS). Ulangi: certbot --nginx -d $DOMAIN -d www.$DOMAIN"
fi

cat <<DONE

Selesai. Langkah berikut:
 1) Tambahkan kunci publik deploy:  echo '<isi .pub>' >> /home/$DEPLOY_USER/.ssh/authorized_keys
 2) Isi secrets GitHub (lihat deploy/README.md) lalu push ke main.
 3) Setelah deploy pertama, buka https://$DOMAIN/admin.html — sandi admin awal ada di
    $APP_DIR/storage/data/INITIAL_ADMIN_PASSWORD.txt (hapus setelah ganti password).
 4) Nonaktifkan login SSH root & password (PermitRootLogin no, PasswordAuthentication no) setelah kunci SSH berfungsi.
DONE
