import { json, redirect } from '@sveltejs/kit';
import { mailCredentials } from '$lib/server/portal';
import { searchPreview } from '$lib/server/mailbox';
import type { RequestHandler } from './$types';

export const GET: RequestHandler = async ({ url, locals }) => {
	if (!locals.token) throw redirect(302, '/login');
	const folder = url.searchParams.get('folder') || 'INBOX';
	const q = url.searchParams.get('q') || '';
	if (q.trim().length < 2) return json({ results: [] });
	const creds = await mailCredentials(locals.token);
	const results = await searchPreview(creds, folder, q, 12).catch(() => []);
	return json({ results });
};
