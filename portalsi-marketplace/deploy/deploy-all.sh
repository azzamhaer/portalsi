#!/usr/bin/env bash
set -euo pipefail

REPO_DIR="${REPO_DIR:-/opt/portalsi-marketplace/source}"
BRANCH="${BRANCH:-main}"

echo "==> Update repo"
echo "Repo:   $REPO_DIR"
echo "Branch: $BRANCH"

if [ ! -d "$REPO_DIR/.git" ]; then
  echo "ERROR: $REPO_DIR belum berisi repo git."
  echo "Clone dulu, contoh:"
  echo "git clone <URL_REPO> $REPO_DIR"
  exit 1
fi

cd "$REPO_DIR"
git fetch origin "$BRANCH"
git checkout "$BRANCH"
git pull --ff-only origin "$BRANCH"

bash "$REPO_DIR/deploy/deploy-backend.sh"
bash "$REPO_DIR/deploy/deploy-frontend.sh"

echo "==> Deploy semua selesai."
