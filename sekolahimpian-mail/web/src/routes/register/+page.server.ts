import { fail, redirect } from '@sveltejs/kit';
import { register } from '$lib/server/portal';
import type { Actions, PageServerLoad } from './$types';

export const load: PageServerLoad = async ({ locals }) => {
	if (locals.user) throw redirect(302, '/');
	return {};
};

export const actions: Actions = {
	default: async ({ request }) => {
		const f = await request.formData();
		const input = {
			username: String(f.get('username') ?? '').trim(),
			full_name: String(f.get('full_name') ?? '').trim(),
			email: String(f.get('email') ?? '').trim(),
			password: String(f.get('password') ?? '')
		};

		if (!input.username || !input.full_name || !input.email || !input.password) {
			return fail(422, { message: 'Lengkapi semua kolom.', values: input });
		}

		try {
			const r = await register(input);
			return {
				success: true,
				message:
					r.message || 'Pendaftaran berhasil. Cek email untuk verifikasi sebelum bisa masuk.'
			};
		} catch (e: any) {
			const status = typeof e?.status === 'number' && e.status < 500 ? 422 : 502;
			return fail(status, { message: e?.message || 'Pendaftaran gagal.', values: input });
		}
	}
};
