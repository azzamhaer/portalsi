import { redirect } from '@sveltejs/kit';
import { mailStatus } from '$lib/server/portal';
import type { PageServerLoad } from './$types';

export const load: PageServerLoad = async ({ locals }) => {
	if (!locals.user || !locals.token) throw redirect(302, '/login');

	const status = await mailStatus(locals.token);
	if (status.gate_enabled && !status.unlocked) throw redirect(302, '/gate');
	if (!status.has_account) throw redirect(302, '/setup');

	return { account: status.account, domain: status.domain };
};
