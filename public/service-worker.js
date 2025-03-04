// Cache name with version
const CACHE_NAME = 'cryptogift-cache-v1';

// Assets to cache
const ASSETS_TO_CACHE = [
    '/',
    '/assets/css/style.css',
    '/assets/js/app.js',
    '/assets/images/default-avatar.png',
    '/assets/images/notification-icon.png'
];

// Install event - cache assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                return cache.addAll(ASSETS_TO_CACHE);
            })
            .then(() => {
                return self.skipWaiting();
            })
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((cacheNames) => {
                return Promise.all(
                    cacheNames.map((cacheName) => {
                        if (cacheName !== CACHE_NAME) {
                            return caches.delete(cacheName);
                        }
                    })
                );
            })
            .then(() => {
                return self.clients.claim();
            })
    );
});

// Fetch event - serve from cache, fallback to network
self.addEventListener('fetch', (event) => {
    // Skip cross-origin requests
    if (!event.request.url.startsWith(self.location.origin)) {
        return;
    }

    event.respondWith(
        caches.match(event.request)
            .then((response) => {
                if (response) {
                    return response;
                }

                return fetch(event.request)
                    .then((response) => {
                        // Don't cache non-successful responses
                        if (!response || response.status !== 200 || response.type !== 'basic') {
                            return response;
                        }

                        // Clone the response as it can only be consumed once
                        const responseToCache = response.clone();

                        caches.open(CACHE_NAME)
                            .then((cache) => {
                                cache.put(event.request, responseToCache);
                            });

                        return response;
                    });
            })
    );
});

// Push event - handle push notifications
self.addEventListener('push', (event) => {
    if (!event.data) {
        return;
    }

    const data = event.data.json();
    const options = {
        body: data.body,
        icon: '/assets/images/notification-icon.png',
        badge: '/assets/images/notification-badge.png',
        vibrate: [100, 50, 100],
        data: {
            url: data.url
        }
    };

    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

// Notification click event - handle notification clicks
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    if (event.notification.data.url) {
        event.waitUntil(
            clients.openWindow(event.notification.data.url)
        );
    }
});

// Background sync event - handle offline actions
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-transactions') {
        event.waitUntil(syncTransactions());
    }
});

// Function to sync offline transactions
async function syncTransactions() {
    try {
        const db = await openDB();
        const offlineTransactions = await db.getAll('offlineTransactions');

        for (const transaction of offlineTransactions) {
            try {
                const response = await fetch('/api/transactions', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(transaction)
                });

                if (response.ok) {
                    await db.delete('offlineTransactions', transaction.id);
                }
            } catch (error) {
                console.error('Failed to sync transaction:', error);
            }
        }
    } catch (error) {
        console.error('Failed to sync transactions:', error);
    }
}

// IndexedDB helper function
function openDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('CryptoGiftDB', 1);

        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);

        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            if (!db.objectStoreNames.contains('offlineTransactions')) {
                db.createObjectStore('offlineTransactions', { keyPath: 'id', autoIncrement: true });
            }
        };
    });
}

// Periodic background sync (if supported)
self.addEventListener('periodicsync', (event) => {
    if (event.tag === 'update-rates') {
        event.waitUntil(updateRates());
    }
});

// Function to update rates periodically
async function updateRates() {
    try {
        const response = await fetch('/api/rates/current');
        if (response.ok) {
            const rates = await response.json();
            const db = await openDB();
            const tx = db.transaction('rates', 'readwrite');
            const store = tx.objectStore('rates');
            await store.put(rates);
        }
    } catch (error) {
        console.error('Failed to update rates:', error);
    }
}
