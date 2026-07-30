import { env } from '$env/dynamic/public';
import { backendRequest } from '$lib/server/api';
import { normalizeMediaUrl } from '$lib/utils/media';
import { ApiError } from '$lib/api/errors';
import { fail } from '@sveltejs/kit';
import { z } from 'zod';
import type { Actions, PageServerLoad } from './$types';

const captchaSchema = z.object({ token: z.string(), question: z.string() });

async function freshCaptcha(requestId?: string) {
	return backendRequest('public/contact/captcha', { requestId, schema: captchaSchema }).catch(
		() => ({ token: '', question: '' })
	);
}

const landingSchema = z
	.object({
		stats: z
			.object({
				members: z.coerce.number().catch(0),
				posts: z.coerce.number().catch(0),
				products: z.coerce.number().catch(0)
			})
			.catch({ members: 0, posts: 0, products: 0 }),
		posts: z
			.array(
				z.object({
					id: z.coerce.number(),
					caption: z.string().nullish(),
					media_url: z.string().nullish(),
					thumbnail_url: z.string().nullish(),
					is_video: z.boolean().catch(false),
					user: z
						.object({
							username: z.string().nullish(),
							full_name: z.string().nullish(),
							avatar_url: z.string().nullish(),
							is_verified: z.boolean().catch(false)
						})
						.nullish()
				})
			)
			.catch([]),
		announcements: z
			.array(
				z.object({
					id: z.coerce.number(),
					title: z.string().nullish(),
					content: z.string().nullish(),
					image_url: z.string().nullish(),
					pinned: z.boolean().catch(false),
					created_at: z.string().nullish(),
					author: z
						.object({
							username: z.string().nullish(),
							full_name: z.string().nullish(),
							avatar_url: z.string().nullish(),
							is_verified: z.boolean().catch(false),
							role: z.string().nullish()
						})
						.nullish()
				})
			)
			.catch([]),
		products: z
			.array(
				z.object({
					id: z.coerce.number(),
					name: z.string().nullish(),
					slug: z.string().nullish(),
					price: z.coerce.number().catch(0),
					original_price: z.coerce.number().catch(0),
					image: z.string().nullish(),
					rating: z.coerce.number().catch(0)
				})
			)
			.catch([])
	})
	.passthrough();

export const load: PageServerLoad = async ({ locals }) => {
	const base = env.PUBLIC_MEDIA_BASE_URL?.trim() || 'https://api.portalsi.com/storage';
	const marketplaceBase =
		env.PUBLIC_MARKETPLACE_URL?.trim() || 'https://marketplace.portalsi.com';
	const appBase = env.PUBLIC_APP_URL?.trim() || 'https://app.portalsi.com';

	const [data, captcha] = await Promise.all([
		backendRequest('public/landing', {
			requestId: locals.requestId,
			schema: landingSchema
		}).catch(() => ({
			stats: { members: 0, posts: 0, products: 0 },
			posts: [],
			announcements: [],
			products: []
		})),
		freshCaptcha(locals.requestId)
	]);

	return {
		captcha,
		stats: data.stats,
		posts: data.posts.map((p) => ({
			id: p.id,
			caption: p.caption ?? '',
			imageUrl: normalizeMediaUrl(p.thumbnail_url ?? p.media_url, base) ?? '',
			isVideo: p.is_video,
			user: p.user
				? {
						username: p.user.username ?? '',
						fullName: p.user.full_name?.trim() || p.user.username || 'Portal SI',
						avatarUrl: normalizeMediaUrl(p.user.avatar_url, base) ?? '',
						verified: p.user.is_verified
					}
				: null
		})),
		announcements: data.announcements.map((a) => ({
			id: a.id,
			title: a.title ?? 'Pengumuman',
			content: a.content ?? '',
			imageUrl: normalizeMediaUrl(a.image_url, base) ?? '',
			pinned: a.pinned,
			author: a.author
				? {
						username: a.author.username ?? '',
						fullName: a.author.full_name?.trim() || a.author.username || 'Portal SI',
						avatarUrl: normalizeMediaUrl(a.author.avatar_url, base) ?? '',
						verified: a.author.is_verified,
						role: a.author.role ?? '',
						url: a.author.username ? `${appBase}/u/${a.author.username}` : appBase
					}
				: null
		})),
		products: data.products.map((p) => ({
			id: p.id,
			name: p.name ?? 'Produk',
			price: p.price,
			originalPrice: p.original_price,
			imageUrl: normalizeMediaUrl(p.image, base) ?? '',
			rating: p.rating,
			url: `${marketplaceBase}/products/${p.slug ?? p.id}`
		}))
	};
};

export const actions: Actions = {
	contact: async ({ request, locals }) => {
		const form = await request.formData();
		const payload = {
			name: String(form.get('name') ?? '').trim(),
			email: String(form.get('email') ?? '').trim(),
			phone: String(form.get('phone') ?? '').trim(),
			message: String(form.get('message') ?? '').trim(),
			captcha_token: String(form.get('captcha_token') ?? ''),
			captcha_answer: String(form.get('captcha_answer') ?? '').trim()
		};

		if (!payload.name || !payload.email || !payload.message) {
			return fail(422, {
				message: 'Nama, email, dan pesan wajib diisi.',
				captcha: await freshCaptcha(locals.requestId)
			});
		}

		try {
			const res = await backendRequest<{ message: string }>('public/contact', {
				method: 'POST',
				requestId: locals.requestId,
				// Teruskan IP klien yang andal (mekanisme sama dengan throttle register).
				headers: { 'X-Real-Client-Ip': locals.clientIp || '' },
				body: payload
			});
			return { success: true, message: res.message };
		} catch (cause) {
			const message =
				cause instanceof ApiError ? cause.message : 'Gagal mengirim. Coba lagi.';
			// Selalu beri captcha baru agar percobaan berikutnya valid.
			return fail(cause instanceof ApiError ? cause.status : 500, {
				message,
				captcha: await freshCaptcha(locals.requestId)
			});
		}
	}
};
