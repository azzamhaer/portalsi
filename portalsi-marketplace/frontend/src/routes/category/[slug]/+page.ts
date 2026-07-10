import { apiEndpoints } from '$lib/api';
import type { PageLoad } from './$types';

export const load: PageLoad = async ({ params, fetch, url }) => {
  const sort = url.searchParams.get('sort') || 'popular';
  const cats: any[] = await apiEndpoints.categories(fetch);
  const flat = cats.flatMap((c) => [c, ...(c.children ?? [])]);
  const cat = flat.find(c => c.slug === params.slug);
  const key = cat?.tag_slug ? `tag=${encodeURIComponent(cat.tag_slug)}` : `category=${params.slug}`;
  const q = `${key}&sort=${sort}&per_page=48`;
  const data: any = await apiEndpoints.products(q, fetch);
  return { category: cat, products: data.data ?? [], allCats: cats, sort };
};
