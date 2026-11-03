// PWA scaffold (tiket 01): cache-first untuk aset build, network untuk sisanya.
// Evolusi offline-draft milestone (tiket 07) akan memperluas file ini.
const CACHE = 'ppjk-shell-v1';
const PRECACHE = ['/manifest.webmanifest'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);
    if (event.request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }
    if (url.pathname.startsWith('/build/') || PRECACHE.includes(url.pathname)) {
        event.respondWith(
            caches.match(event.request).then(
                (cached) =>
                    cached ||
                    fetch(event.request).then((response) => {
                        const copy = response.clone();
                        caches.open(CACHE).then((cache) => cache.put(event.request, copy));
                        return response;
                    }),
            ),
        );
    }
});
