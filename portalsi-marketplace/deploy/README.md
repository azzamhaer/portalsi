# Deploy Scripts

Script ini dipakai di VPS CyberPanel untuk monorepo:

```text
source/
  backend/
  frontend/
  deploy/
```

## Setup Pertama di VPS

Clone repo ke folder source, bukan ke folder domain CyberPanel:

```bash
mkdir -p /opt/portalsi-marketplace
git clone <URL_REPO> /opt/portalsi-marketplace/source
cd /opt/portalsi-marketplace/source
chmod +x deploy/*.sh
```

Jika repo private, pakai SSH key deploy atau GitHub personal access token.

## Deploy Semua

```bash
cd /opt/portalsi-marketplace/source
bash deploy/deploy-all.sh
```

## Deploy Backend Saja

```bash
cd /opt/portalsi-marketplace/source
git pull origin main
bash deploy/deploy-backend.sh
```

## Deploy Frontend Saja

```bash
cd /opt/portalsi-marketplace/source
git pull origin main
bash deploy/deploy-frontend.sh
```

## Override Variable Jika Perlu

```bash
REPO_DIR=/opt/portalsi-marketplace/source \
BACKEND_DIR=/home/api-marketplace.portalsi.com/public_html \
FRONTEND_DIR=/home/marketplace.portalsi.com/public_html \
API_URL=https://api-marketplace.portalsi.com/api \
FRONTEND_ORIGIN=https://marketplace.portalsi.com \
bash deploy/deploy-all.sh
```
