/// <reference lib="webworker" />

import { build, files, version } from '$service-worker';

const worker = self as unknown as ServiceWorkerGlobalScope;
const cacheName = `portal-si-static-${version}`;
const staticAssets = [...build, ...files].filter(
	(path) => !path.endsWith('.map') && !path.includes('muslim-man.png')
);

worker.addEventListener('install', (event) => {
	event.waitUntil(caches.open(cacheName).then((cache) => cache.addAll(staticAssets)));
	void worker.skipWaiting();
});

worker.addEventListener('activate', (event) => {
	event.waitUntil(
		caches
			.keys()
			.then((keys) =>
				Promise.all(keys.filter((key) => key !== cacheName).map((key) => caches.delete(key)))
			)
			.then(() => worker.clients.claim())
	);
});

worker.addEventListener('fetch', (event) => {
	const request = event.request;
	if (request.method !== 'GET') return;
	const url = new URL(request.url);
	if (url.origin !== worker.location.origin || url.pathname.startsWith('/api/')) return;

	// Authenticated HTML is deliberately network-only and never written to Cache Storage.
	if (request.mode === 'navigate') {
		event.respondWith(
			fetch(request).catch(
				() =>
					new Response(
						'Portal SI sedang offline. Sambungkan kembali internet untuk membuka halaman ini.',
						{ status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' } }
					)
			)
		);
		return;
	}

	if (staticAssets.includes(url.pathname)) {
		event.respondWith(caches.match(request).then((cached) => cached ?? fetch(request)));
	}
});

// ── Web Push ──
worker.addEventListener('push', (event) => {
	let data: { title?: string; body?: string; url?: string; icon?: string; badge?: string; tag?: string } = {};
	try {
		data = event.data ? event.data.json() : {};
	} catch {
		data = { body: event.data?.text() };
	}
	const title = data.title || 'Portal SI';
	const options: NotificationOptions = {
		body: data.body || '',
		icon: data.icon || '/assets/logo-mark.png',
		badge: data.badge || '/assets/logo-mark.png',
		tag: data.tag,
		data: { url: data.url || '/notifications' }
	};
	event.waitUntil(worker.registration.showNotification(title, options));
});

worker.addEventListener('notificationclick', (event) => {
	event.notification.close();
	const target = (event.notification.data && event.notification.data.url) || '/notifications';
	event.waitUntil(
		worker.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
			// Fokuskan tab yang sudah terbuka bila ada; jika tidak, buka tab baru.
			for (const client of clients) {
				if ('focus' in client) {
					void client.focus();
					if ('navigate' in client) void (client as WindowClient).navigate(target);
					return;
				}
			}
			return worker.clients.openWindow(target);
		})
	);
});
