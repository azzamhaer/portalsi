// ════════════════════════════════════════════════════════════════════
// Data layer email berbasis D1 + R2 — pengganti mailbox.ts (IMAP).
// Meniru kontrak tipe lama (Folder / MsgSummary / FullMessage / ViewResult)
// agar UI dari mail.kbihuarrahman.com bisa dipakai tanpa perubahan besar.
// ════════════════════════════════════════════════════════════════════

import type { Env } from './auth';

export type FolderKey = 'inbox' | 'starred' | 'sent' | 'drafts' | 'archive' | 'junk' | 'trash';

export interface Folder {
	key: FolderKey;
	label: string;
	path: string; // = key (kompat UI lama)
	unseen?: number;
	virtual?: boolean;
}

export interface MsgSummary {
	uid: number;
	subject: string;
	fromName: string;
	fromAddr: string;
	to: string;
	date: string | null;
	seen: boolean;
	flagged: boolean;
	answered: boolean;
	attachments: boolean;
}

export interface Attachment {
	index: number;
	filename: string;
	contentType: string;
	size: number;
	inline: boolean;
}

export interface FullMessage {
	uid: number;
	subject: string;
	fromName: string;
	fromAddr: string;
	to: string;
	cc: string;
	date: string | null;
	html: string | null;
	text: string | null;
	messageId: string | null;
	references: string | null;
	attachments: Attachment[];
	flagged: boolean;
}

export interface ListResult {
	messages: MsgSummary[];
	total: number;
	page: number;
	pages: number;
}

export interface ViewResult {
	folders: Folder[];
	folderKey: FolderKey;
	folderPath: string;
	message: FullMessage | null;
	thread: MsgSummary[];
	list: ListResult | null;
}

const PAGE_SIZE = 25;

const FOLDER_LABELS: Record<FolderKey, string> = {
	inbox: 'Kotak Masuk',
	starred: 'Berbintang',
	sent: 'Terkirim',
	drafts: 'Draf',
	archive: 'Arsip',
	junk: 'Spam',
	trash: 'Sampah'
};

export function normalizeSubject(s?: string | null): string {
	return (s || '')
		.replace(/^(\s*(re|fw|fwd|balas|teruskan)\s*:\s*)+/gi, '')
		.trim()
		.toLowerCase();
}

function rowToSummary(r: any): MsgSummary {
	return {
		uid: r.id,
		subject: r.subject || '(tanpa subjek)',
		fromName: r.from_name || r.from_addr || '',
		fromAddr: r.from_addr || '',
		to: r.to_addrs || '',
		date: r.date || null,
		seen: !!r.seen,
		flagged: !!r.flagged,
		answered: !!r.answered,
		attachments: !!r.has_attachments
	};
}

const SUMMARY_COLS =
	'id, subject, from_name, from_addr, to_addrs, date, seen, flagged, answered, has_attachments';

// ─────────────────────────── folders ───────────────────────────
export async function listFolders(env: Env, userId: number, withCounts = true): Promise<Folder[]> {
	const folders: Folder[] = [
		{ key: 'inbox', label: FOLDER_LABELS.inbox, path: 'inbox' },
		{ key: 'starred', label: FOLDER_LABELS.starred, path: 'starred', virtual: true },
		{ key: 'sent', label: FOLDER_LABELS.sent, path: 'sent' },
		{ key: 'drafts', label: FOLDER_LABELS.drafts, path: 'drafts' },
		{ key: 'archive', label: FOLDER_LABELS.archive, path: 'archive' },
		{ key: 'junk', label: FOLDER_LABELS.junk, path: 'junk' },
		{ key: 'trash', label: FOLDER_LABELS.trash, path: 'trash' }
	];
	if (!withCounts) return folders;

	// badge: inbox & junk = belum dibaca; drafts & trash = total
	const rows = await env.DB.prepare(
		`SELECT folder,
		        SUM(CASE WHEN seen = 0 THEN 1 ELSE 0 END) AS unseen,
		        COUNT(*) AS total
		 FROM messages WHERE user_id = ? GROUP BY folder`
	)
		.bind(userId)
		.all<{ folder: string; unseen: number; total: number }>();
	const by = new Map((rows.results || []).map((r) => [r.folder, r]));
	const rules: Record<string, 'unseen' | 'total'> = {
		inbox: 'unseen',
		junk: 'unseen',
		drafts: 'total',
		trash: 'total'
	};
	for (const f of folders) {
		const mode = rules[f.key];
		if (!mode) continue;
		const r = by.get(f.key);
		if (r) f.unseen = mode === 'unseen' ? r.unseen : r.total;
	}
	return folders;
}

