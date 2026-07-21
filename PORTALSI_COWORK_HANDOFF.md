# Portal SI — Handoff / Context untuk Chat Cowork Baru

> Tempel isi file ini sebagai prompt awal di chat Cowork baru agar asisten langsung paham
> konteks proyek, aturan main, struktur, dan cara deploy. Perbarui bila ada yang berubah.

---

## 1. Ringkasan proyek

**Portal SI** = platform sosial pesantren Indonesia (mirip Instagram: feed, post foto/video,
reels, stories, komentar berulir, kolaborasi post, chat/DM, grup, marketplace, explore, profil).
Bahasa UI: **Indonesia**. Estetika: **ala Instagram** (bersih, media edge-to-edge, dsb).

Monorepo di komputer user: `D:\Projects\portalsi` (ini folder git yang di-mount ke Cowork).
Struktur utama:

```
D:\Projects\portalsi\
├── portalsi-app\
│   ├── web\   → Frontend SvelteKit (domain: app.portalsi.com)
│   └── api\   → Backend Laravel     (domain: api.portalsi.com)
└── (file lain / dokumen)
```

---

## 2. Tech stack

**Frontend (`portalsi-app/web`)**
- SvelteKit + `adapter-node` (SSR, dijalankan sebagai Node service di VPS).
- **Svelte 5 runes**: `$state`, `$derived`, `$effect`, `$props`, `bind:this`, `use:` actions,
  `{@const}`, `{@render}`, `<script module>`. (Bukan Svelte 4 — jangan pakai `export let`.)
- TypeScript. Validasi respons API pakai **Zod** (`src/lib/schemas/*`).
- Ikon: `@lucide/svelte`. Media/env: `PUBLIC_MEDIA_BASE_URL` (default `https://api.portalsi.com/storage`).

**Backend (`portalsi-app/api`)**
- Laravel 10, PHP 8.3, MariaDB.
- Auth: Sanctum (token). Middleware default Laravel AKTIF (penting: `ConvertEmptyStringsToNull`
  mengubah string kosong jadi `null` — lihat gotcha di §7).
- Storage media: Cloudflare R2 / S3-compatible (disk media).

---

## 3. ATURAN MAIN / CONSTRAINTS (WAJIB dipatuhi)

1. **Tidak bisa SSH ke VPS.** Asisten HANYA membuat/menyusun perintah, lalu USER yang
   menjalankannya di VPS. Jangan pernah berasumsi sudah menjalankan sesuatu di server.
2. **Rahasia dikelola user.** User menempel & merotasi sendiri kredensialnya (root/Hestia/DB dll).
   JANGAN menyimpan, memakai ulang, atau menampilkan kembali secret apa pun. Kalau butuh, minta
   user menempelkannya saat itu saja.
3. **Frontend TIDAK bisa di-build lokal** di sandbox (native binding `rolldown` beda arsitektur).
   Jadi jangan coba `npm run build` untuk verifikasi; andalkan pembacaan kode + deploy ke VPS.
4. **Mount bash bisa basi/stale untuk file besar.** File tools (Read/Write/Edit) adalah sumber
   kebenaran. Bash oke untuk grep/cek cepat, tapi untuk edit file besar gunakan Edit/Write.
5. **Jangan mengambil konten web yang diblokir lewat cara lain** (curl/wget/python) — dilarang.
6. **Edit tool + Svelte ber-indentasi TAB**: pencocokan multiline sering GAGAL karena whitespace
   awal dianggap spasi, bukan tab. Solusi: pakai **anchor satu baris yang unik**, atau substring
   sebagian baris (cocok apa pun indentasi tab-nya). Verifikasi tab persis dengan
   `sed -n 'X,Yp' file | cat -A` bila perlu. Hindari mencocokkan baris dengan em-dash `—`
   (byte-nya bikin match meleset).

---

## 4. Path mapping (PENTING)

| Konteks | Path |
|---|---|
| File tools (Read/Write/Edit) | `D:\Projects\portalsi\...` |
| Bash sandbox | `/sessions/<SESSION>/mnt/portalsi/...` |

