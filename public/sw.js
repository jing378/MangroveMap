const CACHE_NAME = 'mangrove-map-v3';
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

// Device notifications (Web Push): the server sends title/body/icon/tag and data.url
// (see App\Notifications\Channels\DevicePushChannel).
self.addEventListener('push', event => {
  let payload = {};
  try {
    payload = event.data ? event.data.json() : {};
  } catch (e) {
    payload = { body: event.data ? event.data.text() : '' };
  }

  const title = payload.title || 'MangroveMap';
  const options = {
    body: payload.body || '',
    icon: payload.icon || '/icon-192.png',
    badge: payload.badge || '/icon-192.png',
    tag: payload.tag || undefined,
    data: payload.data || {},
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

// Tapping the notification opens its page, reusing an open MangroveMap tab when there is one.
self.addEventListener('notificationclick', event => {
  event.notification.close();

  const target = new URL((event.notification.data && event.notification.data.url) || '/notifications', self.location.origin).href;

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(windows => {
      const sameSite = windows.find(win => new URL(win.url).origin === self.location.origin && 'navigate' in win);
      if (sameSite) {
        // navigate() only works on tabs this worker controls; otherwise open a new one.
        return sameSite.navigate(target)
          .then(win => (win || sameSite).focus())
          .catch(() => self.clients.openWindow(target));
      }
      return self.clients.openWindow(target);
    })
  );
});
