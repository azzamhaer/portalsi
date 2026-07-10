# Portalsi Marketplace

Marketplace multivendor full-stack: **Laravel 11 (API)** + **SvelteKit 2 (Svelte 5 runes)** + **MySQL** + **Tripay Payment Gateway** + **Admin Center** + **Chat realtime polling**.

Premium minimal design (Apple/Linear-style), responsif penuh mobile→desktop, palette + branding bisa diatur dari admin.

---

## ⚙️ Prasyarat

- PHP 8.2+ & Composer
- MySQL 5.7+ atau 8.x
- Node.js 18+ & npm

---

## 🚀 Setup (3 langkah)

### 1. Backend Laravel

```bash
cd backend
composer install
cp .env.example .env
# Edit .env → set DB_DATABASE, DB_USERNAME, DB_PASSWORD
php artisan key:generate
# Buat database manual: CREATE DATABASE portalsi;
php artisan migrate --seed
php artisan serve --host=0.0.0.0 --port=8000   # http://localhost:8000, LAN: http://IP-LAPTOP:8000
```

### 2. Frontend SvelteKit

```bash
cd frontend
npm install
npm run dev   # http://localhost:5173, LAN: http://IP-LAPTOP:5173
```

### 3. Buka **http://localhost:5173**

Untuk test dari HP/device lain di Wi-Fi yang sama, buka `http://IP-LAPTOP:5173`.
Jika `PUBLIC_API_URL` mengarah ke `localhost:8000`, frontend akan otomatis memakai host LAN yang sama untuk API saat dibuka dari LAN.
Saat dibuka dari domain publik, frontend tidak akan memakai `localhost` dan akan kembali ke API production.

---

## 🔑 Akun Demo (otomatis dibuat saat seed)

| Role   | Email                | Password   | Kemampuan                                                    |
|--------|----------------------|------------|--------------------------------------------------------------|
| Admin  | admin@portalsi.id    | admin123   | Akses penuh /admin: branding, palette, KTP verif, semua CRUD |
| Buyer  | demo@portalsi.id     | demo123    | Belanja, wishlist, chat, return                              |
| Seller | seller@portalsi.id   | seller123  | Sudah punya toko Fashion Hub + 12 produk untuk testing CRUD  |

---

## ✨ Fitur Lengkap

### 🛡️ Admin Center (`/admin`)
- **Dashboard ringkasan**: total user, vendor, pesanan hari ini, revenue, vendor pending verifikasi, return pending
- **CRUD Users**: ubah role (BUYER/SELLER/ADMIN), hapus
- **Verifikasi Vendor + KTP**: lihat foto KTP, approve/reject dengan catatan
- **Manage Pesanan**: ubah status manual (PENDING_PAYMENT/PROCESSING/SHIPPED/DONE/dll)
- **Permintaan Return**: setujui / tolak / refund
- **Opsi Kurir**: tambah, edit, hapus opsi pengiriman
- **Branding**: ubah nama aplikasi, logo (upload), tagline
- **Color Palette**: pilih dari 6 preset (Mono, Indigo, Forest, Sunset, Rose, Midnight) atau custom HEX
- **Tripay Settings**: ubah API key/private key/merchant code/mode dari UI (override .env)

### 🎨 Branding Dinamis
- Logo & nama app yang admin upload otomatis muncul di **navbar, footer, favicon, browser title**
- Color palette yang dipilih admin diterapkan ke semua tombol & accent via CSS variable
- Default fallback (logo "P" + warna mono black) kalau admin belum upload

### 🏷️ Tags (Pengganti Kategori)
- Vendor saat tambah produk wajib isi minimal 1 **tag** (otomatis lowercase, slug-friendly)
- Homepage menampilkan tag populer (top 20 by product count)
- Browse `/products?tag=elektronik` — filter cepat
- Halaman produk listing menampilkan chip tag yang bisa di-klik

### 💬 Chat Toko
- Tombol **"Tanyakan barang ini"** di setiap halaman produk → otomatis buka thread chat dengan seller, attach produk
- Halaman `/chats` — list thread (pembeli & seller di satu inbox)
- Halaman `/chats/[id]` — chat realtime (polling 5 detik), bubble UI, attached product card
- Auto mark-as-read untuk pesan ke user

### 🆔 KTP Verifikasi Vendor
- Saat daftar seller, wajib upload foto KTP (base64)
- Vendor masuk status **PENDING**, tidak bisa create produk
- Admin lihat KTP di `/admin/vendors`, klik **Approve** atau **Reject + alasan**
- Setelah APPROVED, vendor langsung bisa jualan

### 📍 Alamat dengan Maps
- Komponen **MapPicker** pakai OpenStreetMap (free, no API key)
- Tombol **"Lokasi Saya"** (geolocation API) atau **set manual** (paste koordinat / Google Maps URL)
- Otomatis tampilkan link "Buka di Google Maps" untuk vendor & buyer

### ❤️ Wishlist Heart Toggle
- Icon hati di tiap product card
- **Merah filled** kalau sudah di wishlist, **outline abu-abu** kalau belum
- Klik untuk toggle (sync ke server + cache localStorage)

### 📄 Pagination
- Komponen Pagination smart: angka halaman + tombol prev/next + ellipsis untuk halaman jauh
- Aktif di `/products`, `/category/[slug]` dan halaman list lainnya

### 📱 Responsive Mobile
- Mobile-first design dengan breakpoints sm (640) / md (768) / lg (1024) / xl (1280)
- Header sticky dengan hamburger menu untuk mobile
- Cart layout adaptive (qty controls pindah ke bawah info)
- Tabel admin scrollable horizontal di mobile
- Form checkout responsive grid

