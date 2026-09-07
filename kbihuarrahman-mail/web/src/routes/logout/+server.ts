import { redirect } from '@sveltejs/kit';
import { destroySession, SESSION_COOKIE } from '$lib/server/auth';
import type { RequestHandler } from './$types';

export const POST: RequestHandler = async ({ cookies, locals, platform }) => {
	const env = platform?.env;
	if (env && locals.sessionId) await destroySession(env, locals.sessionId).catch(() => {});
	cookies.delete(SESSION_COOKIE, { path: '/' });
	throw redirect(303, '/login');
};
