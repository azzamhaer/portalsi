import { fail, redirect } from '@sveltejs/kit';
import {
	archiveMessage,
	emptyTrash,
	expungeMessage,
	loadView,
	moveToTrash,
	setSeen,
	setStar,
	storeLocal,
	rememberContacts,
	unarchiveMessage,
	type Contact
} from '$lib/server/mailstore';
import { parseAddressList, sendViaBrevo, type OutAttachment } from '$lib/server/brevo';
import type { Actions, PageServerLoad } from './$types';

function bytesToB64(bytes: Uint8Array): string {
	let bin = '';
	const chunk = 0x8000;
	for (let i = 0; i < bytes.length; i += chunk) {
		bin += String.fromCharCode(...bytes.subarray(i, i + chunk));
	}
	return btoa(bin);
}

function requireCtx(locals: App.Locals, platform: App.Platform | undefined) {
	const env = platform?.env;
	if (!locals.user || !env) throw redirect(302, '/login');
	return { env, user: locals.user };
}

export const load: PageServerLoad = async ({ locals, url, platform }) => {
	const { env, user } = requireCtx(locals, platform);

	const key = url.searchParams.get('folder') || 'inbox';
	const page = Number(url.searchParams.get('page') || '1') || 1;
	const q = url.searchParams.get('q') || '';
	const uidParam = url.searchParams.get('uid');
	const uid = uidParam ? Number(uidParam) : undefined;

	const view = await loadView(env, user.id, { key, page, q, uid });

	return {
		account: { email: user.email, local_part: user.local_part },
		user: {
			username: user.local_part,
			full_name: user.full_name,
			email: user.email,
			profile_picture_url: null,
			is_admin: user.is_admin
		},
		addresses: [user.email],
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

export const actions: Actions = {
	send: async ({ request, locals, platform }) => {
		const { env, user } = requireCtx(locals, platform);
		const f = await request.formData();

		const toRaw = String(f.get('to') ?? '').trim();
		const ccRaw = String(f.get('cc') ?? '').trim();
		const bccRaw = String(f.get('bcc') ?? '').trim();
		const subject = String(f.get('subject') ?? '').trim();
		const text = String(f.get('body') ?? '');
		const html = String(f.get('html') ?? '') || undefined;
		const inReplyTo = String(f.get('in_reply_to') ?? '') || undefined;
		const references = String(f.get('references') ?? '') || undefined;
		const fromName = String(f.get('from_name') ?? '').trim() || user.full_name || undefined;

		if (!toRaw) return fail(422, { sendError: 'Isi penerima (To) dulu.' });

		const to = parseAddressList(toRaw);
		const cc = parseAddressList(ccRaw);
		const bcc = parseAddressList(bccRaw);
		if (!to.length) return fail(422, { sendError: 'Alamat penerima tidak valid.' });

		// lampiran → base64 (Brevo) + bytes (simpan ke R2 pada salinan Terkirim)
		const files: { filename: string; contentType: string; bytes: Uint8Array; b64: string }[] = [];
		for (const item of f.getAll('files')) {
			if (item instanceof File && item.size > 0) {
				const bytes = new Uint8Array(await item.arrayBuffer());
				files.push({
					filename: item.name,
					contentType: item.type || 'application/octet-stream',
					bytes,
					b64: bytesToB64(bytes)
				});
			}
		}
		const outAtt: OutAttachment[] = files.map((a) => ({
			filename: a.filename,
			contentB64: a.b64,
			contentType: a.contentType
		}));

		try {
			await sendViaBrevo(env, {
				from: { email: user.email, name: fromName },
				to,
				cc,
				bcc,
				subject,
				html,
				text,
				inReplyTo,
				references,
				attachments: outAtt
			});
		} catch (e: any) {
			return fail(502, { sendError: e?.message || 'Gagal mengirim email.' });
		}

		// simpan salinan ke folder Terkirim (+ lampiran ke R2)
		try {
			const msgId = await storeLocal(env, user.id, 'sent', {
				to: to.map((a) => (a.name ? `${a.name} <${a.email}>` : a.email)).join(', '),
				cc: cc.map((a) => a.email).join(', '),
				subject,
				html,
				text,
				fromName,
				fromAddr: user.email,
				inReplyTo,
				references,
				seen: true,
				hasAttachments: files.length > 0
			});
			for (let i = 0; i < files.length; i++) {
				const a = files[i];
				const key = `sent/${user.id}/${msgId}/${i}-${a.filename}`;
				await env.MAILSTORE.put(key, a.bytes, { httpMetadata: { contentType: a.contentType } });
				await env.DB.prepare(
					'INSERT INTO attachments (message_id, idx, filename, content_type, size, inline, r2_key) VALUES (?, ?, ?, ?, ?, 0, ?)'
				)
					.bind(msgId, i, a.filename, a.contentType, a.bytes.length, key)
					.run();
			}
			// ingat kontak
			const contacts: Contact[] = [...to, ...cc].map((a) => ({ address: a.email, name: a.name || '' }));
			await rememberContacts(env, user.id, contacts);
		} catch {
			/* pengiriman sudah sukses; kegagalan menyimpan salinan diabaikan */
		}

		// hapus draf sumber bila ada
		const draftUid = Number(f.get('draft_uid') || 0);
		if (draftUid) await expungeMessage(env, user.id, draftUid).catch(() => {});

		return { sent: true };
	},

	saveDraft: async ({ request, locals, platform }) => {
		const { env, user } = requireCtx(locals, platform);
		const f = await request.formData();
		const to = String(f.get('to') ?? '').trim();
		const cc = String(f.get('cc') ?? '').trim();
		const subject = String(f.get('subject') ?? '').trim();
		const text = String(f.get('body') ?? '');
		const html = String(f.get('html') ?? '') || undefined;
		const fromName = String(f.get('from_name') ?? '').trim() || user.full_name || undefined;

		try {
			await storeLocal(env, user.id, 'drafts', {
				to,
				cc,
				subject,
				html,
				text,
				fromName,
				fromAddr: user.email,
				seen: true,
				draft: true
			});
			const draftUid = Number(f.get('draft_uid') || 0);
			if (draftUid) await expungeMessage(env, user.id, draftUid).catch(() => {});
		} catch (e: any) {
			return fail(502, { draftError: e?.message || 'Gagal menyimpan draf.' });
		}
		return { draftSaved: true };
	},

	star: async ({ request, locals, platform }) => {
		const { env, user } = requireCtx(locals, platform);
		const f = await request.formData();
		await setStar(env, user.id, Number(f.get('uid')), String(f.get('on')) === '1').catch(() => {});
		return { ok: true };
	},

	archive: async ({ request, locals, platform }) => {
		const { env, user } = requireCtx(locals, platform);
		const f = await request.formData();
		await archiveMessage(env, user.id, Number(f.get('uid'))).catch(() => {});
		return { ok: true };
	},

	trash: async ({ request, locals, platform }) => {
		const { env, user } = requireCtx(locals, platform);
		const f = await request.formData();
		await moveToTrash(env, user.id, Number(f.get('uid'))).catch(() => {});
		return { ok: true };
	},

	unarchive: async ({ request, locals, platform }) => {
		const { env, user } = requireCtx(locals, platform);
		const f = await request.formData();
		await unarchiveMessage(env, user.id, Number(f.get('uid'))).catch(() => {});
		return { ok: true };
	},

	emptyTrash: async ({ locals, platform }) => {
		const { env, user } = requireCtx(locals, platform);
		await emptyTrash(env, user.id).catch(() => {});
		return { ok: true };
	},

	toggleRead: async ({ request, locals, platform }) => {
		const { env, user } = requireCtx(locals, platform);
		const f = await request.formData();
		await setSeen(env, user.id, Number(f.get('uid')), String(f.get('seen')) === '1').catch(() => {});
		return { ok: true };
	},

	resetPassword: async ({ locals }) => {
		if (!locals.user) throw redirect(302, '/login');
		// Deployment mandiri: reset kata sandi dilakukan admin (belum ada email reset otomatis).
		return {
			pwSent: true,
			pwMessage:
				'Untuk keamanan, reset kata sandi mailbox dilakukan oleh admin KBIHU Ar-Rahman. Silakan hubungi admin.',
			pwLeft: 0
		};
	}
};
