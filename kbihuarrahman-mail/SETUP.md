# KBIHU Ar-Rahman Mail — Panduan Deploy (full Cloudflare)

Webmail mandiri untuk domain **kbihuarrahman.com**, berjalan sepenuhnya di Cloudflare:

- **Web app** (`web/`) — SvelteKit + `adapter-cloudflare`. UI diambil dari mail.portalsi.com, tapi
  backend-nya ditulis ulang untuk Workers. Melayani UI + baca/tulis pesan + kirim via Brevo.
- **Inbound worker** (`inbound/`) — dipicu Cloudflare **Email Routing** saat ada email masuk;
  mem-parse email lalu menyimpannya.
- **D1** (`kbihu_mail`) — database (akun, pesan, kontak, sesi).
- **R2** (`kbihu-mailstore`) — objek email mentah (.eml) + lampiran.
- **Brevo** — mengirim email keluar lewat HTTP API (Workers tidak bisa SMTP mentah).

```
             ┌─────────────── Cloudflare ───────────────┐
  Pengirim → │ Email Routing → [inbound worker] → D1+R2  │
             │                                            │
  Browser  → │ mail.kbihuarrahman.com → [web worker] ─┐  │
             │        ▲  baca/tulis   D1 + R2 ◄────────┘  │
             └────────┼───────────────────────────────────┘
                      └── kirim keluar → Brevo API → penerima
```

> ⚠️ **Catatan verifikasi.** Langkah lewat *dashboard* Cloudflare/Brevo di bawah ditulis sesuai
> kondisi yang saya tahu; tampilan menu bisa berubah. Kalau nama menu beda, cari kata kuncinya
> (mis. "Email Routing", "Custom Domain", "Authenticate domain"). Nilai DNS **DKIM/SPF ikuti
> yang ditampilkan Brevo**, jangan disalin mentah dari sini.

---

## 0. Prasyarat

- Domain **kbihuarrahman.com** sudah di Cloudflare (nameserver aktif). ✔️ (kamu sudah beli lewat CF)
- Akun **Brevo** (gratis cukup untuk mulai) — untuk kirim email.
- **Node.js 18+** dan **wrangler** (CLI Cloudflare) di komputermu.

```bash
npm install -g wrangler
wrangler login          # buka browser, izinkan akses akun Cloudflare-mu
```

---

## 1. Install dependensi & siapkan project

```bash
cd kbihuarrahman-mail/web
npm install

cd ../inbound
npm install
```

---

## 2. Buat D1 dan R2

```bash
cd kbihuarrahman-mail/web

# D1 (database)
wrangler d1 create kbihu_mail
#  → keluaran memberi "database_id". SALIN nilainya.

# R2 (penyimpanan)
wrangler r2 bucket create kbihu-mailstore
```

Tempel `database_id` yang tadi ke **DUA** file (harus sama persis):

- `web/wrangler.toml`  → baris `database_id = "..."`
- `inbound/wrangler.toml` → baris `database_id = "..."`

Lalu buat tabelnya (dari folder `web/`):

```bash
wrangler d1 execute kbihu_mail --remote --file=../schema.sql
```

---

## 3. Set rahasia (secrets) untuk web app

Dari folder `web/`:

```bash
# API key Brevo — ambil di Brevo → menu "SMTP & API" → tab "API Keys" → buat baru
wrangler secret put BREVO_API_KEY

# Kunci acak untuk tanda tangan cookie sesi (buat sekali, simpan)
#   di Linux/Mac:  openssl rand -hex 32
#   di Windows PowerShell:  -join ((1..64) | % { '{0:x}' -f (Get-Random -Max 16) })
wrangler secret put SESSION_SECRET
```

(Wrangler akan meminta nilainya dan menyimpannya terenkripsi di Cloudflare — tidak masuk ke git.)

---

## 4. Deploy web app + subdomain mail.kbihuarrahman.com

```bash
cd kbihuarrahman-mail/web
npm run deploy        # = npm run build && wrangler deploy
```

Setelah worker `kbihu-mail` ter-deploy, hubungkan subdomain:

1. Buka **Cloudflare Dashboard → Workers & Pages → `kbihu-mail`**.
2. Tab **Settings → Domains & Routes → Add → Custom Domain**.
3. Isi **`mail.kbihuarrahman.com`** → **Add Domain**.

Cloudflare otomatis membuat DNS + sertifikat SSL-nya. Dalam 1–2 menit,
`https://mail.kbihuarrahman.com` sudah hidup.

> Alternatif via CLI: tambahkan `routes` di `web/wrangler.toml`, tapi lewat dashboard paling gampang
> karena sertifikat & DNS diurus otomatis.

---

## 5. Deploy inbound worker

```bash
cd kbihuarrahman-mail/inbound
npm run deploy        # wrangler deploy → worker "kbihu-mail-inbound"
```

Worker ini belum menerima apa-apa sampai Email Routing diarahkan ke sana (langkah 6).

---

## 6. Aktifkan Email Routing (menerima email masuk)

1. Dashboard → pilih domain **kbihuarrahman.com** → menu **Email → Email Routing**.
2. Klik **Get started / Enable**. Cloudflare akan menambahkan **record MX + TXT** otomatis
   (setujui). Ini yang membuat email ke `@kbihuarrahman.com` masuk ke Cloudflare.
