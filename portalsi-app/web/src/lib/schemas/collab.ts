import { z } from 'zod';

const booleanish = z.union([z.boolean(), z.literal(0), z.literal(1)]).transform(Boolean);

export const collabPendingSchema = z.object({
	data: z.array(
		z.object({
			post_id: z.coerce.number().int().positive(),
			caption: z.string().nullish(),
			media_url: z.string().nullish(),
			thumbnail_url: z.string().nullish(),
			is_video: booleanish.catch(false),
			invited_at: z.string().nullish(),
			inviter: z.object({
				user_id: z.coerce.number().int().positive(),
				username: z.string().min(1),
				full_name: z.string().nullish(),
				profile_picture_url: z.string().nullish()
			})
		})
	)
});
