import { json, redirect } from '@sveltejs/kit';
import { loadView } from '$lib/server/mailstore';
import type { RequestHandler } from './$types';

export const GET: RequestHandler = async ({ url, locals, platform }) => {
	const env = platform?.env;
	if (!locals.user || !env) throw redirect(302, '/login');

	const key = url.searchParams.get('folder') || 'inbox';
	const page = Number(url.searchParams.get('page') || '1') || 1;
	const q = url.searchParams.get('q') || '';

	const view = await loadView(env, locals.user.id, { key, page, q });
	return json({
		folders: view.folders,
		messages: view.list?.messages ?? [],
		total: view.list?.total ?? 0,
		page: view.list?.page ?? page,
		pages: view.list?.pages ?? 1
	});
};