3. Buka tab **Routing rules → Catch-all address**.
4. Set **Action = Send to a Worker**, pilih worker **`kbihu-mail-inbound`**, lalu **Save**.
   - (Opsional) Kalau mau per-alamat, pakai "Custom addresses" dan arahkan tiap alamat ke worker
     yang sama. Catch-all lebih praktis: semua alamat yang mailbox-nya ada di app akan tersimpan,
     yang tidak ada otomatis ditolak oleh worker.

Setelah ini, email masuk untuk mailbox yang terdaftar akan muncul di webmail.

---

## 7. Autentikasi domain di Brevo (agar email keluar tidak masuk spam)

Di **Brevo → Senders, Domains & Dedicated IPs → Domains → Authenticate a domain**, masukkan
`kbihuarrahman.com`. Brevo akan menampilkan beberapa record DNS (biasanya **DKIM**, kode
verifikasi, dan **DMARC**). Tambahkan **semua** record itu di **Cloudflare → DNS → Records**
dengan **Proxy status = DNS only (awan abu-abu)**.

**Soal SPF (penting, agar tidak bentrok):** hanya boleh ada **satu** record SPF (`TXT` berisi
`v=spf1 ...`). Karena email keluar lewat **Brevo** (bukan lewat Cloudflare), pakai SPF Brevo:

```
Type: TXT   Name: @   Value: v=spf1 include:spf.brevo.com ~all
```

Jika Cloudflare Email Routing sudah membuat SPF sendiri, jangan buat dua — **gabungkan** jadi satu:

```
v=spf1 include:spf.brevo.com include:_spf.mx.cloudflare.net ~all
```

> Nilai persis DKIM/kode Brevo **ikuti yang muncul di layar Brevo**. Setelah semua record hijau,
> klik **Verify/Authenticate** di Brevo sampai statusnya ✅.

Terakhir, di Brevo tambahkan **sender** `admin@kbihuarrahman.com` (atau alamat lain yang kamu pakai)
supaya diizinkan sebagai pengirim.

---

## 8. Buat akun admin pertama & mailbox lain

1. Buka **https://mail.kbihuarrahman.com** — karena belum ada akun, kamu diarahkan ke halaman
   **/setup**. Isi nama, alamat (mis. `admin`), dan kata sandi (≥ 8 karakter) → **Buat admin & masuk**.
2. Sebagai admin, buka **https://mail.kbihuarrahman.com/register** untuk menambah mailbox lain
   (mis. `info`, `pendaftaran`, `keuangan`). Centang "jadikan admin" bila perlu.
   - Field form: `username` (bagian sebelum @), `full_name`, `password`, dan checkbox admin
     (`make_admin=1`).

> Reset kata sandi mailbox (kalau lupa) untuk sekarang dilakukan admin lewat CLI:
> ```bash
> # contoh: cari user id
> wrangler d1 execute kbihu_mail --remote --command "SELECT id, email FROM users;"
> ```
> (Hash kata sandi dibuat oleh aplikasi; cara paling aman ganti password adalah lewat halaman
> admin — bisa kita tambahkan tombol "reset password" di iterasi berikutnya.)

---

## 9. Uji

- **Kirim**: login, tulis email ke Gmail-mu → cek terkirim & masuk (cek folder spam pertama kali;
  akan membaik setelah DKIM/SPF hijau).
- **Terima**: dari Gmail, kirim ke `admin@kbihuarrahman.com` → dalam beberapa detik muncul di Kotak
  Masuk webmail.

---

## 10. Operasional & catatan

- **Biaya**: D1, R2, Workers, dan Email Routing punya tier gratis yang besar; untuk 1 lembaga kecil
  hampir pasti gratis. Brevo gratis ~300 email/hari.
- **Update kode**: cukup `npm run deploy` lagi di `web/` dan/atau `inbound/`.
- **Ubah skema D1**: tambahkan perintah SQL baru, jalankan `wrangler d1 execute ... --file`.
  (schema.sql aman dijalankan ulang karena `IF NOT EXISTS`.)
- **Batas ukuran**: lampiran sangat besar sebaiknya dibatasi (email inbound via Email Routing ada
  batas ukuran pesan Cloudflare; cek dokumen terkini bila perlu kirim file besar).
- **Backup**: `wrangler d1 export kbihu_mail --remote --output backup.sql` untuk cadangan berkala.

## Ringkasan perintah (cepat)

```bash
# sekali di awal
npm i -g wrangler && wrangler login
cd web && npm i && cd ../inbound && npm i && cd ../web
wrangler d1 create kbihu_mail          # salin database_id ke DUA wrangler.toml
wrangler r2 bucket create kbihu-mailstore
wrangler d1 execute kbihu_mail --remote --file=../schema.sql
wrangler secret put BREVO_API_KEY
wrangler secret put SESSION_SECRET
npm run deploy                          # web app
cd ../inbound && npm run deploy         # inbound worker
# lalu: dashboard → Custom Domain mail.kbihuarrahman.com,
#       Email Routing catch-all → kbihu-mail-inbound,
#       Brevo authenticate domain (DKIM/SPF), buat admin di /setup
```
