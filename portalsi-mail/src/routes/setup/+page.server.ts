import { fail, redirect } from '@sveltejs/kit';
import { env } from '$env/dynamic/private';
import { mailCreateAccount, mailStatus } from '$lib/server/portal';
import type { Actions, PageServerLoad } from './$types';

export const load: PageServerLoad = async ({ locals }) => {
	if (!locals.user || !locals.token) throw redirect(302, '/login');
	const status = await mailStatus(locals.token);
	if (status.gate_enabled && !status.unlocked) throw redirect(302, '/gate');
	if (status.has_account) throw redirect(302, '/');
	const aliasDomains = (env.MAIL_ALIAS_DOMAINS || '')
		.split(',')
		.map((s) => s.trim())
		.filter(Boolean);
	return { domain: status.domain, aliasDomains };
};

export const actions: Actions = {
	default: async ({ request, locals }) => {
		if (!locals.token) throw redirect(302, '/login');
		const f = await request.formData();
		const local = String(f.get('local_part') ?? '')
			.trim()
			.toLowerCase();

		if (!local) return fail(422, { message: 'Isi nama email dulu.', local });

		try {
			await mailCreateAccount(locals.token, local);
		} catch (e: any) {
			const msg = e?.data?.errors?.local_part?.[0] || e?.message || 'Gagal membuat email.';
			const status = typeof e?.status === 'number' && e.status < 500 ? 422 : 502;
			return fail(status, { message: msg, local });
		}

		throw redirect(303, '/');
	}
};
