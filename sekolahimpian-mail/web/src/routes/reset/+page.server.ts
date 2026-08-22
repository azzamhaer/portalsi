import { fail, redirect } from '@sveltejs/kit';
import { resetPassword } from '$lib/server/portal';
import type { Actions, PageServerLoad } from './$types';

export const load: PageServerLoad = async ({ locals, url }) => {
	if (locals.user) throw redirect(302, '/');
	const token = url.searchParams.get('token') || '';
	const email = url.searchParams.get('email') || '';
	return { token, email, valid: Boolean(token && email) };
};

export const actions: Actions = {
	default: async ({ request }) => {
		const f = await request.formData();
		const token = String(f.get('token') ?? '');
		const email = String(f.get('email') ?? '');
		const password = String(f.get('password') ?? '');
		const confirm = String(f.get('confirm') ?? '');

		if (!token || !email) return fail(422, { message: 'Tautan tidak lengkap. Minta tautan baru.' });
		if (password.length < 6) return fail(422, { message: 'Kata sandi minimal 6 karakter.' });
		if (password !== confirm) return fail(422, { message: 'Konfirmasi kata sandi belum sama.' });

		try {
			const r = await resetPassword(token, email, password);
			return { success: true, message: r.message };
		} catch (e: any) {
			const status = typeof e?.status === 'number' && e.status < 500 ? 422 : 502;
			return fail(status, { message: e?.message || 'Gagal mengganti kata sandi.' });
		}
	}
};
