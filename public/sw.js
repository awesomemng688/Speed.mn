const CACHE = 'speedmn-static-v1';
const PRECACHE = ['/favicon.svg'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    // Static assets: cache-first, refreshed in the background.
    if (/^\/(css|js|img|build)\//.test(url.pathname) || url.pathname === '/favicon.svg') {
        event.respondWith(
            caches.open(CACHE).then(async (cache) => {
                const cached = await cache.match(request);
                const network = fetch(request).then((response) => {
                    if (response.ok) {
                        cache.put(request, response.clone());
                    }
                    return response;
                }).catch(() => cached);

                return cached || network;
            })
        );
    }
    // Pages and API stay network-only so live server data is never stale.
});
