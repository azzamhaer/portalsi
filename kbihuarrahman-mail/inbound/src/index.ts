// ════════════════════════════════════════════════════════════════════
// KBIHU Ar-Rahman Mail — Inbound Email Worker (Cloudflare Email Routing).
// Dipicu saat ada email masuk untuk *@kbihuarrahman.com. Mem-parse MIME,
// menyimpan metadata ke D1 dan objek mentah/lampiran ke R2.
// ════════════════════════════════════════════════════════════════════

import PostalMime from 'postal-mime';

export interface Env {
	DB: D1Database;
	MAILSTORE: R2Bucket;
	MAIL_DOMAIN: string;
}

interface Addr {
	address?: string;
	name?: string;
}

function normalizeSubject(s?: string | null): string {
	return (s || '')
		.replace(/^(\s*(re|fw|fwd|balas|teruskan)\s*:\s*)+/gi, '')
		.trim()
		.toLowerCase();
}

function addrText(list?: Addr[] | null): string {
	if (!list || !list.length) return '';
	return list
		.map((a) => (a.name ? `${a.name} <${a.address}>` : a.address || ''))
		.filter(Boolean)
		.join(', ');
}

function snippetOf(text?: string | null, html?: string | null): string {
	const src = text || (html ? html.replace(/<[^>]+>/g, ' ') : '') || '';
	return src.replace(/\s+/g, ' ').trim().slice(0, 180);
}

function refText(v: unknown): string | null {
	if (!v) return null;
	if (Array.isArray(v)) return v.join(' ');
	return String(v);
}

export default {
	async email(message: ForwardableEmailMessage, env: Env, _ctx: ExecutionContext): Promise<void> {
		// Alamat tujuan → local part → cari mailbox pemilik
		const rcpt = (message.to || '').toLowerCase().trim();
		const local = rcpt.split('@')[0];
		const user = await env.DB.prepare('SELECT id FROM users WHERE local_part = ? AND is_active = 1')
			.bind(local)
			.first<{ id: number }>();

		if (!user) {
			// Mailbox tidak ada → tolak agar pengirim dapat bounce yang jelas.
			message.setReject('550 5.1.1 Mailbox tidak ditemukan');
			return;
		}
		const userId = user.id;

		// Baca raw sekali, pakai untuk parse + simpan ke R2
		const rawBuf = await new Response(message.raw).arrayBuffer();
		const parsed = await PostalMime.parse(rawBuf);

		const from: Addr = parsed.from || {};
		const dateIso = parsed.date ? new Date(parsed.date).toISOString() : new Date().toISOString();
		const html = parsed.html || null;
		const text = parsed.text || null;
		const atts = parsed.attachments || [];
		const realAtts = atts.filter((a) => a.disposition !== 'inline' && !a.related);

		// Simpan raw .eml ke R2
		const stamp = `${Date.now()}-${crypto.randomUUID().slice(0, 8)}`;
		const rawKey = `raw/${userId}/${stamp}.eml`;
		await env.MAILSTORE.put(rawKey, rawBuf, { httpMetadata: { contentType: 'message/rfc822' } });

		// Insert baris pesan
		const res = await env.DB.prepare(
			`INSERT INTO messages
			 (user_id, folder, seen, subject, from_name, from_addr, to_addrs, cc_addrs,
			  date, message_id, in_reply_to, ref_ids, subject_norm, snippet, has_attachments,
			  html, body_text, raw_key, size)
			 VALUES (?, 'inbox', 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`
		)
			.bind(
				userId,
				parsed.subject || '(tanpa subjek)',
				from.name || from.address || '',
				from.address || '',
				addrText(parsed.to),
				addrText(parsed.cc),
				dateIso,
				parsed.messageId || null,
				parsed.inReplyTo || null,
				refText(parsed.references),
				normalizeSubject(parsed.subject),
				snippetOf(text, html),
				realAtts.length ? 1 : 0,
				html,
				text,
				rawKey,
				rawBuf.byteLength
			)
			.run();
		const msgId = Number(res.meta.last_row_id);

		// Simpan lampiran ke R2 + baris attachments
		let idx = 0;
		for (const a of atts) {
			const bytes =
				a.content instanceof ArrayBuffer ? new Uint8Array(a.content) : (a.content as Uint8Array);
			const filename = a.filename || `lampiran-${idx + 1}`;
			const key = `att/${userId}/${msgId}/${idx}-${filename}`;
			await env.MAILSTORE.put(key, bytes, {
				httpMetadata: { contentType: a.mimeType || 'application/octet-stream' }
			});
			await env.DB.prepare(
				'INSERT INTO attachments (message_id, idx, filename, content_type, size, inline, cid, r2_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
			)
				.bind(
					msgId,
					idx,
					filename,
					a.mimeType || 'application/octet-stream',
					bytes.byteLength,
					a.disposition === 'inline' || a.related ? 1 : 0,
					a.contentId ? a.contentId.replace(/[<>]/g, '') : null,
					key
				)
				.run();
			idx++;
		}

		// Ingat kontak pengirim
		if (from.address) {
			await env.DB.prepare(
				`INSERT INTO contacts (user_id, address, name, last_seen)
				 VALUES (?, ?, ?, datetime('now'))
				 ON CONFLICT(user_id, address) DO UPDATE SET
				   name = COALESCE(NULLIF(excluded.name, ''), contacts.name),
				   last_seen = datetime('now')`
			)
				.bind(userId, from.address.toLowerCase(), from.name || '')
				.run();
		}
	}
};
