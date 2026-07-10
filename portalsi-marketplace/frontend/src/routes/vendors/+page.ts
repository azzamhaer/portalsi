import { apiEndpoints } from '$lib/api';
import type { PageLoad } from './$types';
export const load: PageLoad = async ({ url, fetch }) => {
  const f = url.searchParams.get('f') ?? 'all';
  const params = new URLSearchParams(url.searchParams);
  params.set('filter', f);
  try {
    const data: any = await apiEndpoints.vendors(params.toString(), fetch);
    return {
      vendors: data,
      filter: f,
      search: url.searchParams.get('search') ?? '',
      city: url.searchParams.get('city') ?? '',
      minRating: url.searchParams.get('min_rating') ?? '',
      official: url.searchParams.get('official') ?? '',
    };
  } catch {
    return { vendors: [], filter: f, search: '', city: '', minRating: '', official: '' };
  }
};
