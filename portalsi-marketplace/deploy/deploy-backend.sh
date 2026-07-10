#!/usr/bin/env bash
set -euo pipefail

REPO_DIR="${REPO_DIR:-/opt/portalsi-marketplace/source}"
BACKEND_DIR="${BACKEND_DIR:-/home/api-marketplace.portalsi.com/public_html}"
WEB_USER="${WEB_USER:-marke9597}"
WEB_GROUP="${WEB_GROUP:-marke9597}"

echo "==> Deploy backend Laravel"
echo "Repo:    $REPO_DIR"
echo "Target:  $BACKEND_DIR"

if [ ! -d "$REPO_DIR/backend" ]; then
  echo "ERROR: $REPO_DIR/backend tidak ditemukan."
  echo "Clone repo dulu ke $REPO_DIR."
  exit 1
fi

mkdir -p "$BACKEND_DIR"

rsync -a --delete \
  --exclude=".env" \
  --exclude="storage" \
  --exclude="vendor" \
  "$REPO_DIR/backend/" \
  "$BACKEND_DIR/"

cd "$BACKEND_DIR"

composer install --no-dev --optimize-autoloader

php artisan migrate --force
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache

mkdir -p storage bootstrap/cache
chown -R "$WEB_USER:$WEB_GROUP" storage bootstrap/cache || true
chmod -R 775 storage bootstrap/cache

echo "==> Backend selesai."
