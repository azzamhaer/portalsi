#!/usr/bin/env bash
set -euo pipefail

REPO_DIR="${REPO_DIR:-/opt/portalsi-marketplace/source}"
FRONTEND_DIR="${FRONTEND_DIR:-/home/marketplace.portalsi.com/public_html}"
API_URL="${API_URL:-https://api-marketplace.portalsi.com/api}"
FRONTEND_ORIGIN="${FRONTEND_ORIGIN:-https://marketplace.portalsi.com}"
FRONTEND_PORT="${FRONTEND_PORT:-3001}"
PM2_NAME="${PM2_NAME:-mpsi-frontend}"

echo "==> Deploy frontend SvelteKit"
echo "Repo:    $REPO_DIR"
echo "Target:  $FRONTEND_DIR"
echo "API URL: $API_URL"

if [ ! -d "$REPO_DIR/frontend" ]; then
  echo "ERROR: $REPO_DIR/frontend tidak ditemukan."
  echo "Clone repo dulu ke $REPO_DIR."
  exit 1
fi

cd "$REPO_DIR/frontend"
printf "PUBLIC_API_URL=%s\n" "$API_URL" > .env.production

npm ci
rm -rf build .svelte-kit
npm run build

if grep -R "localhost:8000" -n build .svelte-kit >/dev/null 2>&1; then
  echo "ERROR: hasil build masih mengandung localhost:8000."
  exit 1
fi

mkdir -p "$FRONTEND_DIR"
rm -rf "$FRONTEND_DIR/build"
cp -R build "$FRONTEND_DIR/build"
cp package.json package-lock.json "$FRONTEND_DIR/"

cd "$FRONTEND_DIR"
npm ci --omit=dev

if pm2 describe "$PM2_NAME" >/dev/null 2>&1; then
  PORT="$FRONTEND_PORT" HOST=127.0.0.1 ORIGIN="$FRONTEND_ORIGIN" pm2 restart "$PM2_NAME" --update-env
else
  PORT="$FRONTEND_PORT" HOST=127.0.0.1 ORIGIN="$FRONTEND_ORIGIN" pm2 start build/index.js --name "$PM2_NAME"
fi

pm2 save

echo "==> Frontend selesai."
