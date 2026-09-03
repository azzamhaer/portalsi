import type { LayoutServerLoad } from './$types';

export const load: LayoutServerLoad = async ({ locals, cookies }) => {
	const c = cookies.get('sim_lang');
	const lang = c === 'en' || c === 'id' ? c : 'id';
	return { user: locals.user, lang };
};
