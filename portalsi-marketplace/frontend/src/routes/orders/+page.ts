import { apiEndpoints, getToken } from '$lib/api';
import { redirect } from '@sveltejs/kit';
import type { PageLoad } from './$types';

export const load: PageLoad = async ({ url, fetch }) => {
  if (!getToken()) throw redirect(302, '/login?next=/orders');
  const status = url.searchParams.get('st') ?? '';
  try {
    const data: any = await apiEndpoints.orders(status ? `status=${status}` : '');
    return { orders: data.data ?? [], status };
  } catch { return { orders: [], status }; }
};
