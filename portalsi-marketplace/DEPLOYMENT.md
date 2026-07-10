# Deployment MPSI Marketplace ke CyberPanel/OpenLiteSpeed

Target production:

- Frontend SvelteKit Node SSR: `https://marketplace.portalsi.com`
- Backend Laravel API: `https://api-marketplace.portalsi.com`
- Node frontend listen lokal: `127.0.0.1:3001`

Panduan ini aman dipakai untuk deploy awal maupun update revisi.

## 1. Persiapan VPS

Login SSH:

```bash
ssh root@IP_VPS
```

Pastikan dependency tersedia:

```bash
node -v
npm -v
php -v
composer -V
pm2 -v
```

Rekomendasi:

- Node.js 20 LTS
- PHP 8.2+
- Composer 2
- PM2 global

Install PM2 jika belum ada:

```bash
npm install -g pm2
```

## 2. DNS dan CyberPanel

Buat dua website/subdomain di CyberPanel:

- `marketplace.portalsi.com`
- `api-marketplace.portalsi.com`

Pastikan DNS A record keduanya mengarah ke IP VPS.

Issue SSL untuk keduanya lewat:

```text
CyberPanel -> SSL -> Manage SSL -> Issue SSL
```

## 3. Deploy Backend Laravel

Folder backend:

```bash
cd /home/api-marketplace.portalsi.com/public_html
```

Upload isi folder lokal `backend/` ke folder tersebut. Setelah upload:

```bash
composer install --no-dev --optimize-autoloader
```

Buat atau edit `.env`:

```env
APP_NAME=MPSI
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api-marketplace.portalsi.com

FRONTEND_URL=https://marketplace.portalsi.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nama_database
DB_USERNAME=user_database
DB_PASSWORD=password_database

TRIPAY_MODE=production
TRIPAY_API_KEY=
TRIPAY_PRIVATE_KEY=
TRIPAY_MERCHANT_CODE=

BREVO_API_KEY=
BREVO_SENDER_EMAIL=
BREVO_SENDER_NAME=MPSI
```

Generate key jika deploy pertama:

```bash
php artisan key:generate
```

Jalankan migrasi dan cache:

```bash
php artisan migrate --force
php artisan db:seed --class=SettingsSeeder --force
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
```

Set permission:

```bash
chown -R marke9597:marke9597 storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

Sesuaikan user `marke9597` dengan user website CyberPanel jika berbeda.

## 4. Backend Document Root

Document root API harus mengarah ke folder Laravel `public`.

Di CyberPanel:

```text
Websites -> List Websites -> Manage api-marketplace.portalsi.com -> vHost Conf
```

Pastikan:

```text
docRoot $VH_ROOT/public_html/public
```

Restart OpenLiteSpeed:

```bash
systemctl restart lsws
```

Test API:

```bash
curl -I https://api-marketplace.portalsi.com/api/settings/public
```

Harus 200 dan return JSON jika dibuka di browser.

## 5. Build Frontend di Lokal

Di komputer lokal:

```bash
cd frontend
```

Pastikan `.env.production` berisi:

```env
PUBLIC_API_URL=https://api-marketplace.portalsi.com/api
```

Penting: file `.env.production` harus UTF-8, bukan UTF-16. Cek hasil build agar tidak ada `localhost:8000`:

```bash
npm install
npm run build
grep -R "localhost:8000" -n build .svelte-kit 2>/dev/null
grep -R "api-marketplace.portalsi.com" -n build .svelte-kit 2>/dev/null
```

Jika masih ada `localhost:8000`, hapus cache dan build ulang:

```bash
rm -rf build .svelte-kit
npm run build
```

Upload ke server file/folder berikut dari folder `frontend`:

- `build/`
- `package.json`
- `package-lock.json`

Struktur setelah upload ke `/home/marketplace.portalsi.com/public_html` minimal:

```text
build/
package.json
package-lock.json
```

## 6. Jalankan Frontend dengan PM2

Di server:

```bash
cd /home/marketplace.portalsi.com/public_html
npm ci --omit=dev
pm2 delete mpsi-frontend
PORT=3001 HOST=127.0.0.1 ORIGIN=https://marketplace.portalsi.com pm2 start build/index.js --name mpsi-frontend
pm2 save
```

Jika sebelumnya Anda mengekstrak isi `build/` langsung ke root `public_html`, command-nya menjadi:

```bash
PORT=3001 HOST=127.0.0.1 ORIGIN=https://marketplace.portalsi.com pm2 start index.js --name mpsi-frontend
```

Rekomendasi: simpan tetap di folder `build/`, lalu jalankan `build/index.js` agar rapi.

Test Node lokal:

```bash
curl -I http://127.0.0.1:3001
pm2 logs mpsi-frontend
```

## 7. Reverse Proxy OpenLiteSpeed ke Node

Di CyberPanel:

```text
Websites -> List Websites -> Manage marketplace.portalsi.com -> vHost Conf
```

Gunakan konfigurasi seperti ini:

```text
docRoot                   $VH_ROOT/public_html
vhDomain                  $VH_NAME
vhAliases                 www.$VH_NAME
adminEmails               admin@portalsi.com
enableGzip                1
enableIpGeo               1

index  {
  useServer               0
  indexFiles              index.html
}

