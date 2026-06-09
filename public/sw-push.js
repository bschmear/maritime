/* Web Push handlers — imported by the Workbox-generated service worker at /sw.js */

self.addEventListener('push', (event) => {
    let payload = {
        title: 'Notification',
        body: '',
        url: '/',
        tag: undefined,
    };

    try {
        if (event.data) {
            payload = { ...payload, ...event.data.json() };
        }
    } catch (e) {
        if (event.data) {
            payload.body = event.data.text();
        }
    }

    const options = {
        body: payload.body || '',
        icon: '/brand/icons/android-chrome-192x192.png',
        badge: '/brand/icons/android-chrome-192x192.png',
        data: { url: payload.url || '/' },
        tag: payload.tag || undefined,
        renotify: Boolean(payload.tag),
    };

    event.waitUntil(self.registration.showNotification(payload.title || 'Notification', options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const targetUrl = event.notification.data?.url || '/';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                const clientPath = new URL(client.url).pathname + new URL(client.url).search;

                if (clientPath === targetUrl && 'focus' in client) {
                    return client.focus();
                }
            }

            if (self.clients.openWindow) {
                return self.clients.openWindow(targetUrl);
            }

            return undefined;
        }),
    );
});
