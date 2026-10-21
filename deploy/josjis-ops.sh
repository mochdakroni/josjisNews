#!/usr/bin/env bash
# Pembungkus SSH "baca-saja" untuk diagnosis (dipakai sebagai forced command, tanpa shell).
# Terpasang sebagai /usr/local/bin/josjis-ops oleh server-setup.sh.
#
#   ssh -i ~/.ssh/josjis_ops josjis-ops@HOST status | health | version | files
#   ssh -i ~/.ssh/josjis_ops josjis-ops@HOST logs-nginx [N] | logs-access [N] | logs-php [N]
#
# Tidak ada perintah yang mengubah apa pun, tidak ada eval, argumen divalidasi ketat.
set -u
APP_DIR="${APP_DIR:-/var/www/josjisnews}"

read -r cmd arg extra <<<"${SSH_ORIGINAL_COMMAND:-}"

lines() {
  local n="${1:-100}"
  [[ "$n" =~ ^[0-9]{1,3}$ ]] && [ "$n" -ge 1 ] && [ "$n" -le 500 ] || n=100
  echo "$n"
}
tail_file() { # $1=berkas, $2=jumlah baris
  [ -r "$1" ] && tail -n "$(lines "${2:-}")" "$1" || echo "(tidak bisa membaca $1)"
}

if [ -n "${extra:-}" ]; then echo "Terlalu banyak argumen" >&2; exit 2; fi

case "${cmd:-}" in
  status)
    echo "== layanan"; for s in nginx $(systemctl list-units --type=service --no-legend 'php*-fpm.service' | awk '{print $1}'); do
      printf '%-24s %s\n' "$s" "$(systemctl is-active "$s")"; done
    echo "== uptime"; uptime
    echo "== disk"; df -h / | tail -n 2
    echo "== memori"; free -m | head -n 2
    ;;
  health)
    curl -fsS --max-time 10 -H "Host: ${HEALTH_HOST:-localhost}" http://127.0.0.1/api/v1/health || { echo "health check GAGAL"; exit 1; }
    echo
    ;;
  version)
    php -v | head -n 1; nginx -v 2>&1; sqlite3 --version 2>/dev/null | head -n 1
    ;;
  files)
    ls -la "$APP_DIR" | head -n 60
    ;;
  logs-nginx)  tail_file /var/log/nginx/error.log  "${arg:-}" ;;
  logs-access) tail_file /var/log/nginx/access.log "${arg:-}" ;;
  logs-php)
    f="$(ls -1 /var/log/php*-fpm.log 2>/dev/null | head -n 1)"
    if [ -n "$f" ]; then tail_file "$f" "${arg:-}"; else echo "(log php-fpm tidak ditemukan)"; fi
    ;;
  ""|help)
    echo "Perintah: status | health | version | files | logs-nginx [N] | logs-access [N] | logs-php [N]"
    ;;
  *)
    echo "Perintah tidak diizinkan: akses ini hanya untuk diagnosis baca-saja." >&2
    exit 2
    ;;
esac
