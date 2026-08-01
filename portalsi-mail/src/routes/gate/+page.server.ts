import { fail, redirect } from '@sveltejs/kit';
import { mailStatus, mailUnlock } from '$lib/server/portal';
import type { Actions, PageServerLoad } from './$types';

export const load: PageServerLoad = async ({ locals }) => {
	if (!locals.user || !locals.token) throw redirect(302, '/login');
	const status = await mailStatus(locals.token);
	if (!status.gate_enabled || status.unlocked) throw redirect(302, '/');
	return {};
};

export const actions: Actions = {
	default: async ({ request, locals }) => {
		if (!locals.token) throw redirect(302, '/login');
		const f = await request.formData();
		const master = String(f.get('master_password') ?? '');
		if (!master) return fail(422, { message: 'Masukkan master password.' });

		try {
			await mailUnlock(locals.token, master);
		} catch (e: any) {
			const msg = e?.data?.errors?.master_password?.[0] || e?.message || 'Master password salah.';
			const status = typeof e?.status === 'number' && e.status < 500 ? 422 : 502;
			return fail(status, { message: msg });
		}

		throw redirect(303, '/');
	}
};