// ─────────────────────────── list ───────────────────────────
export async function list(
	env: Env,
	userId: number,
	folderKey: FolderKey,
	opts: { page?: number; q?: string } = {}
): Promise<ListResult> {
	const page = Math.max(1, opts.page || 1);
	const offset = (page - 1) * PAGE_SIZE;
	const q = (opts.q || '').trim();

	const where: string[] = ['user_id = ?'];
	const args: unknown[] = [userId];
	if (folderKey === 'starred') {
		where.push("flagged = 1 AND folder != 'trash'");
	} else {
		where.push('folder = ?');
		args.push(folderKey);
	}
	if (q) {
		const like = `%${q}%`;
		where.push(
			'(subject LIKE ? OR from_addr LIKE ? OR from_name LIKE ? OR to_addrs LIKE ? OR snippet LIKE ? OR body_text LIKE ?)'
		);
		args.push(like, like, like, like, like, like);
	}
	const whereSql = where.join(' AND ');

	const totalRow = await env.DB.prepare(`SELECT COUNT(*) AS n FROM messages WHERE ${whereSql}`)
		.bind(...args)
		.first<{ n: number }>();
	const total = totalRow?.n ?? 0;

	const res = await env.DB.prepare(
		`SELECT ${SUMMARY_COLS} FROM messages WHERE ${whereSql}
		 ORDER BY COALESCE(date, created_at) DESC, id DESC LIMIT ? OFFSET ?`
	)
		.bind(...args, PAGE_SIZE, offset)
		.all();
	return {
		messages: (res.results || []).map(rowToSummary),
		total,
		page,
		pages: Math.max(1, Math.ceil(total / PAGE_SIZE))
	};
}

// ─────────────────────────── single message ───────────────────────────
export async function getMessage(
	env: Env,
	userId: number,
	uid: number,
	markSeen = true
): Promise<FullMessage | null> {
	const r = await env.DB.prepare('SELECT * FROM messages WHERE id = ? AND user_id = ?')
		.bind(uid, userId)
		.first<any>();
	if (!r) return null;

	if (markSeen && !r.seen) {
		await env.DB.prepare('UPDATE messages SET seen = 1 WHERE id = ?').bind(uid).run();
	}

	const atts = await env.DB.prepare(
		'SELECT idx, filename, content_type, size, inline FROM attachments WHERE message_id = ? ORDER BY idx'
	)
		.bind(uid)
		.all<any>();

	return {
		uid,
		subject: r.subject || '(tanpa subjek)',
		fromName: r.from_name || r.from_addr || '',
		fromAddr: r.from_addr || '',
		to: r.to_addrs || '',
		cc: r.cc_addrs || '',
		date: r.date || null,
		html: r.html || null,
		text: r.body_text || null,
		messageId: r.message_id || null,
		references: r.ref_ids || null,
		flagged: !!r.flagged,
		attachments: (atts.results || []).map((a) => ({
			index: a.idx,
			filename: a.filename || `lampiran-${a.idx + 1}`,
			contentType: a.content_type || 'application/octet-stream',
			size: a.size || 0,
			inline: !!a.inline
		}))
	};
}

export async function threadFor(
	env: Env,
	userId: number,
	subject: string,
	excludeUid: number
): Promise<MsgSummary[]> {
	const norm = normalizeSubject(subject);
	if (!norm || norm === '(tanpa subjek)') return [];
	const res = await env.DB.prepare(
		`SELECT ${SUMMARY_COLS} FROM messages
		 WHERE user_id = ? AND subject_norm = ? AND id != ?
		 ORDER BY COALESCE(date, created_at) ASC LIMIT 20`
	)
		.bind(userId, norm, excludeUid)
		.all();
	return (res.results || []).map(rowToSummary);
}

