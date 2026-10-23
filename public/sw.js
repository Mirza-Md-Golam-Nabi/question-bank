// The app's service worker: shows the push notifications the server sends
// (offers and news — App\Notifications\OfferPushNotification) and opens their
// link when tapped. Nothing is cached — exams and questions are always online.

// Take over at once after a deploy, instead of waiting until every tab of
// the site has been closed.
self.addEventListener('install', (event) => {
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
    if (!event.data) {
        return;
    }

    const payload = event.data.json();
    const options = { ...payload };
    delete options.title;

    event.waitUntil(self.registration.showNotification(payload.title || 'Next Fusion', options));
});

// Tapping a notification: back to an open window of the site if there is
// one, on the notification's page; otherwise a new one.
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = new URL((event.notification.data && event.notification.data.url) || '/app', self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            for (const client of windowClients) {
                if (new URL(client.url).origin === self.location.origin && 'focus' in client) {
                    return client.navigate(url).then((navigated) => (navigated || client).focus());
                }
            }

            return self.clients.openWindow(url);
        }),
    );
});
