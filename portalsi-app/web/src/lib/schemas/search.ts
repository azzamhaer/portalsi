import { z } from 'zod';

const booleanish = z.union([z.boolean(), z.literal(0), z.literal(1)]).transform(Boolean);

export const searchResponseSchema = z.object({
	users: z.array(
		z.object({
			user_id: z.coerce.number().int().positive(),
			username: z.string().min(1),
			full_name: z.string().nullish(),
			is_verified: booleanish.catch(false),
			profile_picture_url: z.string().nullish(),
			role: z.enum(['student', 'parent', 'teacher', 'dev', 'other']).catch('other'),
			is_private: booleanish.catch(false)
		})
	),
	hashtags: z.array(
		z.object({
			tag: z.string().min(1),
			posts_count: z.coerce.number().int().nonnegative().catch(0)
		})
	),
	posts: z.array(
		z.object({
			post_id: z.coerce.number().int().positive(),
			caption: z.string().nullish(),
			media_url: z.string().nullish(),
			thumbnail_url: z.string().nullish(),
			is_video: booleanish.catch(false),
			is_multiple: booleanish.catch(false),
			user: z.object({
				user_id: z.coerce.number().int().positive(),
				username: z.string().min(1)
			})
		})
	)
});

export type SearchResponse = z.infer<typeof searchResponseSchema>;
