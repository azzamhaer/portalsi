import { error, json, redirect } from '@sveltejs/kit';
import { getMessage, threadFor } from '$lib/server/mailstore';
import type { RequestHandler } from './$types';

export const GET: RequestHandler = async ({ url, locals, platform }) => {
	const env = platform?.env;
	if (!locals.user || !env) throw redirect(302, '/login');
	const uid = Number(url.searchParams.get('uid'));
	if (!uid) throw error(400, 'uid wajib.');

	const message = await getMessage(env, locals.user.id, uid);
	if (!message) throw error(404, 'Pesan tidak ditemukan.');
	const thread = await threadFor(env, locals.user.id, message.subject, message.uid).catch(() => []);
	return json({ message, thread });
};
