import { error, redirect } from '@sveltejs/kit';
import { getAttachment } from '$lib/server/mailstore';
import type { RequestHandler } from './$types';

export const GET: RequestHandler = async ({ url, locals, platform }) => {
	const env = platform?.env;
	if (!locals.user || !env) throw redirect(302, '/login');

	const uid = Number(url.searchParams.get('uid'));
	const index = Number(url.searchParams.get('i'));
	const disposition = url.searchParams.get('view') === '1' ? 'inline' : 'attachment';
	if (!uid || Number.isNaN(index)) throw error(400, 'Parameter tidak lengkap.');

	const att = await getAttachment(env, locals.user.id, uid, index).catch(() => null);
	if (!att) throw error(404, 'Lampiran tidak ditemukan.');

	const safeName = att.filename.replace(/["\\\r\n]/g, '_');
	return new Response(att.body, {
		headers: {
			'Content-Type': att.contentType,
			'Content-Disposition': `${disposition}; filename="${safeName}"`,
			'Cache-Control': 'private, max-age=0, no-store'
		}
	});
};
