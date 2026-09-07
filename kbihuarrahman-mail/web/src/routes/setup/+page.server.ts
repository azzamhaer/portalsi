import { fail, redirect } from '@sveltejs/kit';
import { countUsers, createUser, createSession, SESSION_COOKIE } from '$lib/server/auth';
import type { Actions, PageServerLoad } from './$types';

// Bootstrap: buat akun ADMIN pertama. Hanya bisa saat tabel users masih kosong.
export const load: PageServerLoad = async ({ locals, platform }) => {
	const env = platform?.env;
	if (!env) return { domain: 'kbihuarrahman.com', ready: false };
	if ((await countUsers(env)) > 0) throw redirect(302, locals.user ? '/' : '/login');
	return { domain: env.MAIL_DOMAIN, ready: true };
};

export const actions: Actions = {
	default: async ({ request, cookies, platform }) => {
		const env = platform?.env;
		if (!env) return fail(500, { message: 'Server belum siap.' });
		if ((await countUsers(env)) > 0) return fail(409, { message: 'Admin sudah ada. Silakan login.' });

		const f = await request.formData();
		const local = String(f.get('local_part') ?? '').trim().toLowerCase();
		const fullName = String(f.get('full_name') ?? '').trim();
		const password = String(f.get('password') ?? '');

		try {
			const id = await createUser(env, {
				localPart: local,
				password,
				fullName,
				isAdmin: true
			});
			const cookie = await createSession(env, id, request.headers.get('user-agent'));
			cookies.set(SESSION_COOKIE, cookie, {
				path: '/',
				httpOnly: true,
				secure: true,
				sameSite: 'lax',
				maxAge: 60 * 60 * 24 * 30
			});
		} catch (e: any) {
			return fail(e?.status || 422, { message: e?.message || 'Gagal membuat admin.', local, fullName });
		}
		throw redirect(303, '/');
	}
};
