const CACHE_NAME = 'carbay-shell-v5';
const SHELL = [
    '/manifest.webmanifest',
    '/carbay-logo.png',
    '/carbay-logo-dark.png',
    '/carbay-car.png',
    '/carbay-favicon-192.png',
    '/carbay-favicon-512.png',
    '/offline-new-job.html',
];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(SHELL)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key)),
        )),
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET' || new URL(event.request.url).origin !== self.location.origin) {
        return;
    }

    const url = new URL(event.request.url);
    if (url.pathname === '/app/new-wash-job') {
        event.respondWith(fetch(event.request).catch(async () => {
            const offlinePage = await caches.match('/offline-new-job.html');
            if (offlinePage) {
                return offlinePage;
            }

            return new Response('You are offline. Reconnect to Carbay+ to record the wash job.', {
                status: 503,
                headers: { 'Content-Type': 'text/plain; charset=utf-8' },
            });
        }));

        return;
    }

    if (
        url.pathname === '/app' || url.pathname.startsWith('/app/')
        || url.pathname === '/worker' || url.pathname.startsWith('/worker/')
    ) {
        return;
    }

    event.respondWith(fetch(event.request).catch(() => caches.match(event.request)));
});
