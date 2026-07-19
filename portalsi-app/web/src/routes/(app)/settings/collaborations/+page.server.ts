import { env } from '$env/dynamic/public';
import { collabPendingSchema } from '$lib/schemas/collab';
import { backendRequest } from '$lib/server/api';
import { normalizeMediaUrl } from '$lib/utils/media';
import { error } from '@sveltejs/kit';
import type { PageServerLoad } from './$types';

export const load: PageServerLoad = async ({ locals }) => {
	if (!locals.token) error(401, 'Sesi tidak tersedia.');
	const mediaBaseUrl = env.PUBLIC_MEDIA_BASE_URL?.trim() || 'https://api.portalsi.com/storage';
	const response = await backendRequest('collaborations/pending', {
		token: locals.token,
		requestId: locals.requestId,
		schema: collabPendingSchema
	}).catch(() => ({ data: [] }));
	return {
		invites: response.data.map((inv) => ({
			postId: inv.post_id,
			caption: inv.caption?.trim() || '',
			isVideo: inv.is_video,
			thumbnailUrl:
				normalizeMediaUrl(inv.thumbnail_url ?? inv.media_url, mediaBaseUrl) ?? '/assets/logo.png',
			inviter: {
				username: inv.inviter.username,
				fullName: inv.inviter.full_name?.trim() || inv.inviter.username,
				avatarUrl: normalizeMediaUrl(inv.inviter.profile_picture_url, mediaBaseUrl) ?? undefined
			}
		}))
	};
};
