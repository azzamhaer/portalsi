import { apiEndpoints, getToken } from '$lib/api';
import { redirect, error } from '@sveltejs/kit';
import type { PageLoad } from './$types';

export const load: PageLoad = async ({ params, fetch }) => {
  if (!getToken()) throw redirect(302, '/login?next=/orders/' + params.id);
  try { return { order: await apiEndpoints.order(params.id) as any }; }
  catch { throw error(404, 'Order tidak ditemukan'); }
};
