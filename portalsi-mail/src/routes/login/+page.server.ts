import { fail, redirect } from '@sveltejs/kit';
import { login, PORTAL_TOKEN_COOKIE } from '$lib/server/portal';
import type { Actions, PageServerLoad } from './$types';

export const load: PageServerLoad = async ({ locals }) => {
	if (locals.user) throw redirect(302, '/');
	return {};
};

export const actions: Actions = {
	default: async ({ request, cookies }) => {
		const form = await request.formData();
		const loginId = String(form.get('login') ?? '').trim();
		const password = String(form.get('password') ?? '');

		if (!loginId || !password) {
			return fail(422, { message: 'Isi email/username dan password.', login: loginId });
		}

		let token: string;
		try {
			({ token } = await login(loginId, password));
		} catch (e: any) {
			const status = typeof e?.status === 'number' && e.status < 500 ? 422 : 502;
			return fail(status, { message: e?.message || 'Login gagal.', login: loginId });
		}

		cookies.set(PORTAL_TOKEN_COOKIE, token, {
			path: '/',
			httpOnly: true,
			secure: true,
			sameSite: 'lax',
			maxAge: 60 * 60 * 24 * 30
		});

		throw redirect(303, '/');
	}
};
