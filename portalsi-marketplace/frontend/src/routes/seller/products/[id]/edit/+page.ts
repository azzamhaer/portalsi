import { apiEndpoints } from '$lib/api';
import type { PageLoad } from './$types';
export const load: PageLoad = async ({ params, fetch }) => {
  const data: any = await apiEndpoints.product(params.id, fetch);
  return { product: data.product };
};
