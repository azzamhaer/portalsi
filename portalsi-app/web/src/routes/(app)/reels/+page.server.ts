import { env } from '$env/dynamic/public';
import { mapPost } from '$lib/api/mappers';
import { reelsFeedSchema } from '$lib/schemas/post';
import { backendRequest } from '$lib/server/api';
import { error } from '@sveltejs/kit';
import type { PageServerLoad } from './$types';

export const load: PageServerLoad = async ({ locals }) => {
	if (!locals.token) error(401, 'Sesi tidak tersedia.');
	const response = await backendRequest('reels', {
		token: locals.token,
		requestId: locals.requestId,
		query: { count: '6' },
		schema: reelsFeedSchema
	});
	const media = env.PUBLIC_MEDIA_BASE_URL?.trim() || 'https://api.portalsi.com/storage';
	return {
		reels: response.data.map((post) => mapPost(post, media)),
		hasMore: response.has_more
	};
};
