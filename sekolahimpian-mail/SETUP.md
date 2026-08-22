# Sekolah Impian Mail — panduan setup (standalone)

Produk mail terpisah dari Portal SI: **web** (SvelteKit, copy UI portalsi-mail) + **api** (Laravel, auth & DB sendiri). Domain: `mail.sekolahimpian.com` (webmail) + `mailapi.sekolahimpian.com` (API). Mailbox dibuat di user HestiaCP **sekim**, domain **sekolahimpian.com**.

Struktur repo: `sekolahimpian-mail/web` (SvelteKit) & `sekolahimpian-mail/api` (file Laravel siap di-overlay).

---

## 1. Database (HestiaCP)
Buat DB MySQL khusus, mis. lewat panel atau CLI:
```bash
v-add-database sekim mail 'sekimmail' 'PASSWORD_KUAT' mysql
# hasil: DB = sekim_mail, user = sekim_mailuser (nama bisa beda; catat)
```

## 2. Web domain untuk API (mailapi.sekolahimpian.com)
```bash
v-add-web-domain sekim mailapi.sekolahimpian.com
v-add-web-domain-ssl sekim mailapi.sekolahimpian.com   # Let's Encrypt
```
DNS: A record `mailapi.sekolahimpian.com` → IP server (DNS only / abu-abu).

## 3. Scaffold Laravel + overlay file kita
Jalankan sebagai user sekim (bukan root):
```bash
cd /home/sekim/web/mailapi.sekolahimpian.com
sudo -u sekim composer create-project laravel/laravel api
cd api
sudo -u sekim php artisan install:api      # pasang Sanctum + routes/api.php + migrasi token
```
Lalu salin isi folder `sekolahimpian-mail/api/` dari repo ini menimpa hasil scaffold:
`app/Models/{User,MailSetting,MailAccount}.php`, `app/Http/Controllers/{AuthController,MailController}.php`, `app/Services/HestiaMailClient.php`, `routes/api.php`, dan `database/migrations/*` (termasuk `0001_01_01_000000_create_users_table.php` yang MENIMPA bawaan).

Arahkan docroot web domain ke `.../api/public` (symlink):
```bash
ln -sfn /home/sekim/web/mailapi.sekolahimpian.com/api/public /home/sekim/web/mailapi.sekolahimpian.com/public_html
```

## 4. config/services.php — tambahkan blok hestia
```php
'hestia' => [
    'url' => env('HESTIA_API_URL', 'https://127.0.0.1:8083/api/'),
    'access_key' => env('HESTIA_ACCESS_KEY'),
    'secret_key' => env('HESTIA_SECRET_KEY'),
    'user' => env('HESTIA_MAIL_USER', 'sekim'),
    'domain' => env('HESTIA_MAIL_DOMAIN', 'sekolahimpian.com'),
    'timeout' => 15,
],
```

## 5. .env (api)
```
APP_NAME="Sekolah Impian Mail"
APP_URL=https://mailapi.sekolahimpian.com
APP_KEY=            # php artisan key:generate

DB_CONNECTION=mysql
DB_DATABASE=sekim_mail
DB_USERNAME=sekim_mailuser
DB_PASSWORD=PASSWORD_KUAT

# HestiaCP API — buat access key untuk user sekim, scope mail-accounts
HESTIA_API_URL=https://127.0.0.1:8083/api/
HESTIA_ACCESS_KEY=xxxx
HESTIA_SECRET_KEY=xxxx
HESTIA_MAIL_USER=sekim
HESTIA_MAIL_DOMAIN=sekolahimpian.com

# Mail untuk email reset (pakai Exim lokal atau Brevo)
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=587
MAIL_FROM_ADDRESS="noreply@sekolahimpian.com"
MAIL_FROM_NAME="Sekolah Impian Mail"
```
Lalu:
```bash
sudo -u sekim php artisan key:generate
sudo -u sekim php artisan migrate --force
sudo -u sekim php artisan config:cache
systemctl reload php8.3-fpm
```

## 6. HestiaCP: access key untuk sekim (scope mail-accounts)
Buat access key milik user sekim yang boleh menjalankan perintah `v-*-mail-account`, `v-list-mail-accounts`. Isi ke `HESTIA_ACCESS_KEY/SECRET`. (API_ALLOWED_IP=127.0.0.1, API_SYSTEM=2.)

## 7. Mail domain sekolahimpian.com di bawah sekim
Domain mail-nya harus terdaftar di user sekim agar `v-add-mail-account sekim sekolahimpian.com ...` jalan:
```bash
v-list-mail-domains sekim | grep -i sekolah || v-add-mail-domain sekim sekolahimpian.com
```
> Catatan: folder `/etc/exim4/domains/sekolahimpian.com` mungkin sudah ada dari eksperimen alias. Kalau `v-add-mail-domain` menolak/aneh, cek pemilik lama & bersihkan dulu. DKIM/SPF/DMARC + relay Brevo untuk sekolahimpian.com sudah ada — biarkan.

## 8. Set master password gate (beta)
```bash
cd /home/sekim/web/mailapi.sekolahimpian.com/api
sudo -u sekim php artisan tinker --execute="\App\Models\MailSetting::current()->update(['master_password'=>bcrypt('MASTER_RAHASIA')]);"
```
Nonaktifkan gate: `->update(['gate_enabled'=>false])`.

## 9. Webmail (SvelteKit) — mail.sekolahimpian.com
- `.env` (dari `sekolahimpian-mail/web/.env.example`): `PORTALSI_API_URL=https://mailapi.sekolahimpian.com/api`, `MAIL_DOMAIN=sekolahimpian.com`, `ORIGIN=https://mail.sekolahimpian.com`, `PORT=3300`.
- Web domain + service:
```bash
v-add-web-domain sekim mail.sekolahimpian.com
v-add-web-domain-ssl sekim mail.sekolahimpian.com
cd /home/sekim/web/mail.sekolahimpian.com   # taruh folder web di sini (mis. subfolder 'app')
sudo -u sekim npm install
sudo -u sekim npm run build
```
- systemd service `sekolahimpian-mail` (adapter-node, PORT=3300) + nginx proxy ke 127.0.0.1:3300 (mirip service portalsi-mail). DNS A `mail.sekolahimpian.com` → IP.

## 10. Uji
- Buka `https://mail.sekolahimpian.com` → daftar akun baru → masukkan master password → buat mailbox `nama@sekolahimpian.com` → kirim/terima.
- Karena akun TERPISAH, login di sini beda dari Portal SI.

### Belum termasuk (menyusul bila perlu)
- Halaman reset kata sandi (link dari email) — endpoint `/forgot-password` sudah kirim email, tapi halaman reset di webmail belum ada.
- Panel admin (enable/disable gate + ganti master password) — sementara lewat tinker (langkah 8).
