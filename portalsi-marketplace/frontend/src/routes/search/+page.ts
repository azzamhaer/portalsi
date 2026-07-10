import { apiEndpoints } from '$lib/api';
import type { PageLoad } from './$types';

export const load: PageLoad = async ({ url, fetch }) => {
  const q = url.searchParams.get('q') ?? url.searchParams.get('search') ?? '';
  const sort = url.searchParams.get('sort') ?? 'popular';
  const params = new URLSearchParams(url.searchParams);
  params.delete('q');
  if (q) params.set('search', q);
  params.set('sort', sort);
  if (!params.has('per_page')) params.set('per_page', '48');
  const [data, tags, categories]: any[] = await Promise.all([
    q ? apiEndpoints.products(params.toString(), fetch) : Promise.resolve({ data: [], current_page: 1, last_page: 1, total: 0 }),
    apiEndpoints.tags(fetch).catch(() => []),
    apiEndpoints.categories(fetch).catch(() => []),
  ]);
  return {
    q,
    sort,
    products: data.data ?? [],
    meta: { current_page: data.current_page, last_page: data.last_page, total: data.total },
    tags,
    categories,
  };
};
