const CACHE_NAME = 'carbay-shell-v8';
const SHELL = [
    '/manifest.webmanifest',
    '/landing-manifest.webmanifest',
    '/manager-manifest.webmanifest',
    '/carbay-logo.png',
    '/carbay-logo-dark.png',
    '/carbay-car.png',
    '/carbay-favicon-192.png',
    '/carbay-favicon-512.png',
    '/offline-new-job.html',
];

// ngrok's free preview interstitial must not be cached as a manifest or logo.
const networkRequest = (request) => {
    if (! /(^|\.)ngrok-free\.(dev|app)$|(^|\.)ngrok\.io$/.test(self.location.hostname)) return request;
    const prepared = new Request(request, { credentials: 'same-origin', mode: 'same-origin' });
    const headers = new Headers(prepared.headers);
    headers.set('ngrok-skip-browser-warning', '1');
    return new Request(prepared, { headers });
};

self.addEventListener('install', (event) => {
    // An unavailable optional image must not stop service-worker installation.
    event.waitUntil(caches.open(CACHE_NAME).then((cache) => Promise.allSettled(SHELL.map((path) => cache.add(networkRequest(new Request(new URL(path, self.location.origin))))))));
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
        || url.pathname === '/manager' || url.pathname.startsWith('/manager/')
        || url.pathname.startsWith('/staff-photos/')
    ) {
        return;
    }

    event.respondWith(fetch(networkRequest(event.request)).catch(() => caches.match(event.request)));
});
