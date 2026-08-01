import { fail, redirect } from '@sveltejs/kit';
import { mailCredentials, mailStatus } from '$lib/server/portal';
import {
	getMessage,
	listFolders,
	listMessages,
	moveToTrash,
	sendMessage,
	setSeen,
	type Creds
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
	const folders = await listFolders(creds);

	const key = url.searchParams.get('folder') || 'inbox';
	const folder = folders.find((f) => f.key === key) || folders[0];
	const page = Number(url.searchParams.get('page') || '1') || 1;
	const q = url.searchParams.get('q') || '';

	const list = await listMessages(creds, folder.path, { page, search: q });

	const uidParam = url.searchParams.get('uid');
	let message = null;
	if (uidParam) {
		message = await getMessage(creds, folder.path, Number(uidParam)).catch(() => null);
	}

	return {
		account,
		folders,
		folderKey: folder.key,
		folderPath: folder.path,
		messages: list.messages,
		total: list.total,
		page: list.page,
		pages: list.pages,
		q,
		message
	};
};

export const actions: Actions = {
	send: async ({ request, locals }) => {
		if (!locals.token) throw redirect(302, '/login');
		const creds = await mailCredentials(locals.token);
		const folders = await listFolders(creds);
		const sentPath = folders.find((f) => f.key === 'sent')?.path;

		const f = await request.formData();
		const to = String(f.get('to') ?? '').trim();
		const cc = String(f.get('cc') ?? '').trim();
		const subject = String(f.get('subject') ?? '').trim();
		const text = String(f.get('body') ?? '');
		const inReplyTo = String(f.get('in_reply_to') ?? '') || undefined;
		const references = String(f.get('references') ?? '') || undefined;

		if (!to) return fail(422, { sendError: 'Isi penerima (To) dulu.' });

		try {
			await sendMessage(creds, { to, cc, subject, text, inReplyTo, references }, sentPath);
		} catch (e: any) {
			return fail(502, { sendError: e?.message || 'Gagal mengirim email.' });
		}
		return { sent: true };
	},

	trash: async ({ request, locals }) => {
		if (!locals.token) throw redirect(302, '/login');
		const creds = await mailCredentials(locals.token);
		const folders = await listFolders(creds);
		const trashPath = folders.find((f) => f.key === 'trash')?.path || 'Trash';

		const f = await request.formData();
		const uid = Number(f.get('uid'));
		const folderPath = String(f.get('folder_path') ?? '');
		const key = String(f.get('folder_key') ?? 'inbox');
		try {
			await moveToTrash(creds, folderPath, trashPath, uid);
		} catch {
			/* ignore */
		}
		throw redirect(303, `/?folder=${key}`);
	},

	toggleRead: async ({ request, locals }) => {
		if (!locals.token) throw redirect(302, '/login');
		const creds = await mailCredentials(locals.token);
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
