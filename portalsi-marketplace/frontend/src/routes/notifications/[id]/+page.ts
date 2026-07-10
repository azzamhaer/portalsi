import { apiEndpoints, getToken } from '$lib/api';
import { error, redirect } from '@sveltejs/kit';
import type { PageLoad } from './$types';

export const load: PageLoad = async ({ params }) => {
  if (!getToken()) throw redirect(302, '/login?next=/notifications/' + params.id);
  try {
    return { notification: await apiEndpoints.notification(params.id) as any };
  } catch {
    throw error(404, 'Notifikasi tidak ditemukan');
  }
};
