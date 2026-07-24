import { env } from '$env/dynamic/public';
import { mapCompactUser, mapPost, mapSessionToPortalUser } from '$lib/api/mappers';
import { commentsResponseSchema } from '$lib/schemas/comment';
import { ApiError } from '$lib/api/errors';
import { postLikesSchema, postSchema } from '$lib/schemas/post';
import { backendRequest } from '$lib/server/api';
import { normalizeMediaUrl } from '$lib/utils/media';
import { relativeTimeId } from '$lib/utils/time';
import { error, fail, redirect } from '@sveltejs/kit';
import { z } from 'zod';
import type { Actions, PageServerLoad } from './$types';

const ogSchema = z
	.object({
		found: z.boolean().optional(),
		username: z.string().nullish(),
		full_name: z.string().nullish(),
		caption: z.string().nullish(),
		image: z.string().nullish()
	})
	.passthrough();

function ogTitle(name: string) {
	return `${name} · Portal SI`;
}
function ogDescription(caption?: string | null) {
	const text = (caption ?? '').trim().replace(/\s+/g, ' ');
	return text ? text.slice(0, 180) : 'Buka postingan ini di Portal SI.';
}

export const load: PageServerLoad = async ({ locals, params }) => {
	const postId = Number.parseInt(params.postId, 10);
	if (!Number.isSafeInteger(postId) || postId < 1) error(404, 'Postingan tidak ditemukan.');
	const mediaBaseUrl0 = env.PUBLIC_MEDIA_BASE_URL?.trim() || 'https://api.portalsi.com/storage';

	// Belum login / crawler share link → sajikan meta Open Graph publik + ajakan masuk.
	if (!locals.token || !locals.user) {
		const og = await backendRequest(`posts/${postId}/og`, {
			requestId: locals.requestId,
			schema: ogSchema
		}).catch(() => null);
		const authorName = og?.full_name?.trim() || (og?.username ? `@${og.username}` : 'Portal SI');
		return {
			isPublic: true as const,
			postId,
			author: authorName,
			username: og?.username ?? null,
			og: {
				title: ogTitle(authorName),
				description: ogDescription(og?.caption),
				image: normalizeMediaUrl(og?.image, mediaBaseUrl0) ?? ''
			}
		};
	}

	let post, comments, likes;
	try {
		[post, comments, likes] = await Promise.all([
			backendRequest(`posts/${postId}`, {
				token: locals.token,
				requestId: locals.requestId,
				schema: postSchema
			}),
			backendRequest(`posts/${postId}/comments`, {
				token: locals.token,
				requestId: locals.requestId,
				schema: commentsResponseSchema
			}),
			// Daftar penyuka hanya pelengkap. Kalau gagal (data lama yang tak sesuai kontrak,
			// backend lambat), tampilkan postingannya saja daripada memberi 500 ke user.
			backendRequest(`posts/${postId}/likes`, {
				token: locals.token,
				requestId: locals.requestId,
				schema: postLikesSchema
			}).catch(() => [])
		]);
	} catch (cause) {
		// Akun privat & belum di-follow: jangan 500 — arahkan ke profil pemiliknya.
		if (cause instanceof ApiError && cause.status === 403) {
			const owner = cause.payload?.owner_username;
			if (typeof owner === 'string' && owner) redirect(307, `/u/${owner}`);
		}
		throw cause;
	}
	const mediaBaseUrl = env.PUBLIC_MEDIA_BASE_URL?.trim() || 'https://api.portalsi.com/storage';
	const mapComment = (comment: (typeof comments.comments)[number]['replies'][number]) => ({
		id: comment.comment_id,
		user: mapCompactUser(comment.user, mediaBaseUrl),
		text: comment.content,
		gifUrl: comment.gif_url ?? null,
		createdLabel: relativeTimeId(comment.created_at),
		likesCount: comment.likes_count,
		isLiked: comment.is_liked,
		parentId: comment.parent_comment_id
	});

	const mappedPost = mapPost(post, mediaBaseUrl);
	return {
		isPublic: false as const,
		og: {
			title: ogTitle(mappedPost.user.fullName || `@${mappedPost.user.username}`),
			description: ogDescription(mappedPost.caption),
			// Foto: pakai media asli (kualitas penuh utk share WhatsApp/SEO). Video: thumbnail.
			image: mappedPost.isVideo ? mappedPost.thumbnailUrl || mappedPost.mediaUrl : mappedPost.mediaUrl
		},
		post: mappedPost,
		likers: likes
			.filter((like): like is typeof like & { user: NonNullable<typeof like.user> } =>
				Boolean(like.user)
			)
			.map((like) => ({
				...mapCompactUser(like.user, mediaBaseUrl),
				isFollowing: like.is_following_status
			})),
		comments: comments.comments.map((comment) => ({
			...mapComment(comment),
			replies: comment.replies.map(mapComment)
		})),
		currentUser: {
			...mapSessionToPortalUser(locals.user),
			avatarUrl: normalizeMediaUrl(locals.user.avatarUrl, mediaBaseUrl) ?? undefined
		}
	};
};

export const actions: Actions = {
	update: async ({ request, locals, params }) => {
		if (!locals.token) return fail(401, { message: 'Sesi tidak tersedia.' });
		const postId = Number.parseInt(params.postId, 10);
		if (!Number.isSafeInteger(postId) || postId < 1)
			return fail(400, { message: 'Postingan tidak valid.' });
		const source = await request.formData();
		const caption = String(source.get('caption') ?? '').trim();
		const location = String(source.get('location') ?? '').trim();
		const thumbnailSecond = String(source.get('thumbnail_second') ?? '').trim();
		const body = new FormData();
		body.set('caption', caption);
		body.set('location', location);
		// Hanya kirim bila user memilih frame thumbnail baru (server yang ekstrak via ffmpeg).
		if (thumbnailSecond !== '') body.set('thumbnail_second', thumbnailSecond);
		try {
			await backendRequest(`posts/${postId}/update`, {
				method: 'POST',
				token: locals.token,
				requestId: locals.requestId,
				body
			});
			return { success: true, message: 'Postingan berhasil diperbarui.' };
		} catch (cause) {
			if (cause instanceof ApiError)
				return fail(cause.status, { message: cause.message, errors: cause.fieldErrors });
			throw cause;
		}
	},
	delete: async ({ locals, params }) => {
		if (!locals.token) return fail(401, { message: 'Sesi tidak tersedia.' });
		const postId = Number.parseInt(params.postId, 10);
		if (!Number.isSafeInteger(postId) || postId < 1)
			return fail(400, { message: 'Postingan tidak valid.' });
		try {
			await backendRequest(`posts/${postId}`, {
				method: 'DELETE',
				token: locals.token,
				requestId: locals.requestId
			});
		} catch (cause) {
			if (cause instanceof ApiError)
				return fail(cause.status, { message: cause.message, errors: cause.fieldErrors });
			throw cause;
		}
		redirect(303, '/profile');
	}
};
