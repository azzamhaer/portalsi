import { fail, redirect } from '@sveltejs/kit';
import { mailCredentials, mailStatus } from '$lib/server/portal';
import {
	archiveMessage,
	listFolders,
	loadView,
	moveToTrash,
	sendMessage,
	setSeen,
	setStar,
	type Creds,
	type OutAttachment
} from '$lib/server/mailbox';
import type { Actions, PageServerLoad } from './$types';

async function ensure(locals: App.Locals): Promise<{ token: string; creds: Creds; account: any }> {
	if (!locals.user || !locals.token) throw redirect(302, '/login');
	const status = await mailStatus(locals.token);
	if (status.gate_enabled && !status.unlocked) throw redirect(302, '/gate');
	if (!status.has_account) throw redirect(302, '/setup');
	const creds = await mailCredentials(locals.token);
	return { token: locals.token, creds, account: status.account };
}

export const load: PageServerLoad = async ({ locals, url }) => {
	const { creds, account } = await ensure(locals);

	const key = url.searchParams.get('folder') || 'inbox';
	const page = Number(url.searchParams.get('page') || '1') || 1;
	const q = url.searchParams.get('q') || '';
	const uidParam = url.searchParams.get('uid');
	const uid = uidParam ? Number(uidParam) : undefined;

	const view = await loadView(creds, { key, page, q, uid });

	return {
		account,
		user: locals.user,
		folders: view.folders,
		folderKey: view.folderKey,
		folderPath: view.folderPath,
		messages: view.list?.messages ?? [],
		total: view.list?.total ?? 0,
		page: view.list?.page ?? page,
		pages: view.list?.pages ?? 1,
		q,
		message: view.message,
		thread: view.thread
	};
};

function credsFrom(locals: App.Locals) {
	if (!locals.token) throw redirect(302, '/login');
	return mailCredentials(locals.token);
}

export const actions: Actions = {
	send: async ({ request, locals }) => {
		const creds = await credsFrom(locals);
		const folders = await listFolders(creds);
		const sentPath = folders.find((f) => f.key === 'sent')?.path;

		const f = await request.formData();
		const to = String(f.get('to') ?? '').trim();
		const cc = String(f.get('cc') ?? '').trim();
		const bcc = String(f.get('bcc') ?? '').trim();
		const subject = String(f.get('subject') ?? '').trim();
		const text = String(f.get('body') ?? '');
		const html = String(f.get('html') ?? '') || undefined;
		const inReplyTo = String(f.get('in_reply_to') ?? '') || undefined;
		const references = String(f.get('references') ?? '') || undefined;
		const fromName = String(f.get('from_name') ?? '').trim() || undefined;

		if (!to) return fail(422, { sendError: 'Isi penerima (To) dulu.' });

		const attachments: OutAttachment[] = [];
		for (const item of f.getAll('files')) {
			if (item instanceof File && item.size > 0) {
				const buf = Buffer.from(await item.arrayBuffer());
				attachments.push({ filename: item.name, content: buf, contentType: item.type || undefined });
			}
		}

		try {
			await sendMessage(
				creds,
				{ to, cc, bcc, subject, text, html, inReplyTo, references, attachments, fromName },
				sentPath
			);
		} catch (e: any) {
			return fail(502, { sendError: e?.message || 'Gagal mengirim email.' });
		}
		return { sent: true };
	},

	star: async ({ request, locals }) => {
		const creds = await credsFrom(locals);
		const f = await request.formData();
		const uid = Number(f.get('uid'));
		const folderPath = String(f.get('folder_path') ?? 'INBOX');
		const on = String(f.get('on')) === '1';
		try {
			await setStar(creds, folderPath, uid, on);
		} catch {
			/* ignore */
		}
		return { ok: true };
	},

	archive: async ({ request, locals }) => {
		const creds = await credsFrom(locals);
		const folders = await listFolders(creds);
		const archivePath = folders.find((f) => f.key === 'archive')?.path || 'Archive';
		const f = await request.formData();
		const uid = Number(f.get('uid'));
		const folderPath = String(f.get('folder_path') ?? 'INBOX');
		try {
			await archiveMessage(creds, folderPath, archivePath, uid);
		} catch {
			/* ignore */
		}
		return { ok: true };
	},

	trash: async ({ request, locals }) => {
		const creds = await credsFrom(locals);
		const folders = await listFolders(creds);
		const trashPath = folders.find((f) => f.key === 'trash')?.path || 'Trash';
		const f = await request.formData();
		const uid = Number(f.get('uid'));
		const folderPath = String(f.get('folder_path') ?? '');
		try {
			await moveToTrash(creds, folderPath, trashPath, uid);
		} catch {
			/* ignore */
		}
		return { ok: true };
	},

	toggleRead: async ({ request, locals }) => {
		const creds = await credsFrom(locals);
		const f = await request.formData();
		const uid = Number(f.get('uid'));
		const folderPath = String(f.get('folder_path') ?? '');
		const seen = String(f.get('seen')) === '1';
		try {
			await setSeen(creds, folderPath, uid, seen);
		} catch {
			/* ignore */
		}
		return { ok: true };
	}
};
