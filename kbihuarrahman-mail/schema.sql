-- ════════════════════════════════════════════════════════════════════
-- KBIHU Ar-Rahman Mail — skema D1 (Cloudflare)
-- Jalankan:  wrangler d1 execute kbihu_mail --remote --file=../schema.sql
-- (dari folder web/). Aman dijalankan ulang (IF NOT EXISTS).
-- ════════════════════════════════════════════════════════════════════

-- Akun mailbox (juga = akun login webmail)
CREATE TABLE IF NOT EXISTS users (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  email         TEXT NOT NULL UNIQUE,          -- alamat penuh, mis. admin@kbihuarrahman.com
  local_part    TEXT NOT NULL,                 -- bagian sebelum @ (untuk routing inbound), lowercase
  full_name     TEXT,
  password_hash TEXT NOT NULL,                 -- pbkdf2$<iter>$<salt_b64>$<hash_b64>
  is_admin      INTEGER NOT NULL DEFAULT 0,
  is_active     INTEGER NOT NULL DEFAULT 1,
  created_at    TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_users_local ON users(local_part);

-- Sesi login (cookie berisi id sesi yang ditandatangani HMAC)
CREATE TABLE IF NOT EXISTS sessions (
  id         TEXT PRIMARY KEY,                 -- token acak 32 byte (hex)
  user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  user_agent TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  expires_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_sessions_user ON sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_sessions_exp  ON sessions(expires_at);

-- Pesan (satu baris = satu email milik satu user)
CREATE TABLE IF NOT EXISTS messages (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,  -- dipakai sebagai "uid" di UI
  user_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  folder          TEXT NOT NULL DEFAULT 'inbox',      -- inbox|sent|drafts|archive|junk|trash
  seen            INTEGER NOT NULL DEFAULT 0,
  flagged         INTEGER NOT NULL DEFAULT 0,          -- berbintang
  answered        INTEGER NOT NULL DEFAULT 0,
  draft           INTEGER NOT NULL DEFAULT 0,
  subject         TEXT,
  from_name       TEXT,
  from_addr       TEXT,
  to_addrs        TEXT,
  cc_addrs        TEXT,
  date            TEXT,                                -- ISO 8601
  message_id      TEXT,
  in_reply_to     TEXT,
  ref_ids         TEXT,                                -- header References (dipisah spasi)
  subject_norm    TEXT,                                -- subjek dinormalisasi (untuk threading)
  snippet         TEXT,
  has_attachments INTEGER NOT NULL DEFAULT 0,
  html            TEXT,
  body_text       TEXT,
  raw_key         TEXT,                                -- key objek R2 berisi .eml mentah
  size            INTEGER NOT NULL DEFAULT 0,
  created_at      TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_msg_box   ON messages(user_id, folder, date);
CREATE INDEX IF NOT EXISTS idx_msg_star  ON messages(user_id, flagged);
CREATE INDEX IF NOT EXISTS idx_msg_subj  ON messages(user_id, subject_norm);

-- Lampiran (objek disimpan di R2; baris ini metadata)
CREATE TABLE IF NOT EXISTS attachments (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  message_id   INTEGER NOT NULL REFERENCES messages(id) ON DELETE CASCADE,
  idx          INTEGER NOT NULL DEFAULT 0,
  filename     TEXT,
  content_type TEXT,
  size         INTEGER NOT NULL DEFAULT 0,
  inline       INTEGER NOT NULL DEFAULT 0,
  cid          TEXT,
  r2_key       TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_att_msg ON attachments(message_id);

-- Kontak (auto-terkumpul dari interaksi)
CREATE TABLE IF NOT EXISTS contacts (
  id        INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id   INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  address   TEXT NOT NULL,
  name      TEXT,
  last_seen TEXT NOT NULL DEFAULT (datetime('now')),
  UNIQUE(user_id, address)
);

-- Pengaturan per-user (key/value; mis. bahasa UI)
CREATE TABLE IF NOT EXISTS settings (
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  key     TEXT NOT NULL,
  value   TEXT,
  PRIMARY KEY (user_id, key)
);
