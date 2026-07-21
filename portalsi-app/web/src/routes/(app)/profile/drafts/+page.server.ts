import { env } from '$env/dynamic/public';
import { backendRequest } from '$lib/server/api';
import { normalizeMediaUrl } from '$lib/utils/media';
import { draftsResponseSchema } from '$lib/schemas/profile';
import { error } from '@sveltejs/kit';
import type { PageServerLoad } from './$types';

export const load: PageServerLoad = async ({ locals }) => {
	// Draft bersifat pribadi: endpointnya sendiri sudah mengunci ke user yang login,
	// dan halaman ini pun tidak menerima parameter user apa pun.
	if (!locals.token || !locals.user) error(401, 'Sesi Anda tidak tersedia. Silakan masuk kembali.');

	const mediaBaseUrl = env.PUBLIC_MEDIA_BASE_URL?.trim() || 'https://api.portalsi.com/storage';

	const response = await backendRequest('posts/drafts?per_page=24', {
		token: locals.token,
		requestId: locals.requestId,
		schema: draftsResponseSchema
	}).catch(() => null);

	return {
		drafts: (response?.data ?? []).map((draft) => ({
			id: draft.post_id,
			caption: draft.caption?.trim() || 'Draft tanpa caption',
			mediaUrl: normalizeMediaUrl(draft.media_url, mediaBaseUrl) || '/assets/logo.png',
			thumbnailUrl: normalizeMediaUrl(draft.thumbnail_url, mediaBaseUrl),
			isVideo: draft.is_video,
			isMultiple: draft.is_multiple
		})),
		// Gagal memuat dibedakan dari "memang belum ada draft" supaya pesannya tidak menyesatkan.
		failed: response === null
	};
};
