import { ImapFlow, type ListResponse } from 'imapflow';
import nodemailer from 'nodemailer';
import MailComposer from 'nodemailer/lib/mail-composer';
import { simpleParser } from 'mailparser';
import { env } from '$env/dynamic/private';

export interface Creds {
	email: string;
	password: string;
}

export type FolderKey = 'inbox' | 'sent' | 'drafts' | 'junk' | 'trash';

export interface Folder {
	key: FolderKey;
	label: string;
	path: string;
	unseen?: number;
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
	filename: string;
	contentType: string;
	size: number;
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
}

const IMAP_HOST = () => env.MAIL_IMAP_HOST || '127.0.0.1';
const IMAP_PORT = () => Number(env.MAIL_IMAP_PORT || 993);
const SMTP_HOST = () => env.MAIL_SMTP_HOST || '127.0.0.1';
const SMTP_PORT = () => Number(env.MAIL_SMTP_PORT || 587);

const FOLDER_LABELS: Record<FolderKey, string> = {
	inbox: 'Kotak Masuk',
	sent: 'Terkirim',
	drafts: 'Draf',
	junk: 'Spam',
	trash: 'Sampah'
};

function client(creds: Creds): ImapFlow {
	return new ImapFlow({
		host: IMAP_HOST(),
		port: IMAP_PORT(),
		secure: IMAP_PORT() === 993,
		auth: { user: creds.email, pass: creds.password },
		logger: false,
		tls: { rejectUnauthorized: false }
	});
}

async function withImap<T>(creds: Creds, fn: (c: ImapFlow) => Promise<T>): Promise<T> {
	const c = client(creds);
	await c.connect();
	try {
		return await fn(c);
	} finally {
		await c.logout().catch(() => {
			try {
				c.close();
			} catch {
				/* ignore */
			}
		});
	}
}

function detectFolders(list: ListResponse[]): Record<FolderKey, string> {
	const map: Record<FolderKey, string> = {
		inbox: 'INBOX',
		sent: '',
		drafts: '',
		junk: '',
		trash: ''
	};
	const bySpecial = (use: string) => list.find((m) => m.specialUse === use)?.path;
	const byName = (re: RegExp) => list.find((m) => re.test(m.name) || re.test(m.path))?.path;

	map.sent = bySpecial('\\Sent') || byName(/^sent$|sent items/i) || 'Sent';
	map.drafts = bySpecial('\\Drafts') || byName(/^drafts?$/i) || 'Drafts';
	map.junk = bySpecial('\\Junk') || byName(/^junk$|^spam$/i) || 'Junk';
	map.trash = bySpecial('\\Trash') || byName(/^trash$|deleted/i) || 'Trash';
	return map;
}

function addr(a?: { name?: string; address?: string }[] | null): { name: string; address: string } {
	const first = a && a[0];
	return { name: first?.name || '', address: first?.address || '' };
}

function addrList(a?: { name?: string; address?: string }[] | null): string {
	if (!a || !a.length) return '';
	return a.map((x) => x.name || x.address || '').join(', ');
}

function structureHasAttachments(node: any): boolean {
	if (!node) return false;
	if (node.disposition === 'attachment') return true;
	if (Array.isArray(node.childNodes)) return node.childNodes.some(structureHasAttachments);
	return false;
}

/** Daftar folder standar + jumlah belum dibaca di INBOX. */
export async function listFolders(creds: Creds): Promise<Folder[]> {
	return withImap(creds, async (c) => {
		const list = await c.list();
		const paths = detectFolders(list);
		const order: FolderKey[] = ['inbox', 'sent', 'drafts', 'junk', 'trash'];
		const folders: Folder[] = [];
		for (const key of order) {
			const path = paths[key];
			if (!path) continue;
			let unseen: number | undefined;
			if (key === 'inbox') {
				try {
					const st = await c.status(path, { unseen: true });
					unseen = st.unseen;
				} catch {
					/* ignore */
				}
			}
			folders.push({ key, label: FOLDER_LABELS[key], path, unseen });
		}
		return folders;
	});
}

