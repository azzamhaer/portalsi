// ════════════════════════════════════════════════════════════════════
// Pengiriman email keluar via Brevo Transactional API (HTTPS).
// Workers TIDAK bisa buka koneksi SMTP mentah, jadi kirim lewat REST.
// Docs: https://developers.brevo.com/reference/sendtransacemail
// ════════════════════════════════════════════════════════════════════

import type { Env } from './auth';

export interface Addr {
	email: string;
	name?: string;
}

export interface OutAttachment {
	filename: string;
	/** isi base64 (tanpa prefix data:) */
	contentB64: string;
	contentType?: string;
}

export interface SendInput {
	from: Addr;
	to: Addr[];
	cc?: Addr[];
	bcc?: Addr[];
	subject: string;
	html?: string;
	text?: string;
	inReplyTo?: string;
	references?: string;
	attachments?: OutAttachment[];
}

export interface SendResult {
	messageId: string;
}

const BREVO_URL = 'https://api.brevo.com/v3/smtp/email';

export function parseAddressList(raw: string | undefined | null): Addr[] {
	if (!raw) return [];
	return raw
		.split(',')
		.map((s) => s.trim())
		.filter(Boolean)
		.map((s) => {
			// dukung format "Nama <email@dom>"
			const m = s.match(/^\s*"?([^"<]*)"?\s*<([^>]+)>\s*$/);
			if (m) return { name: m[1].trim() || undefined, email: m[2].trim() };
			return { email: s };
		})
		.filter((a) => a.email.includes('@'));
}

export async function sendViaBrevo(env: Env, input: SendInput): Promise<SendResult> {
	if (!env.BREVO_API_KEY) throw new Error('BREVO_API_KEY belum di-set');
	if (!input.to.length) throw new Error('Penerima kosong');

	const headers: Record<string, string> = {};
	if (input.inReplyTo) headers['In-Reply-To'] = input.inReplyTo;
	if (input.references) headers['References'] = input.references;

	const body: Record<string, unknown> = {
		sender: { email: input.from.email, name: input.from.name || env.BREVO_SENDER_NAME },
		to: input.to.map((a) => ({ email: a.email, name: a.name })),
		subject: input.subject || '(tanpa subjek)'
	};
	if (input.cc?.length) body.cc = input.cc.map((a) => ({ email: a.email, name: a.name }));
	if (input.bcc?.length) body.bcc = input.bcc.map((a) => ({ email: a.email, name: a.name }));
	if (input.html) body.htmlContent = input.html;
	if (input.text) body.textContent = input.text;
	if (!input.html && !input.text) body.textContent = ' ';
	if (Object.keys(headers).length) body.headers = headers;
	if (input.attachments?.length)
		body.attachment = input.attachments.map((a) => ({ name: a.filename, content: a.contentB64 }));

	const res = await fetch(BREVO_URL, {
		method: 'POST',
		headers: {
			'api-key': env.BREVO_API_KEY,
			'content-type': 'application/json',
			accept: 'application/json'
		},
		body: JSON.stringify(body)
	});

	const txt = await res.text();
	if (!res.ok) {
		let msg = txt;
		try {
			msg = JSON.parse(txt).message || txt;
		} catch {
			/* biarkan */
		}
		throw new Error(`Brevo gagal (${res.status}): ${msg}`);
	}
	let messageId = '';
	try {
		messageId = JSON.parse(txt).messageId || '';
	} catch {
		/* biarkan */
	}
	return { messageId };
}
