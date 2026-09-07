import type { Handle } from '@sveltejs/kit';
import { getSessionUser, SESSION_COOKIE } from '$lib/server/auth';

export const handle: Handle = async ({ event, resolve }) => {
	event.locals.user = null;
	event.locals.sessionId = null;

	const env = event.platform?.env;
	const cookie = event.cookies.get(SESSION_COOKIE);
	if (env && cookie) {
		try {
			const sess = await getSessionUser(env, cookie);
			if (sess) {
				event.locals.user = sess.user;
				event.locals.sessionId = sess.sessionId;
			} else {
				event.cookies.delete(SESSION_COOKIE, { path: '/' });
			}
		} catch {
			/* abaikan; anggap belum login */
		}
	}

	return resolve(event);
};
