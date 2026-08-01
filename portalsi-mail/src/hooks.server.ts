import type { Handle } from '@sveltejs/kit';
import { getPortalUser, PORTAL_TOKEN_COOKIE } from '$lib/server/portal';

export const handle: Handle = async ({ event, resolve }) => {
	event.locals.user = null;
	event.locals.token = null;

	const token = event.cookies.get(PORTAL_TOKEN_COOKIE);
	if (token) {
		try {
			event.locals.user = await getPortalUser(token);
			event.locals.token = token;
		} catch {
			// Token invalid/kadaluarsa — bersihkan.
			event.cookies.delete(PORTAL_TOKEN_COOKIE, { path: '/' });
		}
	}

	return resolve(event);
};
