import { writable, derived } from 'svelte/store';
import type { SessionUser } from '$lib/schemas/user';

/**
 * Pengguna yang sedang login, di-set sekali dari layout app. Dipakai komponen mana pun
 * (kartu post, reels, dsb.) yang perlu tahu identitas/role tanpa menerima prop berantai.
 */
export const sessionUser = writable<SessionUser | null>(null);

/**
 * Apakah pengguna ini moderator: Developer (role 'dev') yang SUDAH terverifikasi.
 * Gerbang tampilan menu Moderasi; keputusan sebenarnya tetap divalidasi server.
 */
export const isModerator = derived(
	sessionUser,
	($u) => Boolean($u && $u.role === 'dev' && $u.badgeVerified)
);