export async function listMessages(
	creds: Creds,
	folderPath: string,
	opts: { page?: number; pageSize?: number; search?: string } = {}
): Promise<{ messages: MsgSummary[]; total: number; page: number; pages: number }> {
	const page = Math.max(1, opts.page || 1);
	const pageSize = opts.pageSize || 25;

	return withImap(creds, async (c) => {
		const lock = await c.getMailboxLock(folderPath);
		try {
			const total = c.mailbox && typeof c.mailbox !== 'boolean' ? c.mailbox.exists : 0;
			const query = { envelope: true, flags: true, uid: true, bodyStructure: true } as const;
			let seqs: string | number[] = '';
			let effectiveTotal = total;

			if (opts.search && opts.search.trim()) {
				const q = opts.search.trim();
				const uids = await c.search(
					{ or: [{ header: { subject: q } }, { from: q }, { to: q } ] },
					{ uid: true }
				);
				const arr = (uids || []).slice(-pageSize * page);
				effectiveTotal = uids?.length || 0;
				if (!arr.length) return { messages: [], total: effectiveTotal, page, pages: 1 };
				seqs = arr;
			} else {
				if (total === 0) return { messages: [], total: 0, page, pages: 1 };
				const end = total - (page - 1) * pageSize;
				const start = Math.max(1, end - pageSize + 1);
				if (end < 1) return { messages: [], total, page, pages: Math.ceil(total / pageSize) };
				seqs = `${start}:${end}`;
			}

			const out: MsgSummary[] = [];
			const iter =
				typeof seqs === 'string'
					? c.fetch(seqs, query)
					: c.fetch(seqs, query, { uid: true });
			for await (const m of iter) {
				const from = addr(m.envelope?.from);
				const flags = m.flags || new Set<string>();
				out.push({
					uid: m.uid,
					subject: m.envelope?.subject || '(tanpa subjek)',
					fromName: from.name || from.address,
					fromAddr: from.address,
					to: addrList(m.envelope?.to),
					date: m.envelope?.date ? new Date(m.envelope.date).toISOString() : null,
					seen: flags.has('\\Seen'),
					flagged: flags.has('\\Flagged'),
					answered: flags.has('\\Answered'),
					attachments: structureHasAttachments(m.bodyStructure)
				});
			}
			out.reverse();
			return { messages: out, total: effectiveTotal, page, pages: Math.max(1, Math.ceil(effectiveTotal / pageSize)) };
		} finally {
			lock.release();
		}
	});
}

export async function getMessage(
	creds: Creds,
	folderPath: string,
	uid: number
): Promise<FullMessage | null> {
	return withImap(creds, async (c) => {
		const lock = await c.getMailboxLock(folderPath);
		try {
			const msg = await c.fetchOne(String(uid), { source: true, uid: true }, { uid: true });
			if (!msg || !msg.source) return null;
			const parsed = await simpleParser(msg.source as Buffer);

			// tandai sudah dibaca
			try {
				await c.messageFlagsAdd({ uid: String(uid) }, ['\\Seen'], { uid: true });
			} catch {
				/* ignore */
			}

			const from = parsed.from?.value?.[0];
			return {
				uid,
				subject: parsed.subject || '(tanpa subjek)',
				fromName: from?.name || from?.address || '',
				fromAddr: from?.address || '',
				to: (parsed.to && ('text' in parsed.to ? parsed.to.text : '')) || '',
				cc: (parsed.cc && ('text' in parsed.cc ? parsed.cc.text : '')) || '',
				date: parsed.date ? parsed.date.toISOString() : null,
				html: parsed.html || null,
				text: parsed.text || null,
				messageId: parsed.messageId || null,
				references: Array.isArray(parsed.references)
					? parsed.references.join(' ')
					: parsed.references || null,
				attachments: (parsed.attachments || []).map((a) => ({
					filename: a.filename || 'lampiran',
					contentType: a.contentType || 'application/octet-stream',
					size: a.size || 0
				}))
			};
		} finally {
			lock.release();
		}
	});
}

export async function setSeen(creds: Creds, folderPath: string, uid: number, seen: boolean) {
	return withImap(creds, async (c) => {
		const lock = await c.getMailboxLock(folderPath);
		try {
			if (seen) await c.messageFlagsAdd({ uid: String(uid) }, ['\\Seen'], { uid: true });
			else await c.messageFlagsRemove({ uid: String(uid) }, ['\\Seen'], { uid: true });
		} finally {
			lock.release();
		}
	});
}

export async function moveToTrash(
	creds: Creds,
	folderPath: string,
	trashPath: string,
	uid: number
) {
	return withImap(creds, async (c) => {
		const lock = await c.getMailboxLock(folderPath);
		try {
			if (folderPath === trashPath) {
				await c.messageDelete({ uid: String(uid) }, { uid: true });
			} else {
				await c.messageMove({ uid: String(uid) }, trashPath, { uid: true });
			}
		} finally {
			lock.release();
		}
	});
}

export async function sendMessage(
	creds: Creds,
	msg: {
		to: string;
		cc?: string;
		subject: string;
		text?: string;
		html?: string;
		inReplyTo?: string;
		references?: string;
	},
	sentPath?: string
): Promise<void> {
	const composer = new MailComposer({
		from: creds.email,
		to: msg.to,
		cc: msg.cc || undefined,
		subject: msg.subject,
		text: msg.text || undefined,
		html: msg.html || undefined,
		inReplyTo: msg.inReplyTo || undefined,
		references: msg.references || undefined
	});
	const raw: Buffer = await composer.compile().build();

	const transporter = nodemailer.createTransport({
		host: SMTP_HOST(),
		port: SMTP_PORT(),
		secure: SMTP_PORT() === 465,
		requireTLS: SMTP_PORT() === 587,
		auth: { user: creds.email, pass: creds.password },
		tls: { rejectUnauthorized: false }
	});

	const recipients = [
		...msg.to.split(',').map((s) => s.trim()).filter(Boolean),
		...(msg.cc ? msg.cc.split(',').map((s) => s.trim()).filter(Boolean) : [])
	];

	await transporter.sendMail({
		envelope: { from: creds.email, to: recipients },
		raw
	});

	// simpan salinan ke folder Terkirim
	if (sentPath) {
		try {
			await withImap(creds, async (c) => {
				await c.append(sentPath, raw, ['\\Seen']);
			});
		} catch {
			/* biarkan; pengiriman tetap sukses walau append gagal */
		}
	}
}
