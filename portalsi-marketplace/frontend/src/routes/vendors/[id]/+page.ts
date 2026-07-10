import { apiEndpoints } from '$lib/api';
import { error, redirect } from '@sveltejs/kit';
import type { PageLoad } from './$types';

export const load: PageLoad = async ({ params, fetch }) => {
  try {
    const r: any = await apiEndpoints.vendor(params.id, fetch);
    // Redirect ke URL canonical /{username}
    if (r?.vendor?.username) {
      throw redirect(301, '/' + r.vendor.username);
    }
    return r;
  } catch (e: any) {
    if (e?.status === 301 || e?.status === 302) throw e;
    throw error(404, 'Toko tidak ditemukan');
  }
};
