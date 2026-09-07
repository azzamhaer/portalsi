// ════════════════════════════════════════════════════════════════════
// Auth untuk KBIHU Ar-Rahman Mail — berjalan di Cloudflare Workers.
// Password hash: PBKDF2-SHA256 (WebCrypto). Sesi: baris D1 + cookie
// bertanda-tangan HMAC-SHA256. Tidak ada dependensi Node.
// ════════════════════════════════════════════════════════════════════

export type Env = App.Platform['env'];

export const SESSION_COOKIE = 'kbihu_sess';
const SESSION_TTL_DAYS = 30;
const PBKDF2_ITER = 100_000;

export interface SessionUser {
	id: number;
	email: string;
	local_part: string;
	full_name: string | null;
	is_admin: boolean;
}

export class AuthError extends Error {
	constructor(
		message: string,
		public status = 400
	) {
		super(message);
	}
}

// ─────────────────────────── util encoding ───────────────────────────
const enc = new TextEncoder();

function bufToB64(buf: ArrayBuffer): string {
	const bytes = new Uint8Array(buf);
	let bin = '';
	for (const b of bytes) bin += String.fromCharCode(b);
	return btoa(bin);
}
function b64ToBuf(b64: string): Uint8Array {
	const bin = atob(b64);
	const out = new Uint8Array(bin.length);
	for (let i = 0; i < bin.length; i++) out[i] = bin.charCodeAt(i);
	return out;
}
function toHex(buf: ArrayBuffer): string {
	return [...new Uint8Array(buf)].map((b) => b.toString(16).padStart(2, '0')).join('');
}
function randHex(bytes = 32): string {
	const a = new Uint8Array(bytes);
	crypto.getRandomValues(a);
	return [...a].map((b) => b.toString(16).padStart(2, '0')).join('');
}

// timing-safe compare
function safeEqual(a: string, b: string): boolean {
	if (a.length !== b.length) return false;
	let diff = 0;
	for (let i = 0; i < a.length; i++) diff |= a.charCodeAt(i) ^ b.charCodeAt(i);
	return diff === 0;
}

// ─────────────────────────── password ───────────────────────────
export async function hashPassword(password: string): Promise<string> {
	const salt = crypto.getRandomValues(new Uint8Array(16));
	const key = await crypto.subtle.importKey('raw', enc.encode(password), 'PBKDF2', false, [
		'deriveBits'
	]);
	const bits = await crypto.subtle.deriveBits(
		{ name: 'PBKDF2', salt: salt as BufferSource, iterations: PBKDF2_ITER, hash: 'SHA-256' },
		key,
		256
	);
	return `pbkdf2$${PBKDF2_ITER}$${bufToB64(salt.buffer as ArrayBuffer)}$${bufToB64(bits)}`;
}

export async function verifyPassword(password: string, stored: string): Promise<boolean> {
	const parts = stored.split('$');
	if (parts.length !== 4 || parts[0] !== 'pbkdf2') return false;
	const iter = Number(parts[1]);
	const salt = b64ToBuf(parts[2]);
	const key = await crypto.subtle.importKey('raw', enc.encode(password), 'PBKDF2', false, [
		'deriveBits'
	]);
	const bits = await crypto.subtle.deriveBits(
		{ name: 'PBKDF2', salt: salt as BufferSource, iterations: iter, hash: 'SHA-256' },
		key,
		256
	);
	return safeEqual(bufToB64(bits), parts[3]);
}

// ─────────────────────────── cookie signing ───────────────────────────
async function hmac(secret: string, data: string): Promise<string> {
	const key = await crypto.subtle.importKey(
		'raw',
		enc.encode(secret),
		{ name: 'HMAC', hash: 'SHA-256' },
		false,
		['sign']
	);
	const sig = await crypto.subtle.sign('HMAC', key, enc.encode(data));
	return toHex(sig);
}

export async function signSession(secret: string, sessionId: string): Promise<string> {
	return `${sessionId}.${await hmac(secret, sessionId)}`;
}

async function unsignSession(secret: string, value: string): Promise<string | null> {
	const dot = value.lastIndexOf('.');
	if (dot < 0) return null;
	const id = value.slice(0, dot);
	const sig = value.slice(dot + 1);
	const expected = await hmac(secret, id);
	return safeEqual(sig, expected) ? id : null;
}

// ─────────────────────────── sessions (D1) ───────────────────────────
export async function createSession(
	env: Env,
	userId: number,
	userAgent: string | null
): Promise<string> {
	const id = randHex(32);
	const expires = new Date(Date.now() + SESSION_TTL_DAYS * 864e5).toISOString();
	await env.DB.prepare(
		'INSERT INTO sessions (id, user_id, user_agent, expires_at) VALUES (?, ?, ?, ?)'
	)
		.bind(id, userId, userAgent || null, expires)
		.run();
	return signSession(env.SESSION_SECRET, id);
}

export async function destroySession(env: Env, signedOrId: string): Promise<void> {
	const id = (await unsignSession(env.SESSION_SECRET, signedOrId)) || signedOrId;
	await env.DB.prepare('DELETE FROM sessions WHERE id = ?').bind(id).run();
}

