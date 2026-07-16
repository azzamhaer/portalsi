import { fail, redirect } from '@sveltejs/kit';
import { z } from 'zod';
import type { Actions, PageServerLoad } from './$types';
import { ApiError } from '$lib/api/errors';
import { backendRequest } from '$lib/server/api';

const banStatusSchema = z
	.object({
		is_banned: z.boolean(),
		ban_reason: z.string().nullish(),
		banned_at: z.string().nullish(),
		username: z.string().nullish()
	})
	.passthrough();

const appealsSchema = z
	.object({
		appeals: z.array(
			z
				.object({
					appeal_id: z.number(),
					message: z.string(),
					status: z.string(),
					admin_response: z.string().nullish(),
					created_at: z.string().nullish()
				})
				.passthrough()
		)
	})
	.passthrough();

export const load: PageServerLoad = async ({ locals }) => {
	if (!locals.token) redirect(303, '/login');

	let status;
	try {
		status = await backendRequest('me/ban-status', {
			token: locals.token,
			requestId: locals.requestId,
			schema: banStatusSchema
		});
	} catch (error) {
		if (error instanceof ApiError && error.status === 401) redirect(303, '/login');
		throw error;
	}

	// Akun tidak diblokir → tidak perlu halaman ini.
	if (!status.is_banned) redirect(303, '/home');

	let appeals: z.infer<typeof appealsSchema>['appeals'] = [];
	try {
		const res = await backendRequest('appeals/mine', {
			token: locals.token,
			requestId: locals.requestId,
			schema: appealsSchema
		});
		appeals = res.appeals;
	} catch {
		// biarkan kosong kalau gagal ambil daftar banding
	}

	return {
		banReason: status.ban_reason ?? null,
		username: status.username ?? null,
		appeals
	};
};

export const actions: Actions = {
	default: async ({ request, locals }) => {
		if (!locals.token) redirect(303, '/login');

		const formData = await request.formData();
		const message = String(formData.get('message') ?? '').trim();
		if (message.length < 10) {
			return fail(422, {
				message: 'Tuliskan alasan banding minimal 10 karakter.',
				values: { message }
			});
		}

		try {
			await backendRequest('appeals', {
				method: 'POST',
				token: locals.token,
				body: { message },
				requestId: locals.requestId
			});
		} catch (error) {
			if (error instanceof ApiError) {
				return fail(error.status, { message: error.message, values: { message } });
			}
			throw error;
		}

		return { success: true };
	}
};