// ─────────────────────────── loadView (3-panel) ───────────────────────────
export async function loadView(
	env: Env,
	userId: number,
	p: { key: string; page: number; q: string; uid?: number }
): Promise<ViewResult> {
	const folders = await listFolders(env, userId, true);
	const folder = folders.find((f) => f.key === p.key) || folders[0];
	const listRes = await list(env, userId, folder.key, { page: p.page, q: p.q });

	let message: FullMessage | null = null;
	let thread: MsgSummary[] = [];
	if (p.uid) {
		message = await getMessage(env, userId, p.uid).catch(() => null);
		if (message) thread = await threadFor(env, userId, message.subject, message.uid).catch(() => []);
	}
	return {
		folders,
		folderKey: folder.key,
		folderPath: folder.key,
		message,
		thread,
		list: listRes
	};
}

// ─────────────────────────── flags & moves ───────────────────────────
export async function setSeen(env: Env, userId: number, uid: number, seen: boolean) {
	await env.DB.prepare('UPDATE messages SET seen = ? WHERE id = ? AND user_id = ?')
		.bind(seen ? 1 : 0, uid, userId)
		.run();
}

export async function setStar(env: Env, userId: number, uid: number, on: boolean) {
	await env.DB.prepare('UPDATE messages SET flagged = ? WHERE id = ? AND user_id = ?')
		.bind(on ? 1 : 0, uid, userId)
		.run();
}

export async function archiveMessage(env: Env, userId: number, uid: number) {
	await env.DB.prepare(
		"UPDATE messages SET folder = 'archive' WHERE id = ? AND user_id = ? AND folder != 'archive'"
	)
		.bind(uid, userId)
		.run();
}

export async function unarchiveMessage(env: Env, userId: number, uid: number) {
	await env.DB.prepare("UPDATE messages SET folder = 'inbox' WHERE id = ? AND user_id = ?")
		.bind(uid, userId)
		.run();
}

/** Ke Sampah; kalau sudah di Sampah → hapus permanen (termasuk objek R2). */
export async function moveToTrash(env: Env, userId: number, uid: number) {
	const r = await env.DB.prepare('SELECT folder FROM messages WHERE id = ? AND user_id = ?')
		.bind(uid, userId)
		.first<{ folder: string }>();
	if (!r) return;
	if (r.folder === 'trash') {
		await hardDelete(env, userId, [uid]);
	} else {
		await env.DB.prepare("UPDATE messages SET folder = 'trash' WHERE id = ? AND user_id = ?")
			.bind(uid, userId)
			.run();
	}
}

export async function expungeMessage(env: Env, userId: number, uid: number) {
	await hardDelete(env, userId, [uid]);
}

export async function emptyTrash(env: Env, userId: number): Promise<number> {
	const rows = await env.DB.prepare(
		"SELECT id FROM messages WHERE user_id = ? AND folder = 'trash'"
	)
		.bind(userId)
		.all<{ id: number }>();
	const ids = (rows.results || []).map((r) => r.id);
	await hardDelete(env, userId, ids);
	return ids.length;
}

async function hardDelete(env: Env, userId: number, uids: number[]) {
	if (!uids.length) return;
	// hapus objek R2 (raw + lampiran) lalu baris D1
	for (const uid of uids) {
		const own = await env.DB.prepare('SELECT raw_key FROM messages WHERE id = ? AND user_id = ?')
			.bind(uid, userId)
			.first<{ raw_key: string | null }>();
		if (!own) continue;
		const atts = await env.DB.prepare('SELECT r2_key FROM attachments WHERE message_id = ?')
			.bind(uid)
			.all<{ r2_key: string }>();
		const keys = [own.raw_key, ...(atts.results || []).map((a) => a.r2_key)].filter(
			Boolean
		) as string[];
		await Promise.all(keys.map((k) => env.MAILSTORE.delete(k).catch(() => {})));
		await env.DB.prepare('DELETE FROM messages WHERE id = ? AND user_id = ?').bind(uid, userId).run();
	}
}

