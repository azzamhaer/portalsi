import { apiEndpoints } from '$lib/api';
import type { PageLoad } from './$types';

export const load: PageLoad = async ({ fetch, url }) => {
  const params = new URLSearchParams(url.searchParams);
  if (!params.has('per_page')) params.set('per_page', '24');
  if (!params.has('page')) params.set('page', '1');

  // STREAMING: return promise, biar navigasi instant
  const productsPromise = apiEndpoints.products(params.toString(), fetch)
    .then((data: any) => ({
      products: data.data ?? [],
      meta: { current_page: data.current_page, last_page: data.last_page, total: data.total },
    }))
    .catch(() => ({ products: [], meta: null }));

  const filtersPromise = Promise.all([
    apiEndpoints.tags(fetch).catch(() => []),
    apiEndpoints.categories(fetch).catch(() => []),
  ]).then(([tags, categories]) => ({ tags, categories }));

  return {
    streamed: { result: productsPromise, filters: filtersPromise },
    tag: url.searchParams.get('tag') ?? '',
    search: url.searchParams.get('search') ?? '',
    sort: url.searchParams.get('sort') ?? 'popular',
  };
};