`<SESSION>` berganti tiap sesi (mis. `wizardly-intelligent-hopper`). Cari nilainya di bagian
"Shell access" pada system prompt, atau: `ls /sessions/*/mnt/portalsi` di bash.

Contoh: file `D:\Projects\portalsi\portalsi-app\web\src\...` = bash
`/sessions/<SESSION>/mnt/portalsi/portalsi-app/web/src/...`.

---

## 5. DEPLOY (alur baku)

Perubahan diterapkan lewat git → push → user jalankan skrip deploy di VPS.

```bash
cd D:\Projects\portalsi
git add portalsi-app/web          # dan/atau portalsi-app/api sesuai yang diubah
git commit -m "pesan jelas dlm Bahasa Indonesia"
git push
```

Lalu USER menjalankan di VPS (via panel/terminal mereka):

```
portalsi-deploy
```

- `portalsi-deploy` = skrip deploy di VPS (pull terbaru, build/setup, restart service).
- **Deploy WEB dan/atau API** sesuai perubahan. Kalau ada perubahan di `api/`, ingatkan user
  bahwa deploy harus mencakup API (mis. migrasi bila ada — sebagian besar perubahan sejauh ini
  TIDAK butuh migrasi karena hanya query baca/logika).
- Setelah deploy: **hard refresh** browser (cache SvelteKit).
- VPS memakai **HestiaCP** (panel). Detail filesystem/port tidak diekspos ke asisten; minta user
  bila perlu.

**Kebiasaan yang berguna**: setelah menyelesaikan perubahan, selalu berikan blok perintah
`git add/commit/push` yang siap salin + ingatkan `portalsi-deploy` + hard refresh.

---

## 6. Peta file penting

### Frontend (`portalsi-app/web/src`)
- `lib/components/media/SmartVideo.svelte` — **pemutar video inti** (dipakai reels & detail post).
  Fitur: autoplay via IntersectionObserver ATAU prop `active` eksplisit, kontrol mute, kualitas
  (low/medium/original), seek tipis (reels/minimal), fullscreen, loader, sinkron musik opsional.
- `lib/components/post/PostDetailView.svelte` — **detail post ala IG** (~1900 baris). Media kiri
  (edge-to-edge, `#0a0b0c`), komentar/caption/musik/lokasi kanan. Edit modal (caption + pemilih
  lokasi + kelola kolaborator). Mobile: buka `CommentsSheet` fullscreen.
- `lib/components/post/PostModal.svelte` — pembungkus modal untuk detail post (shallow routing).
- `lib/components/reels/CommentsSheet.svelte` — komentar berulir fullscreen (reply/like/reply-to-
  reply/edit/hapus/GIF). Dipakai reels DAN detail-post mobile.
- `routes/(app)/reels/+page.svelte` — feed reels (scroll-snap vertikal, virtualisasi ±1,
  prop `active`, preload tetangga ringan).
- `routes/(app)/posts/[postId]/+page.svelte` + `+page.server.ts` — halaman detail penuh + actions
  `?/update` & `?/delete`. (Deep-link/refresh; normalnya post dibuka sbg modal via AppShell.)
- `lib/components/layout/AppShell.svelte` — kerangka app: sidebar, mobile-header, bottom-nav,
  tombol back global (`showBack`/`hasOwnBack`), buka post sebagai modal via `pushState`
  (shallow routing), skeleton saat navigasi.
- `lib/components/story/StoryAvatarLink.svelte` — avatar + lingkar story (klik = buka story/profil,
  bisa preview foto). Prop: `userId, username, name, avatarUrl, hasStory, seen, size`.
- `lib/api/mappers.ts` — `mapCompactUser` (map `has_story`→`hasStory`, dll), `mapPost`.
- `lib/schemas/post.ts`, `lib/schemas/comment.ts` — Zod. `compactUserSchema` sudah punya
  `has_story`/`story_viewed` (opsional).
- `lib/ui/confirm.ts` — `confirmAction({ title, description, confirmLabel?, cancelLabel?, tone? })`
  → Promise<boolean>. Dialog global sudah ter-mount; pakai ini untuk konfirmasi cakep.
