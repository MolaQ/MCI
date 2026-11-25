/*
 * MCI service worker (served by PwaController at /sw.js; __VERSION__ changes
 * with every front-end build). Deliberately small:
 *  - pages: network first, offline page when there is no connection
 *    (logged-in pages are never cached, so nothing private stays on a shared device);
 *  - /build and /pwa assets: cache first (file names are content-hashed);
 *  - Web Push: shows the notification and opens its link on click.
 */
const VERSION = '__VERSION__';
const OFFLINE_URL = '__OFFLINE_URL__';
const STATIC_CACHE = `mci-static-${VERSION}`;

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => cache.addAll([OFFLINE_URL, '/pwa/icon-192.png']))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k.startsWith('mci-') && k !== STATIC_CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
        return;
    }

    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/pwa/')) {
        event.respondWith(
            caches.match(request).then((hit) => hit || fetch(request).then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(STATIC_CACHE).then((cache) => cache.put(request, copy));
                }
                return response;
            })),
        );
    }
});

self.addEventListener('push', (event) => {
    let payload = {};
    try {
        payload = event.data ? event.data.json() : {};
    } catch (e) {
        payload = { title: 'SOWA', body: event.data ? event.data.text() : '' };
    }
    const { title = 'SOWA', ...options } = payload;
    options.icon ??= '/pwa/icon-192.png';
    options.badge ??= '/pwa/icon-96-badge.png';
    options.lang ??= 'pl';
    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = new URL(event.notification.data?.url || '/pulpit', self.location.origin);
    if (target.origin !== self.location.origin) return;

    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const client of windows) {
            if (new URL(client.url).origin === target.origin && 'focus' in client) {
                await client.focus();
                return client.navigate ? client.navigate(target.href) : undefined;
            }
        }
        return self.clients.openWindow(target.href);
    })());
});

// The browser rotated the subscription: register the new one in place of the old.
self.addEventListener('pushsubscriptionchange', (event) => {
    event.waitUntil((async () => {
        const old = event.oldSubscription;
        const fresh = event.newSubscription || (old && await self.registration.pushManager.subscribe(old.options));
        if (!old || !fresh) return;
        await fetch('/push/odnow', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ old_endpoint: old.endpoint, subscription: fresh.toJSON() }),
        });
    })());
});
