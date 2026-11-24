// Delivra web push service worker. Shows the push and opens same-origin pages only.

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => {
    if (!event.data) {
        return;
    }

    let message;

    try {
        message = event.data.json();
    } catch (e) {
        message = { title: event.data.text() };
    }

    // the payload is WebPushMessage::toArray(): the title plus showNotification() options
    const { title, ...options } = message;

    event.waitUntil(self.registration.showNotification(title || self.location.hostname, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = new URL(event.notification.data?.url || '/', self.location.origin);

    // only open pages of this app
    if (url.origin !== self.location.origin) {
        return;
    }

    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });

        for (const client of windows) {
            if (client.url === url.href && 'focus' in client) {
                return client.focus();
            }
        }

        return self.clients.openWindow(url.href);
    })());
});