- `lib/components/composer/ComposerPrototype.svelte` — komposer post (referensi logika pemilih
  lokasi).
- `routes/api/external/locations/+server.ts` — proxy pencarian lokasi. Return
  `{ locations: [{ id, label }] }`, butuh query ≥ 3 char + auth.

### Backend (`portalsi-app/api`)
- `app/Http/Controllers/PostController.php` — CRUD post; helper `attachStoryInfo($user,$authUser)`
  (set `has_story`/`story_viewed`); `update()` (lihat gotcha caption di §7).
- `app/Http/Controllers/CommentController.php` — komentar; `getCommentsByPost()` kini melampirkan
  info story ke user komentar & balasan (batch query ke `stories`/`story_views`).
- `routes/api.php` — rute API. Komentar: `GET /posts/{id}/comments`, `POST /posts/{id}/comments`,
  `PUT /comments/{id}`, `DELETE /comments/{id}`, like/unlike. Post update: `POST /posts/{id}/update`.

---

## 7. Gotchas / pelajaran penting

- **Caption tak bisa dikosongkan (API)**: `ConvertEmptyStringsToNull` mengubah caption kosong jadi
  `null`; pola lama `$post->caption = $request->caption ?? $post->caption` selalu mempertahankan
  nilai lama. Solusi: pakai `if ($request->has('caption')) { $post->caption = $request->input('caption'); }`
  (juga untuk `location`). Sudah diterapkan.
- **Lingkar story di komentar**: endpoint komentar dulu tak mengirim `has_story`; sudah ditambah
  enrichment batch di `getCommentsByPost`. Butuh field `has_story`/`story_viewed` agar
  `StoryAvatarLink`/`Avatar` menampilkan ring.
- **CSS komentar rusak** pernah terjadi karena komentar dibungkus `.inline-thread` tapi selector
  masih `.comment-list > article`. Selalu cek selector anak-langsung saat membungkus ulang markup.
- **Spasi username↔badge** harus konsisten antara caption & komentar (pakai `.c-user` yang sama;
  jangan tambah `margin-right` khusus di caption).
- **Svelte 5 reaktivitas**: variabel `let` biasa (termasuk target `bind:this`) TIDAK reaktif; hanya
  `$state/$derived/$props`/store yang dilacak `$effect`. Ini dipakai sengaja: mis. `$effect` cleanup
  yang hanya baca `video` (plain let) → jalan sekali, cleanup hanya saat unmount.

---

## 8. Detail penting SmartVideo & Reels (sering disentuh)

**Autoplay mobile (iOS) yang andal:**
- Video autoplay **selalu mulai muted** (kebijakan browser memblokir autoplay bersuara).
  `defaultMuted=true` di-set agar atribut `muted` benar untuk iOS. `playsinline` wajib.
- **Tap pertama** pada video menyalakan suara (dalam gesture user), tanpa mem-pause.
- Ikon play overlay = **segitiga saja** (tanpa lingkaran), `<Play size={56} strokeWidth={0}>`.

**Reels — kendali putar DETERMINISTIK (jangan andalkan observer per-video):**
- `reels/+page.svelte` merender `{#if Math.abs(i - activeIndex) <= 1}` (aktif + 1 atas/bawah,
  agar tetangga preload → scroll mulus), dan meneruskan **`active={i === activeIndex}`** ke SmartVideo.
- SmartVideo: bila prop `active` **terdefinisi**, pakai `$effect` yang membaca `active` untuk
  play/pause (deterministik). Bila `active === undefined` (feed/detail), pakai IntersectionObserver.
- **`ensurePlaying()`**: loop kecil yang mencoba `play()` tiap 350ms sampai benar-benar jalan;
  berhenti otomatis saat playing / keluar viewport / `userPaused` / `scrubbing`. Ini mengatasi
  iOS yang kadang menolak/melewatkan `play()`.
- **`userPaused`**: bedakan "harus main tapi belum jalan" vs "user sengaja pause" agar loop tak
  melawan user.

