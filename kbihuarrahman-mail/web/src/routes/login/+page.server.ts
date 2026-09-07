import { fail, redirect } from '@sveltejs/kit';
import { createSession, findUserByEmail, verifyPassword, SESSION_COOKIE, countUsers } from '$lib/server/auth';
import type { Actions, PageServerLoad } from './$types';

export const load: PageServerLoad = async ({ locals, platform }) => {
	if (locals.user) throw redirect(302, '/');
	// Jika belum ada satupun akun, arahkan ke setup admin pertama.
	const env = platform?.env;
	if (env && (await countUsers(env)) === 0) throw redirect(302, '/setup');
	return {};
};

export const actions: Actions = {
	default: async ({ request, cookies, platform }) => {
		const env = platform?.env;
		if (!env) return fail(500, { message: 'Server belum siap (binding tidak tersedia).', login: '' });

		const form = await request.formData();
		const loginId = String(form.get('login') ?? '').trim().toLowerCase();
		const password = String(form.get('password') ?? '');
		if (!loginId || !password) {
			return fail(422, { message: 'Isi email/username dan password.', login: loginId });
		}

		const email = loginId.includes('@') ? loginId : `${loginId}@${env.MAIL_DOMAIN}`;
		const user = await findUserByEmail(env, email);
		if (!user || !user.is_active || !(await verifyPassword(password, user.password_hash))) {
			return fail(422, { message: 'Email atau kata sandi salah.', login: loginId });
		}

		const cookie = await createSession(env, user.id, request.headers.get('user-agent'));
		cookies.set(SESSION_COOKIE, cookie, {
			path: '/',
			httpOnly: true,
			secure: true,
			sameSite: 'lax',
			maxAge: 60 * 60 * 24 * 30
		});
		throw redirect(303, '/');
	}
};
