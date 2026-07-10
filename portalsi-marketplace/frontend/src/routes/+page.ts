import { apiEndpoints } from '$lib/api';
import type { PageLoad } from './$types';

export const load: PageLoad = async ({ fetch }) => {
  // Streaming: home data dimuat asinkron, navigasi instant
  const homePromise = apiEndpoints.home(fetch).catch(() => ({
    tags: [], categories: [], flashSale: [], recommended: [], official: []
  }));
  return { streamed: { home: homePromise } };
};
