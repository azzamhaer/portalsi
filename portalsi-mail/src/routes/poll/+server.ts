import { json, redirect } from '@sveltejs/kit';
import { mailCredentials, mailStatus } from '$lib/server/portal';
import { loadView } from '$lib/server/mailbox';
import type { RequestHandler } from './$types';

export const GET: RequestHandler = async ({ url, locals }) => {
	if (!locals.token) throw redirect(302, '/login');
	const status = await mailStatus(locals.token);
	if (!status.has_account) return json({ folders: [], messages: [], total: 0, page: 1, pages: 1 });

	const key = url.searchParams.get('folder') || 'inbox';
	const page = Number(url.searchParams.get('page') || '1') || 1;
	const q = url.searchParams.get('q') || '';

	const creds = await mailCredentials(locals.token);
	const view = await loadView(creds, { key, page, q });
	return json({
		folders: view.folders,
		messages: view.list?.messages ?? [],
		total: view.list?.total ?? 0,
		page: view.list?.page ?? page,
		pages: view.list?.pages ?? 1
	});
};
