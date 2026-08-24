import { json, redirect } from '@sveltejs/kit';
import { mailCredentials } from '$lib/server/portal';
import { listFolders, recentContacts } from '$lib/server/mailbox';
import type { RequestHandler } from './$types';

export const GET: RequestHandler = async ({ locals }) => {
	if (!locals.token) throw redirect(302, '/login');
	const creds = await mailCredentials(locals.token);
	const folders = await listFolders(creds);
	const sentPath = folders.find((f) => f.key === 'sent')?.path;
	const inboxPath = folders.find((f) => f.key === 'inbox')?.path || 'INBOX';
	try {
		const contacts = await recentContacts(creds, sentPath, inboxPath);
		return json({ contacts });
	} catch {
		return json({ contacts: [] });
	}
};
