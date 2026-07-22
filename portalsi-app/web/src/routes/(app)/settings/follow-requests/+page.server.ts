import { env } from '$env/dynamic/public';
import { ApiError } from '$lib/api/errors';
import { pendingFollowersResponseSchema } from '$lib/schemas/profile';
import { backendRequest } from '$lib/server/api';
import { normalizeMediaUrl } from '$lib/utils/media';
import { error } from '@sveltejs/kit';
import type { PageServerLoad } from './$types';
export const load: PageServerLoad = async ({ locals }) => {
	if (!locals.token) error(401, 'Sesi tidak tersedia.');
	const mediaBaseUrl = env.PUBLIC_MEDIA_BASE_URL?.trim() || 'https://api.portalsi.com/storage';
	try {
		const response = await backendRequest('followers/pending', {
			token: locals.token,
			requestId: locals.requestId,
			schema: pendingFollowersResponseSchema
		});
		return {
			isPrivate: true,
			requests: response.pending_requests.map((user) => ({
				id: user.user_id,
				username: user.username,
				fullName: user.full_name?.trim() || user.username,
				avatarUrl: normalizeMediaUrl(user.profile_picture_thumb_url ?? user.profile_picture_url, mediaBaseUrl),
				hasStory: Boolean(user.has_story)
			}))
		};
	} catch (cause) {
		if (cause instanceof ApiError && cause.status === 403)
			return { isPrivate: false, requests: [] };
		throw cause;
	}
};
