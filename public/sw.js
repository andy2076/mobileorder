const CACHE_NAME = 'mobileorder-v1';
const ASSETS = [
    './',
    './index.html',
    '../css/style.css',
    '../js/order.js',
    '../images/no-image.jpg'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => cache.addAll(ASSETS))
    );
});

self.addEventListener('fetch', event => {
    // APIリクエストはキャッシュしない
    if (event.request.url.includes('/api/')) {
        return fetch(event.request);
    }
    event.respondWith(
        caches.match(event.request).then(response => response || fetch(event.request))
    );
});
