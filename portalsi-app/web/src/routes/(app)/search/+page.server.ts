import { redirect } from '@sveltejs/kit';
import type { PageServerLoad } from './$types';

export const load: PageServerLoad = ({ locals, url }) => {
	if (!locals.token) redirect(303, '/login');
	const tab = url.searchParams.get('tab') ?? 'all';
	return {
		initialQuery: (url.searchParams.get('q') ?? '').slice(0, 80),
		initialTab: ['all', 'users', 'tags', 'posts'].includes(tab) ? tab : 'all'
	};
};
