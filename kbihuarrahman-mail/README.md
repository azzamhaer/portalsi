# KBIHU Ar-Rahman Mail

Webmail mandiri untuk **kbihuarrahman.com**, berjalan penuh di Cloudflare (Workers + D1 + R2)
dengan pengiriman via Brevo. UI diambil dari mail.portalsi.com; backend ditulis ulang untuk
Cloudflare (tanpa IMAP/SMTP server).

```
kbihuarrahman-mail/
├── web/         SvelteKit (adapter-cloudflare) — webmail + API + kirim (Brevo)
├── inbound/     Email Worker — terima email (Cloudflare Email Routing) → D1 + R2
├── schema.sql   Skema D1 (jalankan sekali)
└── SETUP.md     ← panduan deploy langkah-demi-langkah (baca ini)
```

Mulai dari **[SETUP.md](./SETUP.md)**.

## Arsitektur singkat

- **Auth** — akun disimpan di D1; kata sandi di-hash PBKDF2 (WebCrypto); sesi = cookie ber-HMAC.
- **Baca email** — data dari D1 (metadata) + R2 (raw/lampiran), meniru kontrak UI lama.
- **Kirim** — `web/src/lib/server/brevo.ts` → Brevo Transactional API (Workers tak bisa SMTP).
- **Terima** — `inbound/src/index.ts` → parse MIME (postal-mime) → simpan ke D1 + R2.
- **Mailbox** — dibuat admin lewat `/setup` (admin pertama) lalu `/register` (mailbox berikutnya).

## Perbedaan penting dari mail.portalsi.com

| Aspek        | portalsi-mail (lama)         | kbihuarrahman-mail (ini)          |
|--------------|------------------------------|-----------------------------------|
| Runtime      | Node (adapter-node) di VPS   | Cloudflare Workers                |
| Auth         | SSO Portal SI API            | Akun sendiri di D1                |
| Simpan email | IMAP server (Dovecot)        | D1 + R2                           |
| Kirim        | SMTP (nodemailer)            | Brevo HTTP API                    |
| Terima       | IMAP                         | Cloudflare Email Routing + Worker |
