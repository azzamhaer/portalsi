import type { PortalUser } from '$lib/server/portal';

declare global {
	namespace App {
		interface Locals {
			user: PortalUser | null;
			token: string | null;
		}
		// interface Error {}
		// interface PageData {}
		// interface Platform {}
	}
}

export {};
