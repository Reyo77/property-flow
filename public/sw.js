/*
 * PropertyFlow service worker.
 *
 * Keeps an offline page and the app's static files (hashed build assets, icons) on the device.
 * Pages and API data are never cached: they are private to whoever is signed in, and a shared
 * phone or computer must not show them to the next person. When the network is down, a page
 * request falls back to the offline page instead of the browser's error.
 *
 * Bump VERSION when the offline page or the precached files change.
 */
const VERSION = 'v1';
const CACHE = `propertyflow-${VERSION}`;
const PRECACHE = ['/offline', '/favicon.svg', '/icons/icon-192.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key.startsWith('propertyflow-') && key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

const isStaticAsset = (url) => url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/') || url.pathname === '/favicon.svg';

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match('/offline')));

        return;
    }

    if (isStaticAsset(url)) {
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request).then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(CACHE).then((cache) => cache.put(request, copy));
                }

                return response;
            })),
        );
    }
});
