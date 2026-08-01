import { error, json, redirect } from '@sveltejs/kit';
import { mailCredentials } from '$lib/server/portal';
import { loadMessage } from '$lib/server/mailbox';
import type { RequestHandler } from './$types';

export const GET: RequestHandler = async ({ url, locals }) => {
	if (!locals.token) throw redirect(302, '/login');
	const folder = url.searchParams.get('folder') || 'INBOX';
	const uid = Number(url.searchParams.get('uid'));
	if (!uid) throw error(400, 'uid wajib.');

	const creds = await mailCredentials(locals.token);
	const res = await loadMessage(creds, folder, uid);
	if (!res.message) throw error(404, 'Pesan tidak ditemukan.');
	return json(res);
};
