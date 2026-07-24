import { env } from '$env/dynamic/public';
import { clientRequest } from '$lib/api/client';

const VAPID_PUBLIC_KEY = (env.PUBLIC_VAPID_PUBLIC_KEY ?? '').trim();

// Ambil public key: utamakan env web; bila kosong (adapter-node kadang tak memuat .env),
// ambil dari API yang pasti punya kuncinya. Hasilnya di-cache.
let cachedVapidKey: string | null = null;
async function getVapidKey(): Promise<string> {
	if (VAPID_PUBLIC_KEY) return VAPID_PUBLIC_KEY;
	if (cachedVapidKey !== null) return cachedVapidKey;
	try {
		const res = (await clientRequest('push/public-key')) as { key?: string } | null;
		cachedVapidKey = (res?.key ?? '').trim();
	} catch {
		cachedVapidKey = '';
	}
	return cachedVapidKey;
}

/** Web Push didukung di browser ini? */
export function pushSupported(): boolean {
	return (
		typeof window !== 'undefined' &&
		'serviceWorker' in navigator &&
		'PushManager' in window &&
		'Notification' in window
	);
}

/** Status izin notifikasi saat ini. */
export function pushPermission(): NotificationPermission | 'unsupported' {
	if (!pushSupported()) return 'unsupported';
	return Notification.permission;
}

/** Sudah punya langganan aktif di perangkat ini? */
export async function isPushSubscribed(): Promise<boolean> {
	if (!pushSupported()) return false;
	try {
		const reg = await navigator.serviceWorker.ready;
		const sub = await reg.pushManager.getSubscription();
		return sub !== null;
	} catch {
		return false;
	}
}

function urlBase64ToUint8Array(base64String: string): Uint8Array {
	const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
	const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
	const raw = atob(base64);
	const out = new Uint8Array(raw.length);
	for (let i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
	return out;
}

export type EnableResult = 'granted' | 'denied' | 'unsupported' | 'no-key' | 'error';

/**
 * Minta izin, buat langganan push, dan kirim ke server.
 * Memicu prompt izin browser — panggil dari dalam gesture user (klik).
 */
export async function enablePush(): Promise<EnableResult> {
	if (!pushSupported()) return 'unsupported';
	const vapidKey = await getVapidKey();
	if (!vapidKey) return 'no-key';
	try {
		const permission = await Notification.requestPermission();
		if (permission !== 'granted') return 'denied';

		const reg = await navigator.serviceWorker.ready;
		let sub = await reg.pushManager.getSubscription();
		if (!sub) {
			sub = await reg.pushManager.subscribe({
				userVisibleOnly: true,
				applicationServerKey: urlBase64ToUint8Array(vapidKey)
			});
		}

		const json = sub.toJSON();
		await clientRequest('push/subscribe', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({
				endpoint: json.endpoint,
				keys: { p256dh: json.keys?.p256dh, auth: json.keys?.auth }
			})
		});
		return 'granted';
	} catch {
		return 'error';
	}
}

/** Nonaktifkan push di perangkat ini (hapus langganan lokal + server). */
export async function disablePush(): Promise<boolean> {
	if (!pushSupported()) return false;
	try {
		const reg = await navigator.serviceWorker.ready;
		const sub = await reg.pushManager.getSubscription();
		if (sub) {
			const endpoint = sub.toJSON().endpoint;
			await sub.unsubscribe();
			if (endpoint) {
				await clientRequest('push/unsubscribe', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ endpoint })
				}).catch(() => undefined);
			}
		}
		return true;
	} catch {
		return false;
	}
}
