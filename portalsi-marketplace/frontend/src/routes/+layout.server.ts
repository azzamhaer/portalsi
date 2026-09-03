import { apiEndpoints } from '$lib/api';
import type { LayoutServerLoad } from './$types';

export const load: LayoutServerLoad = async ({ fetch, cookies }) => {
  const c = cookies.get('portalsi_lang');
  const lang = c === 'en' || c === 'id' ? c : 'id';
  try {
    const settings: any = await apiEndpoints.publicSettings(fetch);
    return { settings, lang };
  } catch {
    return { settings: null, lang };
  }
};
