import { fail, redirect } from '@sveltejs/kit';
import { env } from '$env/dynamic/private';
import {
	forgotPassword,
	mailCredentials,
	mailStatus,
	requestEmailChange,
	updateProfile,
	uploadPhoto
} from '$lib/server/portal';
import {
	archiveMessage,
	emptyTrash,
	listFolders,
	loadView,
	moveToTrash,
	sendMessage,
	setSeen,
	setStar,
	unarchiveMessage,
	type Creds,
	type OutAttachment
} from '$lib/server/mailbox';
import type { Actions, PageServerLoad } from './$types';

// batas permintaan reset password: 3/hari per user (in-memory, cukup untuk beta)
const resetCounts = new Map<string, { date: string; count: number }>();

// domain alias tambahan (mis. "sekolahimpian.com"); user dapat alamat kedua otomatis
function aliasDomains(): string[] {
	return (env.MAIL_ALIAS_DOMAINS || '')
		.split(',')
		.map((s) => s.trim())
		.filter(Boolean);
}
/** Semua alamat milik user: [primary@portalsi.com, local@aliasdomain, ...] */
function addressesFor(email: string): string[] {
	if (!email || !email.includes('@')) return email ? [email] : [];
	const local = email.split('@')[0];
	return [email, ...aliasDomains().map((d) => `${local}@${d}`)];
}

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
		addresses: addressesFor(account?.email || ''),
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
		// alamat pengirim yang dipilih (validasi hanya alamat milik user)
		const requestedFrom = String(f.get('from_addr') ?? '').trim().toLowerCase();
		const allowed = addressesFor(creds.email);
		const fromAddr = allowed.includes(requestedFrom) ? requestedFrom : undefined;

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
				{ to, cc, bcc, subject, text, html, inReplyTo, references, attachments, fromName, fromAddr },
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

	unarchive: async ({ request, locals }) => {
		const creds = await credsFrom(locals);
		const f = await request.formData();
		const uid = Number(f.get('uid'));
		const folderPath = String(f.get('folder_path') ?? 'Archive');
		try {
			await unarchiveMessage(creds, folderPath, uid);
		} catch {
			/* ignore */
		}
		return { ok: true };
	},

	emptyTrash: async ({ locals }) => {
		const creds = await credsFrom(locals);
		const folders = await listFolders(creds);
		const trashPath = folders.find((f) => f.key === 'trash')?.path || 'Trash';
		try {
			await emptyTrash(creds, trashPath);
		} catch {
			/* ignore */
		}
		return { ok: true };
	},

	resetPassword: async ({ locals }) => {
		if (!locals.user) throw redirect(302, '/login');
		const email = (locals.user.email || '').trim();
		if (!email) return fail(422, { pwError: 'Akunmu belum punya email pemulihan untuk menerima tautan.' });

		const key = String(locals.user.user_id ?? email);
		const today = new Date().toISOString().slice(0, 10);
		const rec = resetCounts.get(key);
		const n = rec && rec.date === today ? rec.count : 0;
		if (n >= 3) return fail(429, { pwError: 'Batas 3 permintaan per hari tercapai. Coba lagi besok.' });

		try {
			const r = await forgotPassword(email);
			resetCounts.set(key, { date: today, count: n + 1 });
			return { pwSent: true, pwMessage: r.message, pwLeft: 3 - (n + 1) };
		} catch (e: any) {
			return fail(e?.status && e.status < 500 ? 422 : 502, { pwError: e?.message || 'Gagal mengirim tautan.' });
		}
	},

	updateProfile: async ({ request, locals }) => {
		if (!locals.token) throw redirect(302, '/login');
		const f = await request.formData();
		const full_name = String(f.get('full_name') ?? '').trim();
		const username = String(f.get('username') ?? '').trim();
		if (!full_name || !username) return fail(422, { profileError: 'Nama dan username wajib diisi.' });
		try {
			const r = await updateProfile(locals.token, { full_name, username });
			return { profileOk: true, profileMessage: r.message || 'Profil diperbarui.' };
		} catch (e: any) {
			return fail(e?.status && e.status < 500 ? 422 : 502, {
				profileError: e?.message || 'Gagal memperbarui profil.'
			});
		}
	},

	uploadPhoto: async ({ request, locals }) => {
		if (!locals.token) throw redirect(302, '/login');
		const f = await request.formData();
		const photo = f.get('photo');
		if (!(photo instanceof File) || photo.size === 0)
			return fail(422, { photoError: 'Pilih file gambar dulu.' });
		if (photo.size > 2 * 1024 * 1024)
			return fail(422, { photoError: 'Ukuran foto maksimal 2MB.' });
		try {
			const r = await uploadPhoto(locals.token, photo);
			return { photoOk: true, profileMessage: r.message || 'Foto profil diperbarui.' };
		} catch (e: any) {
			return fail(e?.status && e.status < 500 ? 422 : 502, {
				photoError: e?.message || 'Gagal mengunggah foto.'
			});
		}
	},

	changeEmail: async ({ request, locals }) => {
		if (!locals.token) throw redirect(302, '/login');
		const f = await request.formData();
		const email = String(f.get('email') ?? '').trim();
		if (!email) return fail(422, { emailError: 'Isi email baru dulu.' });
		try {
			const r = await requestEmailChange(locals.token, email);
			return { emailReqOk: true, emailMessage: r.message };
		} catch (e: any) {
			return fail(e?.status && e.status < 500 ? 422 : 502, {
				emailError: e?.message || 'Gagal mengirim tautan konfirmasi.'
			});
		}
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
