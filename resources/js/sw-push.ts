/// <reference lib="WebWorker" />
/// <reference types="vite-plugin-pwa/client" />
import { cleanupOutdatedCaches, precacheAndRoute } from 'workbox-precaching';

declare const self: ServiceWorkerGlobalScope;

// Precaching automático gestionado por VitePWA/Workbox
precacheAndRoute(self.__WB_MANIFEST);
cleanupOutdatedCaches();

// Manejar evento push entrante
self.addEventListener('push', (event: PushEvent) => {
    if (!event.data) return;

    const data = event.data.json();
    const title: string = data.title ?? 'Cancha Sintética';
    const options: NotificationOptions = {
        body:    data.body ?? '',
        icon:    data.icon ?? '/icons/pwa-192.png',
        badge:   data.badge ?? '/icons/pwa-96.png',
        data:    data.data ?? {},
        vibrate: [200, 100, 200],
        tag:     'cancha-notification',
        renotify: true,
    };

    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

// Al hacer click en la notificación
self.addEventListener('notificationclick', (event: NotificationEvent) => {
    event.notification.close();

    const url: string = event.notification.data?.url ?? '/dashboard';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            // Si ya hay una ventana abierta, navegar en ella
            for (const client of clients) {
                if ('focus' in client && 'navigate' in client) {
                    return (client as WindowClient).focus().then((c) => c.navigate(url));
                }
            }
            // Si no hay ventana abierta, abrir una nueva
            if (self.clients.openWindow) {
                return self.clients.openWindow(url);
            }
        })
    );
});
