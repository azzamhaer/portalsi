/// <reference types="@cloudflare/workers-types" />
import type { SessionUser } from '$lib/server/auth';

declare global {
	namespace App {
		interface Locals {
			user: SessionUser | null;
			/** id sesi mentah (untuk logout) */
			sessionId: string | null;
		}
		interface Platform {
			env: {
				DB: D1Database;
				MAILSTORE: R2Bucket;
				MAIL_DOMAIN: string;
				APP_NAME: string;
				APP_ORIGIN: string;
				BREVO_SENDER_NAME: string;
				BREVO_API_KEY: string;
				SESSION_SECRET: string;
			};
			cf: IncomingRequestCfProperties;
			ctx: ExecutionContext;
		}
	}
}

export {};