**Anti-saturasi koneksi (gambar halaman lain tak load setelah reels):**
- Saat unmount, SmartVideo **membatalkan unduhan**: `pause(); removeAttribute('src'); load();`
  (efek tanpa dependensi reaktif → hanya jalan saat teardown; aman utk pemutaran aktif).
- **Preload cerdas**: hanya video **aktif** `preload="auto"`; tetangga `preload="metadata"`
  → satu unduhan berat pada satu waktu, aktif dapat bandwidth penuh (loading lebih cepat).

**Loader/buffering:**
- Loader = **lingkaran berputar di tengah** (`.video-loading`, disc kecil semi-transparan,
  bukan overlay gelap penuh). Muncul saat `loadstart/waiting/stalled`; hilang saat
  `playing/canplay/loadeddata/loadedmetadata` atau frame maju (`timeupdate`).

**Persistensi suara reels:**
- Variabel module-level `reelsSoundOn` (di `<script module>`): sekali user unmute, reel berikutnya
  mencoba bersuara (fallback muted bila diblokir → pemutaran tak pernah rusak).

**Musik menyatu dengan video (detail post video):**
- SmartVideo punya prop `musicSrc/musicStart/musicClip` + elemen `<audio>` tersembunyi yang ikut
  play/pause/loop mengikuti video (tanpa tombol musik terpisah). Untuk post **foto** bermusik,
  pakai komponen `ViewportMusic`. Untuk post **video** bermusik, tampilkan baris judul musik
  statis (tanpa kontrol) karena sudah menyatu dgn play/pause video. (Reels TIDAK memakai `musicSrc`
  — audionya menyatu di file video; video & audio berjalan sendiri-sendiri.)

---

## 9. Riwayat pekerjaan (fitur yang sudah dikerjakan di sesi sebelumnya)

- Fitur **Reels** (feed video vertikal, scroll-snap, virtualisasi, komentar berulir).
- **Detail post redesign ala IG** (media kiri edge-to-edge, aksi & komentar kanan; versi mobile
  media atas → aksi → caption → "Lihat semua N komentar" buka `CommentsSheet` fullscreen dgn input
  sticky).
- **Komentar berulir** penuh di reels & detail (reply, like, reply-to-reply, edit, hapus, GIF),
  username (bukan nama lengkap), klik ke profil, lingkar story, spasi username↔badge rapi.
- **Sinkron video+musik** di detail (pause video = pause musik).
- **Pemilih lokasi** saat edit post (dropdown pencarian seperti saat buat post).
- **Caption bisa dikosongkan** saat edit (fix middleware Laravel).
- **Popup konfirmasi hapus kolaborator** (pakai `confirmAction`, tone danger).
- **Tombol back** halaman detail dirapikan (tidak menutupi media; mobile di atas-kiri).
- **Border-radius mobile** detail: bagian komentar tanpa lengkung atas → menyatu dgn media.
- **SmartVideo** dirombak untuk keandalan iOS (lihat §8): autoplay muted, tap-to-unmute, ikon
  segitiga, kendali `active` deterministik, `ensurePlaying`, batal unduh saat unmount, preload
  cerdas, loader melingkar, `reelsSoundOn`.
- **Chat share note**: catatan opsional saat share post digabung ke dalam satu bubble bersama
  preview post (`ConversationPrototype.svelte`).

---

## 10. Cara kerja yang disukai user

- Jawab **ringkas & langsung** (user set preferensi concise). Hindari basa-basi & verbositas.
- Bahasa: campur Indonesia (user menulis Indonesia). Balas dalam Bahasa Indonesia.
- Setelah kerja: sebutkan hasil singkat + **blok perintah deploy siap-salin** + ingatkan
  `portalsi-deploy` + hard refresh. Sebutkan bila perlu deploy API juga / perlu migrasi.
- Saat mengubah file besar Svelte: pakai anchor 1 baris untuk Edit; verifikasi keseimbangan
  `{#if}`/`{/if}` dan kurung `{`/`}` setelah edit (mis. via bash grep count).
- Jangan mengekspos path internal `/sessions/...` ke user; sebut "folder yang kamu pilih".
