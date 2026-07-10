import { apiEndpoints } from '$lib/api';
import { error } from '@sveltejs/kit';
import type { PageLoad } from './$types';

export const load: PageLoad = async ({ params, fetch }) => {
  try {
    const data: any = await apiEndpoints.product(params.id, fetch);
    return data;
  } catch (e: any) {
    throw error(404, 'Produk tidak ditemukan');
  }
};
