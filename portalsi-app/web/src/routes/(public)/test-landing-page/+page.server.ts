import { env } from '$env/dynamic/public';
import { backendRequest } from '$lib/server/api';
import { normalizeMediaUrl } from '$lib/utils/media';
import { z } from 'zod';
import type { PageServerLoad } from './$types';

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
					excerpt: z.string().nullish(),
					image_url: z.string().nullish(),
					pinned: z.boolean().catch(false),
					created_at: z.string().nullish(),
					author: z.string().nullish()
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

	const data = await backendRequest('public/landing', {
		requestId: locals.requestId,
		schema: landingSchema
	}).catch(() => ({
		stats: { members: 0, posts: 0, products: 0 },
		posts: [],
		announcements: [],
		products: []
	}));

	return {
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
			excerpt: a.excerpt ?? '',
			imageUrl: normalizeMediaUrl(a.image_url, base) ?? '',
			pinned: a.pinned,
			author: a.author ?? 'Portal SI'
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
