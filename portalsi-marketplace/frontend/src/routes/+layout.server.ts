import { apiEndpoints } from '$lib/api';
import type { LayoutServerLoad } from './$types';

export const load: LayoutServerLoad = async ({ fetch }) => {
  try {
    const settings: any = await apiEndpoints.publicSettings(fetch);
    return { settings };
  } catch {
    return { settings: null };
  }
};
