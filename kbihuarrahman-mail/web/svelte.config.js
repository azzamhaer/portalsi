import adapter from '@sveltejs/adapter-cloudflare';
import { vitePreprocess } from '@sveltejs/vite-plugin-svelte';

/** @type {import('@sveltejs/kit').Config} */
const config = {
	preprocess: vitePreprocess(),
	kit: {
		// Deploy sebagai Cloudflare Worker (dengan static assets). D1 & R2 tersedia
		// di server routes lewat `event.platform.env`.
		adapter: adapter()
	}
};

export default config;
