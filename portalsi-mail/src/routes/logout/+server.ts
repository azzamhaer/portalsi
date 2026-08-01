import { redirect } from '@sveltejs/kit';
import { logout, PORTAL_TOKEN_COOKIE } from '$lib/server/portal';
import type { RequestHandler } from './$types';

export const POST: RequestHandler = async ({ cookies, locals }) => {
	if (locals.token) await logout(locals.token);
	cookies.delete(PORTAL_TOKEN_COOKIE, { path: '/' });
	throw redirect(303, '/login');
};
