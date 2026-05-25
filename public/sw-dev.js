// Service Worker para entorno de desarrollo (servido directo desde /public por Laravel).
// Solo maneja push + notificationclick — sin precaching, eso lo hace el SW de prod (VitePWA).

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
    if (!event.data) return;

    let data = {};
    try {
        data = event.data.json();
    } catch {
        data = { title: 'Cancha Sintética', body: event.data.text() };
    }

    const title = data.title || 'Cancha Sintética';
    const options = {
        body: data.body || '',
        icon: data.icon || '/icons/pwa-192.png',
        badge: data.badge || '/icons/pwa-96.png',
        data: data.data || {},
        vibrate: [200, 100, 200],
        tag: `cancha-${Date.now()}`,
        renotify: false,
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = (event.notification.data && event.notification.data.url) || '/dashboard';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            for (const client of clients) {
                if ('focus' in client && 'navigate' in client) {
                    return client.focus().then((c) => c.navigate(url));
                }
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(url);
            }
        })
    );
});
