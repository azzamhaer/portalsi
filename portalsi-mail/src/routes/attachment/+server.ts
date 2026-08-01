import { error, redirect } from '@sveltejs/kit';
import { mailCredentials } from '$lib/server/portal';
import { getAttachment } from '$lib/server/mailbox';
import type { RequestHandler } from './$types';

export const GET: RequestHandler = async ({ url, locals }) => {
	if (!locals.token) throw redirect(302, '/login');

	const folder = url.searchParams.get('folder') || 'INBOX';
	const uid = Number(url.searchParams.get('uid'));
	const index = Number(url.searchParams.get('i'));
	const disposition = url.searchParams.get('view') === '1' ? 'inline' : 'attachment';

	if (!uid || Number.isNaN(index)) throw error(400, 'Parameter tidak lengkap.');

	const creds = await mailCredentials(locals.token);
	const att = await getAttachment(creds, folder, uid, index).catch(() => null);
	if (!att) throw error(404, 'Lampiran tidak ditemukan.');

	const safeName = att.filename.replace(/["\\\r\n]/g, '_');
	return new Response(att.content as unknown as BodyInit, {
		headers: {
			'Content-Type': att.contentType,
			'Content-Length': String(att.content.length),
			'Content-Disposition': `${disposition}; filename="${safeName}"`,
			'Cache-Control': 'private, max-age=0, no-store'
		}
	});
};
