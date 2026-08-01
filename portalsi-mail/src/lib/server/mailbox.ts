import { ImapFlow, type ListResponse } from 'imapflow';
import nodemailer from 'nodemailer';
import MailComposer from 'nodemailer/lib/mail-composer';
import { simpleParser } from 'mailparser';
import { env } from '$env/dynamic/private';

export interface Creds {
	email: string;
	password: string;
}

export type FolderKey = 'inbox' | 'starred' | 'sent' | 'drafts' | 'archive' | 'junk' | 'trash';

/** Sentinel path untuk tampilan virtual "Berbintang" (bukan folder IMAP asli). */
export const STARRED_PATH = '__STARRED__';

export interface Folder {
	key: FolderKey;
	label: string;
	path: string;
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

export interface OutAttachment {
	filename: string;
	content: Buffer;
	contentType?: string;
}

const IMAP_HOST = () => env.MAIL_IMAP_HOST || '127.0.0.1';
const IMAP_PORT = () => Number(env.MAIL_IMAP_PORT || 993);
const SMTP_HOST = () => env.MAIL_SMTP_HOST || '127.0.0.1';
const SMTP_PORT = () => Number(env.MAIL_SMTP_PORT || 587);

const FOLDER_LABELS: Record<FolderKey, string> = {
	inbox: 'Kotak Masuk',
	starred: 'Berbintang',
	sent: 'Terkirim',
	drafts: 'Draf',
	archive: 'Arsip',
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

function detectFolders(list: ListResponse[]): Record<Exclude<FolderKey, 'starred'>, string> {
	const map = {
		inbox: 'INBOX',
		sent: '',
		drafts: '',
		archive: '',
		junk: '',
		trash: ''
	} as Record<Exclude<FolderKey, 'starred'>, string>;
	const bySpecial = (use: string) => list.find((m) => m.specialUse === use)?.path;
	const byName = (re: RegExp) => list.find((m) => re.test(m.name) || re.test(m.path))?.path;

	map.sent = bySpecial('\\Sent') || byName(/^sent$|sent items/i) || 'Sent';
	map.drafts = bySpecial('\\Drafts') || byName(/^drafts?$/i) || 'Drafts';
	map.archive = bySpecial('\\Archive') || byName(/^archive$|^arsip$/i) || 'Archive';
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

/** Normalisasi subjek untuk pengelompokan percakapan (buang Re:/Fwd:). */
export function normalizeSubject(s?: string | null): string {
	return (s || '')
		.replace(/^(\s*(re|fw|fwd|balas|teruskan)\s*:\s*)+/gi, '')
		.trim()
		.toLowerCase();
}

/** Daftar folder standar + jumlah belum dibaca di INBOX. */
export async function listFolders(creds: Creds): Promise<Folder[]> {
	return withImap(creds, async (c) => {
		const list = await c.list();
		const paths = detectFolders(list);
		let inboxUnseen: number | undefined;
		try {
			const st = await c.status('INBOX', { unseen: true });
			inboxUnseen = st.unseen;
		} catch {
			/* ignore */
		}
		const folders: Folder[] = [
			{ key: 'inbox', label: FOLDER_LABELS.inbox, path: paths.inbox, unseen: inboxUnseen },
			{ key: 'starred', label: FOLDER_LABELS.starred, path: STARRED_PATH, virtual: true },
			{ key: 'sent', label: FOLDER_LABELS.sent, path: paths.sent },
			{ key: 'drafts', label: FOLDER_LABELS.drafts, path: paths.drafts },
			{ key: 'archive', label: FOLDER_LABELS.archive, path: paths.archive },
			{ key: 'junk', label: FOLDER_LABELS.junk, path: paths.junk },
			{ key: 'trash', label: FOLDER_LABELS.trash, path: paths.trash }
		];
		return folders;
	});
}

function summaryFrom(m: any): MsgSummary {
	const from = addr(m.envelope?.from);
	const flags = m.flags || new Set<string>();
	return {
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
	};
}

export async function listMessages(
	creds: Creds,
	folderPath: string,
	opts: { page?: number; pageSize?: number; search?: string } = {}
): Promise<{ messages: MsgSummary[]; total: number; page: number; pages: number }> {
	const page = Math.max(1, opts.page || 1);
	const pageSize = opts.pageSize || 25;
	const isStarred = folderPath === STARRED_PATH;
	const mailbox = isStarred ? 'INBOX' : folderPath;
	const query = { envelope: true, flags: true, uid: true, bodyStructure: true } as const;

	return withImap(creds, async (c) => {
		const lock = await c.getMailboxLock(mailbox);
		try {
			const total = c.mailbox && typeof c.mailbox !== 'boolean' ? c.mailbox.exists : 0;
			let uidList: number[] | null = null;

			if (isStarred) {
				uidList = (await c.search({ flagged: true }, { uid: true })) || [];
			} else if (opts.search && opts.search.trim()) {
				const q = opts.search.trim();
				uidList =
					(await c.search(
						{ or: [{ header: { subject: q } }, { from: q }, { to: q }, { body: q }] },
						{ uid: true }
					)) || [];
			}

			let seqs: string | number[];
			let effectiveTotal: number;

			if (uidList) {
				effectiveTotal = uidList.length;
				const startIdx = Math.max(0, uidList.length - pageSize * page);
				const endIdx = uidList.length - pageSize * (page - 1);
				const arr = uidList.slice(startIdx, endIdx);
				if (!arr.length)
					return {
						messages: [],
						total: effectiveTotal,
						page,
						pages: Math.max(1, Math.ceil(effectiveTotal / pageSize))
					};
				seqs = arr;
			} else {
				effectiveTotal = total;
				if (total === 0) return { messages: [], total: 0, page, pages: 1 };
				const end = total - (page - 1) * pageSize;
				const start = Math.max(1, end - pageSize + 1);
				if (end < 1) return { messages: [], total, page, pages: Math.ceil(total / pageSize) };
				seqs = `${start}:${end}`;
			}

			const out: MsgSummary[] = [];
			const iter =
				typeof seqs === 'string' ? c.fetch(seqs, query) : c.fetch(seqs, query, { uid: true });
			for await (const m of iter) out.push(summaryFrom(m));
			out.reverse();
			return {
				messages: out,
				total: effectiveTotal,
				page,
				pages: Math.max(1, Math.ceil(effectiveTotal / pageSize))
			};
		} finally {
			lock.release();
		}
	});
}

/** Ganti referensi cid: pada HTML dengan data URI dari lampiran inline. */
function inlineCidImages(html: string, attachments: any[]): string {
	if (!html) return html;
	let out = html;
	for (const a of attachments) {
		const cid = (a.cid || a.contentId || '').replace(/[<>]/g, '');
		if (!cid || !a.content) continue;
		const dataUri = `data:${a.contentType || 'application/octet-stream'};base64,${a.content.toString('base64')}`;
		out = out.split(`cid:${cid}`).join(dataUri);
	}
	return out;
}

export async function getMessage(
	creds: Creds,
	folderPath: string,
	uid: number,
	markSeen = true
): Promise<FullMessage | null> {
	const mailbox = folderPath === STARRED_PATH ? 'INBOX' : folderPath;
	return withImap(creds, async (c) => {
		const lock = await c.getMailboxLock(mailbox);
		try {
			const msg = await c.fetchOne(
				String(uid),
				{ source: true, uid: true, flags: true },
				{ uid: true }
			);
			if (!msg || !msg.source) return null;
			const parsed = await simpleParser(msg.source as Buffer);

			if (markSeen) {
				try {
					await c.messageFlagsAdd({ uid: String(uid) }, ['\\Seen'], { uid: true });
				} catch {
					/* ignore */
				}
			}

			const parsedAtt = parsed.attachments || [];
			const html = parsed.html ? inlineCidImages(parsed.html, parsedAtt) : null;
			const attachments: Attachment[] = parsedAtt.map((a, i) => ({
				index: i,
				filename: a.filename || `lampiran-${i + 1}`,
				contentType: a.contentType || 'application/octet-stream',
				size: a.size || 0,
				inline: a.contentDisposition === 'inline' || !!a.cid
			}));

			const from = parsed.from?.value?.[0];
			const flags = (msg.flags as Set<string>) || new Set<string>();
			return {
				uid,
				subject: parsed.subject || '(tanpa subjek)',
				fromName: from?.name || from?.address || '',
				fromAddr: from?.address || '',
				to: (parsed.to && ('text' in parsed.to ? parsed.to.text : '')) || '',
				cc: (parsed.cc && ('text' in parsed.cc ? parsed.cc.text : '')) || '',
				date: parsed.date ? parsed.date.toISOString() : null,
				html,
				text: parsed.text || null,
				messageId: parsed.messageId || null,
				references: Array.isArray(parsed.references)
					? parsed.references.join(' ')
					: parsed.references || null,
				attachments,
				flagged: flags.has('\\Flagged')
			};
		} finally {
			lock.release();
		}
	});
}

/** Ambil satu lampiran (buffer) untuk diunduh. */
export async function getAttachment(
	creds: Creds,
	folderPath: string,
	uid: number,
	index: number
): Promise<{ filename: string; contentType: string; content: Buffer } | null> {
	const mailbox = folderPath === STARRED_PATH ? 'INBOX' : folderPath;
	return withImap(creds, async (c) => {
		const lock = await c.getMailboxLock(mailbox);
		try {
			const msg = await c.fetchOne(String(uid), { source: true, uid: true }, { uid: true });
			if (!msg || !msg.source) return null;
			const parsed = await simpleParser(msg.source as Buffer);
			const a = (parsed.attachments || [])[index];
			if (!a) return null;
			return {
				filename: a.filename || `lampiran-${index + 1}`,
				contentType: a.contentType || 'application/octet-stream',
				content: a.content as Buffer
			};
		} finally {
			lock.release();
		}
	});
}

/** Pesan lain dalam percakapan yang sama (berdasar subjek ternormalisasi). */
export async function getThread(
	creds: Creds,
	folderPath: string,
	subject: string,
	excludeUid: number
): Promise<MsgSummary[]> {
	const mailbox = folderPath === STARRED_PATH ? 'INBOX' : folderPath;
	const norm = normalizeSubject(subject);
	if (!norm) return [];
	return withImap(creds, async (c) => {
		const lock = await c.getMailboxLock(mailbox);
		try {
			const core = subject.replace(/^(\s*(re|fw|fwd|balas|teruskan)\s*:\s*)+/gi, '').trim();
			if (!core || core === '(tanpa subjek)') return [];
			const uids = (await c.search({ header: { subject: core } }, { uid: true })) || [];
			const pick = uids.filter((u) => u !== excludeUid).slice(-20);
			if (!pick.length) return [];
			const out: MsgSummary[] = [];
			for await (const m of c.fetch(
				pick,
				{ envelope: true, flags: true, uid: true, bodyStructure: true },
				{ uid: true }
			)) {
				if (normalizeSubject(m.envelope?.subject) === norm) out.push(summaryFrom(m));
			}
			out.sort((a, b) => (a.date || '').localeCompare(b.date || ''));
			return out;
		} finally {
			lock.release();
		}
	});
}

export async function setSeen(creds: Creds, folderPath: string, uid: number, seen: boolean) {
	const mailbox = folderPath === STARRED_PATH ? 'INBOX' : folderPath;
	return withImap(creds, async (c) => {
		const lock = await c.getMailboxLock(mailbox);
		try {
			if (seen) await c.messageFlagsAdd({ uid: String(uid) }, ['\\Seen'], { uid: true });
			else await c.messageFlagsRemove({ uid: String(uid) }, ['\\Seen'], { uid: true });
		} finally {
			lock.release();
		}
	});
}

export async function setStar(creds: Creds, folderPath: string, uid: number, on: boolean) {
	const mailbox = folderPath === STARRED_PATH ? 'INBOX' : folderPath;
	return withImap(creds, async (c) => {
		const lock = await c.getMailboxLock(mailbox);
		try {
			if (on) await c.messageFlagsAdd({ uid: String(uid) }, ['\\Flagged'], { uid: true });
			else await c.messageFlagsRemove({ uid: String(uid) }, ['\\Flagged'], { uid: true });
		} finally {
			lock.release();
		}
	});
}

async function ensureMailbox(c: ImapFlow, path: string) {
	try {
		await c.mailboxCreate(path);
	} catch {
		/* sudah ada */
	}
}

export async function archiveMessage(
	creds: Creds,
	folderPath: string,
	archivePath: string,
	uid: number
) {
	const mailbox = folderPath === STARRED_PATH ? 'INBOX' : folderPath;
	if (mailbox === archivePath) return;
	return withImap(creds, async (c) => {
		await ensureMailbox(c, archivePath);
		const lock = await c.getMailboxLock(mailbox);
		try {
			await c.messageMove({ uid: String(uid) }, archivePath, { uid: true });
		} finally {
			lock.release();
		}
	});
}

export async function moveToTrash(creds: Creds, folderPath: string, trashPath: string, uid: number) {
	const mailbox = folderPath === STARRED_PATH ? 'INBOX' : folderPath;
	return withImap(creds, async (c) => {
		const lock = await c.getMailboxLock(mailbox);
		try {
			if (mailbox === trashPath) {
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
		bcc?: string;
		subject: string;
		text?: string;
		html?: string;
		inReplyTo?: string;
		references?: string;
		attachments?: OutAttachment[];
	},
	sentPath?: string
): Promise<void> {
	const composer = new MailComposer({
		from: creds.email,
		to: msg.to,
		cc: msg.cc || undefined,
		bcc: msg.bcc || undefined,
		subject: msg.subject,
		text: msg.text || undefined,
		html: msg.html || undefined,
		inReplyTo: msg.inReplyTo || undefined,
		references: msg.references || undefined,
		attachments: msg.attachments?.length
			? msg.attachments.map((a) => ({
					filename: a.filename,
					content: a.content,
					contentType: a.contentType
				}))
			: undefined
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
		...(msg.cc ? msg.cc.split(',').map((s) => s.trim()).filter(Boolean) : []),
		...(msg.bcc ? msg.bcc.split(',').map((s) => s.trim()).filter(Boolean) : [])
	];

	await transporter.sendMail({
		envelope: { from: creds.email, to: recipients },
		raw
	});

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
