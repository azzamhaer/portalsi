import { json, redirect } from '@sveltejs/kit';
import { recentContacts } from '$lib/server/mailstore';
import type { RequestHandler } from './$types';

export const GET: RequestHandler = async ({ locals, platform }) => {
	const env = platform?.env;
	if (!locals.user || !env) throw redirect(302, '/login');
	try {
		const contacts = await recentContacts(env, locals.user.id);
		return json({ contacts });
	} catch {
		return json({ contacts: [] });
	}
};