// ─────────────────────────── attachments ───────────────────────────
export async function getAttachment(
	env: Env,
	userId: number,
	uid: number,
	index: number
): Promise<{ filename: string; contentType: string; body: ReadableStream } | null> {
	const a = await env.DB.prepare(
		`SELECT a.filename, a.content_type, a.r2_key
		 FROM attachments a JOIN messages m ON m.id = a.message_id
		 WHERE a.message_id = ? AND a.idx = ? AND m.user_id = ?`
	)
		.bind(uid, index, userId)
		.first<{ filename: string; content_type: string; r2_key: string }>();
	if (!a) return null;
	const obj = await env.MAILSTORE.get(a.r2_key);
	if (!obj) return null;
	return {
		filename: a.filename || `lampiran-${index + 1}`,
		contentType: a.content_type || 'application/octet-stream',
		body: obj.body
	};
}

// ─────────────────────────── drafts & sent ───────────────────────────
export interface ComposedInput {
	to?: string;
	cc?: string;
	subject?: string;
	html?: string;
	text?: string;
	fromName?: string;
	fromAddr?: string;
	messageId?: string;
	inReplyTo?: string;
	references?: string;
	hasAttachments?: boolean;
}

function snippetOf(text?: string | null, html?: string | null): string {
	const src = text || (html ? html.replace(/<[^>]+>/g, ' ') : '') || '';
	return src.replace(/\s+/g, ' ').trim().slice(0, 180);
}

/** Simpan baris di sebuah folder (dipakai untuk draft & salinan Terkirim). */
export async function storeLocal(
	env: Env,
	userId: number,
	folder: FolderKey,
	m: ComposedInput & { seen?: boolean; draft?: boolean; date?: string }
): Promise<number> {
	const date = m.date || new Date().toISOString();
	const res = await env.DB.prepare(
		`INSERT INTO messages
		 (user_id, folder, seen, draft, subject, from_name, from_addr, to_addrs, cc_addrs,
		  date, message_id, in_reply_to, ref_ids, subject_norm, snippet, has_attachments, html, body_text, size)
		 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`
	)
		.bind(
			userId,
			folder,
			m.seen ? 1 : 0,
			m.draft ? 1 : 0,
			m.subject || '(tanpa subjek)',
			m.fromName || null,
			m.fromAddr || null,
			m.to || null,
			m.cc || null,
			date,
			m.messageId || null,
			m.inReplyTo || null,
			m.references || null,
			normalizeSubject(m.subject),
			snippetOf(m.text, m.html),
			m.hasAttachments ? 1 : 0,
			m.html || null,
			m.text || null,
			(m.html || m.text || '').length
		)
		.run();
	return Number(res.meta.last_row_id);
}

// ─────────────────────────── contacts ───────────────────────────
export interface Contact {
	name: string;
	address: string;
}

export async function recentContacts(env: Env, userId: number, limit = 200): Promise<Contact[]> {
	const res = await env.DB.prepare(
		'SELECT address, name FROM contacts WHERE user_id = ? ORDER BY last_seen DESC LIMIT ?'
	)
		.bind(userId, limit)
		.all<{ address: string; name: string | null }>();
	return (res.results || []).map((r) => ({ address: r.address, name: r.name || '' }));
}

export async function rememberContacts(env: Env, userId: number, list: Contact[]) {
	for (const c of list) {
		const addr = c.address.trim().toLowerCase();
		if (!addr.includes('@')) continue;
		await env.DB.prepare(
			`INSERT INTO contacts (user_id, address, name, last_seen)
			 VALUES (?, ?, ?, datetime('now'))
			 ON CONFLICT(user_id, address) DO UPDATE SET
			   name = COALESCE(NULLIF(excluded.name, ''), contacts.name),
			   last_seen = datetime('now')`
		)
			.bind(userId, addr, c.name || '')
			.run();
	}
}

// ─────────────────────────── search preview ───────────────────────────
export async function searchPreview(
	env: Env,
	userId: number,
	q: string,
	limit = 12
): Promise<MsgSummary[]> {
	const term = q.trim();
	if (!term) return [];
	const like = `%${term}%`;
	const res = await env.DB.prepare(
		`SELECT ${SUMMARY_COLS} FROM messages
		 WHERE user_id = ? AND folder != 'trash'
		   AND (subject LIKE ? OR from_addr LIKE ? OR from_name LIKE ? OR to_addrs LIKE ? OR snippet LIKE ?)
		 ORDER BY COALESCE(date, created_at) DESC LIMIT ?`
	)
		.bind(userId, like, like, like, like, like, limit)
		.all();
	return (res.results || []).map(rowToSummary);
}
