import { apiEndpoints } from '$lib/api';
import type { PageLoad } from './$types';

export const load: PageLoad = async ({ fetch }) => {
  try {
    const methods = await apiEndpoints.paymentMethods(fetch);
    return { methods };
  } catch {
    return { methods: [] };
  }
};
