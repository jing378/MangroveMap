const CACHE_NAME = 'mangrove-map-v2';
const urlsToCache = ['/', '/manifest.json'];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(urlsToCache))
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key)))
    )
  );
});

self.addEventListener('fetch', event => {
  if (event.request.mode === 'navigate') {
    return event.respondWith(fetch(event.request));
  }

  event.respondWith(
    caches.match(event.request).then(response => response || fetch(event.request))
  );
});