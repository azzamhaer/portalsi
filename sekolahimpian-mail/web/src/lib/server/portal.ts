import { env } from '$env/dynamic/private';

export const PORTAL_TOKEN_COOKIE = 'sim_mail_token';

export interface PortalUser {
	user_id: number;
	username: string;
	full_name?: string | null;
	email?: string | null;
	is_verified?: boolean | number;
	profile_picture_url?: string | null;
}

export interface MailAccountInfo {
	email: string;
	local_part: string;
	created_at: string;
}

export interface MailStatus {
	gate_enabled: boolean;
	unlocked: boolean;
	has_account: boolean;
	account: MailAccountInfo | null;
	domain: string;
}

export class PortalError extends Error {
	constructor(
		message: string,
		public status = 400,
		public data: unknown = null
	) {
		super(message);
	}
}

const apiBase = () => (env.PORTALSI_API_URL || 'https://api.portalsi.com/api').replace(/\/+$/, '');
const timeoutMs = () => Number(env.PORTALSI_API_TIMEOUT_MS || 12000);

async function req(path: string, init: RequestInit = {}, token?: string): Promise<any> {
	const controller = new AbortController();
	const timer = setTimeout(() => controller.abort(), timeoutMs());
	try {
		const res = await fetch(`${apiBase()}${path}`, {
			...init,
			signal: controller.signal,
			cache: 'no-store',
			headers: {
				Accept: 'application/json',
				'Content-Type': 'application/json',
				...(token ? { Authorization: `Bearer ${token}` } : {}),
				...(init.headers || {})
			}
		});
		const text = await res.text();
		let data: any = {};
		try {
			data = text ? JSON.parse(text) : {};
		} catch {
			data = { message: text };
		}
		if (!res.ok) {
			throw new PortalError(data.message || data.error || 'Permintaan gagal.', res.status, data);
		}
		return data;
	} catch (e: any) {
		if (e instanceof PortalError) throw e;
		throw new PortalError('Server tidak dapat dihubungi. Coba lagi beberapa saat.', 503);
	} finally {
		clearTimeout(timer);
	}
}

// ── Auth (SSO) ──
export async function login(login: string, password: string) {
	const d = await req('/login', { method: 'POST', body: JSON.stringify({ login, password }) });
	if (!d.token || !d.user) throw new PortalError('Respons login tidak valid.', 502, d);
	return { token: d.token as string, user: d.user as PortalUser };
}

export async function register(input: {
	username: string;
	full_name: string;
	email: string;
	password: string;
}) {
	const d = await req('/register', {
		method: 'POST',
		body: JSON.stringify({ ...input, role: 'student' })
	});
	return {
		user: d.user as PortalUser | undefined,
		message: d.message as string | undefined,
		verification: d.verification_email_status as string | undefined
	};
}

export async function forgotPassword(email: string): Promise<{ message?: string }> {
	const d = await req('/forgot-password', { method: 'POST', body: JSON.stringify({ email }) });
	return { message: (d?.message as string) || 'Tautan ganti kata sandi telah dikirim ke email.' };
}

export async function resetPassword(
	token: string,
	email: string,
	password: string
): Promise<{ message?: string }> {
	const d = await req('/reset-password', {
		method: 'POST',
		body: JSON.stringify({ token, email, password, password_confirmation: password })
	});
	return { message: (d?.message as string) || 'Kata sandi berhasil diganti.' };
}

export async function resendVerification(email: string): Promise<{ message?: string }> {
	const d = await req('/resend-verification', { method: 'POST', body: JSON.stringify({ email }) });
	return { message: (d?.message as string) || 'Tautan verifikasi baru telah dikirim.' };
}

// ── Profil ──
export async function updateProfile(
	token: string,
	input: { full_name: string; username: string }
): Promise<{ user: PortalUser; message?: string }> {
	const d = await req('/profile', { method: 'POST', body: JSON.stringify(input) }, token);
	return { user: d.user as PortalUser, message: d.message as string | undefined };
}

export async function requestEmailChange(
	token: string,
	email: string
): Promise<{ message?: string }> {
	const d = await req('/profile/email', { method: 'POST', body: JSON.stringify({ email }) }, token);
	return { message: (d?.message as string) || 'Tautan konfirmasi telah dikirim ke email lama kamu.' };
}

export async function uploadPhoto(
	token: string,
	file: File
): Promise<{ user: PortalUser; message?: string }> {
	const fd = new FormData();
	fd.append('photo', file, file.name);
	const controller = new AbortController();
	const timer = setTimeout(() => controller.abort(), timeoutMs());
	try {
		const res = await fetch(`${apiBase()}/profile/photo`, {
			method: 'POST',
			signal: controller.signal,
			cache: 'no-store',
			headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
			body: fd
		});
		const text = await res.text();
		let data: any = {};
		try {
			data = text ? JSON.parse(text) : {};
		} catch {
			data = { message: text };
		}
		if (!res.ok) {
			// pesan validasi Laravel: { errors: { photo: [..] } }
			const msg = data?.errors?.photo?.[0] || data.message || 'Gagal mengunggah foto.';
			throw new PortalError(msg, res.status, data);
		}
		return { user: data.user as PortalUser, message: data.message as string | undefined };
	} catch (e: any) {
		if (e instanceof PortalError) throw e;
		throw new PortalError('Server tidak dapat dihubungi. Coba lagi beberapa saat.', 503);
	} finally {
		clearTimeout(timer);
	}
}

export async function getPortalUser(token: string): Promise<PortalUser> {
	const d = await req('/user', { method: 'GET' }, token);
	return (d.user ?? d) as PortalUser;
}

export async function logout(token: string): Promise<void> {
	try {
		await req('/logout', { method: 'POST' }, token);
	} catch {
		/* diamkan */
	}
}

// ── Mail (endpoint Portal SI) ──
export async function mailStatus(token: string): Promise<MailStatus> {
	return (await req('/mail/status', { method: 'GET' }, token)) as MailStatus;
}

export async function mailUnlock(token: string, master_password: string): Promise<void> {
	await req('/mail/unlock', { method: 'POST', body: JSON.stringify({ master_password }) }, token);
}

export async function mailGetAccount(token: string): Promise<{ account: MailAccountInfo | null }> {
	return (await req('/mail/account', { method: 'GET' }, token)) as { account: MailAccountInfo | null };
}

export async function mailCreateAccount(
	token: string,
	local_part: string
): Promise<{ account: MailAccountInfo }> {
	return (await req(
		'/mail/account',
		{ method: 'POST', body: JSON.stringify({ local_part }) },
		token
	)) as { account: MailAccountInfo };
}

export interface MailboxCreds {
	email: string;
	password: string;
}

export async function mailCredentials(token: string): Promise<MailboxCreds> {
	return (await req('/mail/credentials', { method: 'GET' }, token)) as MailboxCreds;
}

export async function mailAvatars(
	token: string,
	emails: string[]
): Promise<Record<string, string>> {
	if (!emails.length) return {};
	try {
		const d = await req('/mail/avatars', { method: 'POST', body: JSON.stringify({ emails }) }, token);
		return (d?.avatars as Record<string, string>) || {};
	} catch {
		return {};
	}
}
