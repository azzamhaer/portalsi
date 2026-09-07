import { json, redirect } from '@sveltejs/kit';
import type { RequestHandler } from './$types';

// Tidak ada layanan avatar eksternal di deployment mandiri ini.
// UI menampilkan inisial sebagai fallback bila peta avatar kosong.
export const POST: RequestHandler = async ({ locals }) => {
	if (!locals.user) throw redirect(302, '/login');
	return json({ avatars: {} });
};