### 💳 Tripay Payment Gateway
- 16 metode (BRIVA, BCAVA, MANDIRIVA, BNIVA, PERMATAVA, BSIVA, CIMBVA, OVO, DANA, ShopeePay, LinkAja, QRIS, Alfamart, Indomaret, Alfamidi, CC)
- Real API + signature HMAC-SHA256
- Webhook callback dengan signature verification
- Auto-poll status pembayaran 8 detik + tombol cek manual
- Mode mock fallback kalau API key kosong

### 🔁 Return / Pengembalian
- Buyer ajukan return dari halaman order (status DONE/SHIPPED)
- Admin review di `/admin/returns` → APPROVED / REJECTED / REFUNDED

---

## 🔁 Switch ke Production Tripay (3 cara)

### Cara 1 (Recommended): via Admin Dashboard
1. Login admin → `/admin/settings`
2. Section "Tripay Payment Gateway" → set Mode = `production`, isi credentials production
3. Klik Simpan. Selesai — TripayService otomatis pakai settings DB.

### Cara 2: via .env
Edit `backend/.env`:
```env
TRIPAY_MODE=production
TRIPAY_API_KEY=PROD-XXXXX
TRIPAY_PRIVATE_KEY=XXXXX
TRIPAY_MERCHANT_CODE=Txxxxx
```

### Cara 3: Webhook
Set callback URL di dashboard Tripay → `https://your-domain.com/api/tripay/callback`

---

## 📂 Struktur Project

```
portalsi-marketplace/
├── backend/                          # Laravel 11
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/Api/
│   │   │   │   ├── AdminController.php       (CRUD users, vendors, orders, returns, shipping)
│   │   │   │   ├── SettingsController.php    (branding & tripay env)
│   │   │   │   ├── ChatController.php
│   │   │   │   ├── TagController.php
│   │   │   │   ├── AddressController.php
│   │   │   │   ├── AuthController.php
│   │   │   │   ├── CatalogController.php
│   │   │   │   ├── OrderController.php
│   │   │   │   ├── SellerController.php
│   │   │   │   ├── WishlistController.php
│   │   │   │   └── TripayCallbackController.php
│   │   │   └── Middleware/AdminOnly.php
│   │   ├── Models/                   # 14 models
│   │   └── Services/TripayService.php
│   ├── database/migrations/          # 14 tabel
│   └── database/seeders/             # 12 vendor + 68 produk + tags
└── frontend/                         # SvelteKit + Svelte 5 runes
    ├── src/
    │   ├── lib/
    │   │   ├── api.ts                # Bearer-token client
    │   │   ├── stores.svelte.ts      # auth, cart, wishlist, settings, toast (runes)
    │   │   ├── utils.ts
    │   │   └── components/
    │   │       ├── Icon.svelte       # @iconify/svelte wrapper
    │   │       ├── Header, Footer, Hero, ProductCard, ProductGrid, Toaster
    │   │       ├── Pagination.svelte
    │   │       ├── MapPicker.svelte  # OpenStreetMap embed
    │   │       ├── ProductForm.svelte (dengan tag input)
    │   │       ├── SellerSidebar.svelte
    │   │       └── AdminSidebar.svelte
    │   └── routes/
    │       ├── +layout.svelte        # load settings + auth + wishlist
    │       ├── +page.svelte          # landing dengan tags populer
    │       ├── products/, product/[id]/, search/, category/[slug]/
    │       ├── cart/, checkout/, orders/, orders/[id]/
    │       ├── chats/, chats/[id]/
    │       ├── login/, register/, profile/, wishlist/
    │       ├── vendors/, vendors/[id]/
    │       ├── seller/dashboard|products|products/new|products/[id]/edit|orders|register|profile
    │       ├── admin/+layout, +page (dashboard), users, vendors, orders, returns, shipping, settings
    │       └── about/, help/, payment-info/
    └── static/favicon.svg
```

---

## 🛠️ Stack Teknis

| Layer       | Teknologi                                                |
|-------------|----------------------------------------------------------|
| Frontend    | SvelteKit 2 + Svelte 5 (runes), TypeScript, Tailwind CSS |
| Backend     | Laravel 11 (PHP 8.2+), Sanctum, Eloquent                 |
| Database    | MySQL 8                                                  |
| Icons       | @iconify/svelte (lucide icon set via `Icon` wrapper)     |
| Maps        | OpenStreetMap (free, no API key) + Google Maps deep link |
| Chat        | Polling-based (5s) — bisa upgrade ke Reverb/Pusher       |
| State       | Svelte 5 runes + localStorage persistence                |
| Payment     | Tripay Payment Gateway (real + mock fallback)            |

---

## 📝 Changelog Versi Terbaru

- ✅ Admin Center lengkap dengan CRUD semua entitas
- ✅ Branding dinamis (logo, nama, palette) dari admin
- ✅ KTP verifikasi flow (PENDING → APPROVED/REJECTED)
- ✅ Tag system menggantikan kategori tetap di homepage
- ✅ Chat antar buyer-vendor + "Tanyakan barang ini"
- ✅ Address dengan map picker (OpenStreetMap)
- ✅ Wishlist heart icon merah filled saat aktif
- ✅ Pagination komponen reusable
- ✅ Return / pengembalian barang
- ✅ Mobile responsive audit semua halaman
- ✅ Migrasi semua icon dari `lucide-svelte` ke `@iconify/svelte` (Icon wrapper)

---

## 📝 Lisensi

MIT — bebas dipakai untuk komersial.
