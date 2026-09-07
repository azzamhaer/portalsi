import { fail, redirect } from '@sveltejs/kit';
import {
	createUser,
	deleteUser,
	listUsers,
	setAdmin,
	setPassword
} from '$lib/server/auth';
import type { Actions, PageServerLoad } from './$types';

function guard(locals: App.Locals, platform: App.Platform | undefined) {
	const env = platform?.env;
	if (!locals.user || !env) throw redirect(302, '/login');
	if (!locals.user.is_admin) throw redirect(302, '/');
	return { env, me: locals.user };
}

export const load: PageServerLoad = async ({ locals, platform }) => {
	const { env, me } = guard(locals, platform);
	const users = await listUsers(env);
	return { users, domain: env.MAIL_DOMAIN, meId: me.id };
};

export const actions: Actions = {
	create: async ({ request, locals, platform }) => {
		const { env } = guard(locals, platform);
		const f = await request.formData();
		const local = String(f.get('username') ?? '').trim().toLowerCase();
		const fullName = String(f.get('full_name') ?? '').trim();
		const password = String(f.get('password') ?? '');
		const isAdmin = String(f.get('make_admin') ?? '') === '1';
		if (!local || !password) return fail(422, { error: 'Isi nama email dan kata sandi.' });
		try {
			await createUser(env, { localPart: local, password, fullName, isAdmin });
		} catch (e: any) {
			return fail(e?.status || 422, { error: e?.message || 'Gagal membuat mailbox.' });
		}
		return { created: `${local}@${env.MAIL_DOMAIN}` };
	},

	resetpw: async ({ request, locals, platform }) => {
		const { env } = guard(locals, platform);
		const f = await request.formData();
		const id = Number(f.get('id'));
		const password = String(f.get('password') ?? '');
		if (!id || password.length < 8) return fail(422, { error: 'Kata sandi minimal 8 karakter.' });
		try {
			await setPassword(env, id, password);
		} catch (e: any) {
			return fail(e?.status || 422, { error: e?.message || 'Gagal reset kata sandi.' });
		}
		return { reset: true };
	},

	toggleAdmin: async ({ request, locals, platform }) => {
		const { env, me } = guard(locals, platform);
		const f = await request.formData();
		const id = Number(f.get('id'));
		const makeAdmin = String(f.get('to') ?? '') === '1';
		if (id === me.id) return fail(422, { error: 'Tidak bisa mengubah status admin diri sendiri.' });
		try {
			await setAdmin(env, id, makeAdmin);
		} catch (e: any) {
			return fail(e?.status || 422, { error: e?.message || 'Gagal mengubah status.' });
		}
		return { ok: true };
	},

	remove: async ({ request, locals, platform }) => {
		const { env, me } = guard(locals, platform);
		const f = await request.formData();
		const id = Number(f.get('id'));
		if (id === me.id) return fail(422, { error: 'Tidak bisa menghapus akun sendiri.' });
		try {
			await deleteUser(env, id);
		} catch (e: any) {
			return fail(e?.status || 422, { error: e?.message || 'Gagal menghapus mailbox.' });
		}
		return { removed: true };
	}
};