errorlog $VH_ROOT/logs/$VH_NAME.error_log {
  useServer               0
  logLevel                WARN
  rollingSize             10M
}

accesslog $VH_ROOT/logs/$VH_NAME.access_log {
  useServer               0
  logFormat               "%h %l %u %t \"%r\" %>s %b \"%{Referer}i\" \"%{User-Agent}i\""
  logHeaders              5
  rollingSize             10M
  keepDays                10
  compressArchive         1
}

extprocessor mpsi-node {
  type                    proxy
  address                 http://127.0.0.1:3001
  maxConns                100
  initTimeout             60
  retryTimeout            0
  respBuffer              0
}

rewrite  {
  enable                  1
  autoLoadHtaccess        0
}

context / {
  type                    proxy
  handler                 mpsi-node
  allowBrowse             1
  addDefaultCharset       off

  rewrite  {
    enable                0
  }
}

context /.well-known/acme-challenge {
  location                /usr/local/lsws/Example/html/.well-known/acme-challenge
  allowBrowse             1

  rewrite  {
    enable                0
  }
  addDefaultCharset       off
}
```

Biarkan blok `vhssl` yang sudah dibuat CyberPanel tetap ada.

Restart:

```bash
systemctl restart lsws
```

Test:

```bash
curl -I http://127.0.0.1:3001
curl -I https://marketplace.portalsi.com
```

Keduanya harus 200.

## 8. Update Revisi Baru

Cara paling rapi untuk update berikutnya adalah memakai folder source Git terpisah:

```text
/opt/portalsi-marketplace/source
```

Folder domain CyberPanel tetap hanya berisi hasil deploy:

```text
/home/api-marketplace.portalsi.com/public_html
/home/marketplace.portalsi.com/public_html
```

Setup pertama:

```bash
mkdir -p /opt/portalsi-marketplace
git clone <URL_REPO> /opt/portalsi-marketplace/source
cd /opt/portalsi-marketplace/source
chmod +x deploy/*.sh
```

Deploy semua:

```bash
cd /opt/portalsi-marketplace/source
bash deploy/deploy-all.sh
```

Deploy backend saja:

```bash
cd /opt/portalsi-marketplace/source
git pull origin main
bash deploy/deploy-backend.sh
```

Deploy frontend saja:

```bash
cd /opt/portalsi-marketplace/source
git pull origin main
bash deploy/deploy-frontend.sh
```

Script tersedia di folder `deploy/`.

Backend:

```bash
cd /home/api-marketplace.portalsi.com/public_html
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
```

Frontend:

```bash
cd /home/marketplace.portalsi.com/public_html
npm ci --omit=dev
pm2 restart mpsi-frontend --update-env
```

Jika upload build baru, stop dulu supaya tidak tercampur file lama:

```bash
cd /home/marketplace.portalsi.com/public_html
pm2 stop mpsi-frontend
rm -rf build
```

Upload folder `build/` baru, lalu:

```bash
pm2 restart mpsi-frontend --update-env
```

## 9. Cek Error dan Log

Frontend Node:

```bash
pm2 status
pm2 logs mpsi-frontend --lines 100
```

OpenLiteSpeed:

```bash
tail -100 /usr/local/lsws/logs/error.log
tail -100 /home/marketplace.portalsi.com/logs/marketplace.portalsi.com.error_log
tail -100 /home/marketplace.portalsi.com/logs/marketplace.portalsi.com.access_log
```

Laravel:

```bash
tail -100 /home/api-marketplace.portalsi.com/public_html/storage/logs/laravel.log
```

Cek API dari server:

```bash
curl -I https://api-marketplace.portalsi.com/api/settings/public
curl https://api-marketplace.portalsi.com/api/home
```

Cek frontend tidak membaca localhost:

```bash
cd /home/marketplace.portalsi.com/public_html
grep -R "localhost:8000" -n build node_modules --exclude-dir=node_modules 2>/dev/null
grep -R "api-marketplace.portalsi.com" -n build 2>/dev/null
```

## 10. Cloudflare

Jika memakai Cloudflare dan hasil deploy masih tampak lama:

1. Buka Cloudflare dashboard.
2. Pilih domain `portalsi.com`.
3. Caching -> Configuration.
4. Purge Everything.

Lalu hard refresh browser:

- Windows/Linux: `Ctrl + Shift + R`
- DevTools: centang `Disable cache`, lalu reload.

## 11. Cron Laravel

Tambahkan scheduler:

```bash
crontab -e
```

Isi:

```text
* * * * * cd /home/api-marketplace.portalsi.com/public_html && php artisan schedule:run >> /dev/null 2>&1
```

## 12. Checklist Production

- `APP_DEBUG=false`
- `APP_URL=https://api-marketplace.portalsi.com`
- `FRONTEND_URL=https://marketplace.portalsi.com`
- `.env.production` frontend berisi `PUBLIC_API_URL=https://api-marketplace.portalsi.com/api`
- `npm run build` tidak mengandung `localhost:8000`
- `php artisan migrate --force` sudah jalan
- PM2 status `online`
- OLS context `/` proxy ke `http://127.0.0.1:3001`
- SSL aktif untuk frontend dan backend
- Admin bisa akses `/admin/catalog` untuk kelola kategori/tag
- Admin bisa matikan hero dari `/admin/appearance`
