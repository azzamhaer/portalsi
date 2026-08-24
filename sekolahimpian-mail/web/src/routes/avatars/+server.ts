import { json, redirect } from '@sveltejs/kit';
import { mailAvatars } from '$lib/server/portal';
import type { RequestHandler } from './$types';

export const POST: RequestHandler = async ({ request, locals }) => {
	if (!locals.token) throw redirect(302, '/login');
	let emails: string[] = [];
	try {
		const body = await request.json();
		emails = Array.isArray(body?.emails) ? body.emails.map((e: unknown) => String(e)) : [];
	} catch {
		emails = [];
	}
	const avatars = await mailAvatars(locals.token, emails);
	return json({ avatars });
};