export async function getSessionUser(
	env: Env,
	cookieValue: string | undefined
): Promise<{ user: SessionUser; sessionId: string } | null> {
	if (!cookieValue) return null;
	const id = await unsignSession(env.SESSION_SECRET, cookieValue);
	if (!id) return null;
	const row = await env.DB.prepare(
		`SELECT u.id, u.email, u.local_part, u.full_name, u.is_admin, s.expires_at
		 FROM sessions s JOIN users u ON u.id = s.user_id
		 WHERE s.id = ? AND u.is_active = 1`
	)
		.bind(id)
		.first<{
			id: number;
			email: string;
			local_part: string;
			full_name: string | null;
			is_admin: number;
			expires_at: string;
		}>();
	if (!row) return null;
	if (new Date(row.expires_at).getTime() < Date.now()) {
		await env.DB.prepare('DELETE FROM sessions WHERE id = ?').bind(id).run();
		return null;
	}
	return {
		sessionId: id,
		user: {
			id: row.id,
			email: row.email,
			local_part: row.local_part,
			full_name: row.full_name,
			is_admin: !!row.is_admin
		}
	};
}

// ─────────────────────────── users ───────────────────────────
export async function countUsers(env: Env): Promise<number> {
	const r = await env.DB.prepare('SELECT COUNT(*) AS n FROM users').first<{ n: number }>();
	return r?.n ?? 0;
}

export async function findUserByEmail(env: Env, email: string) {
	return env.DB.prepare('SELECT * FROM users WHERE email = ?')
		.bind(email.toLowerCase())
		.first<{
			id: number;
			email: string;
			local_part: string;
			full_name: string | null;
			password_hash: string;
			is_admin: number;
			is_active: number;
		}>();
}

const LOCAL_RE = /^[a-z0-9](?:[a-z0-9._-]{0,62}[a-z0-9])?$/;

export async function createUser(
	env: Env,
	opts: { localPart: string; password: string; fullName?: string; isAdmin?: boolean }
): Promise<number> {
	const local = opts.localPart.trim().toLowerCase();
	if (!LOCAL_RE.test(local)) throw new AuthError('Nama pengguna tidak valid (huruf kecil, angka, . _ -)', 422);
	if (opts.password.length < 8) throw new AuthError('Kata sandi minimal 8 karakter', 422);
	const email = `${local}@${env.MAIL_DOMAIN}`;
	const exists = await env.DB.prepare('SELECT id FROM users WHERE email = ? OR local_part = ?')
		.bind(email, local)
		.first();
	if (exists) throw new AuthError('Alamat email sudah dipakai', 409);
	const hash = await hashPassword(opts.password);
	const res = await env.DB.prepare(
		'INSERT INTO users (email, local_part, full_name, password_hash, is_admin) VALUES (?, ?, ?, ?, ?)'
	)
		.bind(email, local, opts.fullName?.trim() || null, hash, opts.isAdmin ? 1 : 0)
		.run();
	return Number(res.meta.last_row_id);
}

export async function setPassword(env: Env, userId: number, password: string): Promise<void> {
	if (password.length < 8) throw new AuthError('Kata sandi minimal 8 karakter', 422);
	const hash = await hashPassword(password);
	await env.DB.prepare('UPDATE users SET password_hash = ? WHERE id = ?').bind(hash, userId).run();
}

export interface AdminUserRow {
	id: number;
	email: string;
	local_part: string;
	full_name: string | null;
	is_admin: number;
	is_active: number;
	created_at: string;
	msg_count: number;
}

export async function listUsers(env: Env): Promise<AdminUserRow[]> {
	const res = await env.DB.prepare(
		`SELECT u.id, u.email, u.local_part, u.full_name, u.is_admin, u.is_active, u.created_at,
		        (SELECT COUNT(*) FROM messages m WHERE m.user_id = u.id) AS msg_count
		 FROM users u ORDER BY u.is_admin DESC, u.created_at ASC`
	).all<AdminUserRow>();
	return res.results || [];
}

export async function countAdmins(env: Env): Promise<number> {
	const r = await env.DB.prepare(
		'SELECT COUNT(*) AS n FROM users WHERE is_admin = 1 AND is_active = 1'
	).first<{ n: number }>();
	return r?.n ?? 0;
}

export async function setAdmin(env: Env, userId: number, isAdmin: boolean): Promise<void> {
	if (!isAdmin) {
		// jangan sampai tidak ada admin tersisa
		const admins = await countAdmins(env);
		const target = await env.DB.prepare('SELECT is_admin FROM users WHERE id = ?')
			.bind(userId)
			.first<{ is_admin: number }>();
		if (target?.is_admin && admins <= 1) throw new AuthError('Harus ada minimal satu admin.', 409);
	}
	await env.DB.prepare('UPDATE users SET is_admin = ? WHERE id = ?')
		.bind(isAdmin ? 1 : 0, userId)
		.run();
}

export async function deleteUser(env: Env, userId: number): Promise<void> {
	const target = await env.DB.prepare('SELECT is_admin FROM users WHERE id = ?')
		.bind(userId)
		.first<{ is_admin: number }>();
	if (!target) return;
	if (target.is_admin && (await countAdmins(env)) <= 1)
		throw new AuthError('Tidak bisa menghapus satu-satunya admin.', 409);
	// hapus objek R2 milik user (raw + lampiran) sebelum baris D1
	const rawRows = await env.DB.prepare('SELECT raw_key FROM messages WHERE user_id = ?')
		.bind(userId)
		.all<{ raw_key: string | null }>();
	const attRows = await env.DB.prepare(
		'SELECT a.r2_key FROM attachments a JOIN messages m ON m.id = a.message_id WHERE m.user_id = ?'
	)
		.bind(userId)
		.all<{ r2_key: string }>();
	const keys = [
		...(rawRows.results || []).map((r) => r.raw_key),
		...(attRows.results || []).map((a) => a.r2_key)
	].filter(Boolean) as string[];
	await Promise.all(keys.map((k) => env.MAILSTORE.delete(k).catch(() => {})));
	// messages & attachments & sessions ikut terhapus via ON DELETE CASCADE
	await env.DB.prepare('DELETE FROM users WHERE id = ?').bind(userId).run();
}
