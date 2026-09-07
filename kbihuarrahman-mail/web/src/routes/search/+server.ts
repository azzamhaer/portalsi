import { json, redirect } from '@sveltejs/kit';
import { searchPreview } from '$lib/server/mailstore';
import type { RequestHandler } from './$types';

export const GET: RequestHandler = async ({ url, locals, platform }) => {
	const env = platform?.env;
	if (!locals.user || !env) throw redirect(302, '/login');
	const q = url.searchParams.get('q') || '';
	if (q.trim().length < 2) return json({ results: [] });
	const results = await searchPreview(env, locals.user.id, q, 12).catch(() => []);
	return json({ results });
};
