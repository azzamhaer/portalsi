import { ApiError } from '$lib/api/errors';
import { backendRequest } from '$lib/server/api';
import { fail } from '@sveltejs/kit';
import type { Actions, PageServerLoad } from './$types';

export const load: PageServerLoad = ({ url }) => ({
	token: url.searchParams.get('token') || '',
	email: url.searchParams.get('email') || ''
});

export const actions: Actions = {
	default: async ({ request, locals }) => {
		const data = await request.formData();
		const token = String(data.get('token') ?? '');
		const email = String(data.get('email') ?? '').trim();
		const password = String(data.get('password') ?? '');
		const confirmation = String(data.get('password_confirmation') ?? '');
		if (!token || !email) return fail(422, { message: 'Token atau email reset tidak lengkap.', tokenError: true });
		if (password.length < 6) return fail(422, { message: 'Kata sandi minimal 6 karakter.' });
		if (password !== confirmation)
			return fail(422, { message: 'Konfirmasi kata sandi tidak cocok.' });
		try {
			await backendRequest('reset-password', {
				method: 'POST',
				requestId: locals.requestId,
				body: { token, email, password, password_confirmation: confirmation }
			});
		} catch (error) {
			if (error instanceof ApiError) {
				// Kesalahan token (kedaluwarsa/tidak valid) → tawarkan minta tautan baru.
				const msg = (error.message || '').toLowerCase();
				const tokenError =
					[400, 401, 403, 404, 422].includes(error.status) &&
					/token|tautan|kedaluwarsa|expire|invalid|tidak valid|habis/.test(msg);
				return fail(error.status, { message: error.message, tokenError });
			}
			throw error;
		}
		return { success: true };
	}
};
