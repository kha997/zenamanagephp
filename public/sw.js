// Service Worker for ZenaManage Dashboard PWA
const CACHE_NAME = 'zenamanage-dashboard-v1';
const OFFLINE_URL = '/offline';

// Resources to cache
const STATIC_CACHE_URLS = [
  '/',
  '/app/dashboard',
  '/css/tailwind.css',
  '/css/design-system.css',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css',
  'https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js',
  'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js',
  'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap'
];

// API endpoints to cache
const API_CACHE_URLS = [
  '/api/v1/app/dashboard/metrics'
];

// Install event - cache static resources
self.addEventListener('install', event => {
  console.log('Service Worker: Installing...');
  
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Service Worker: Caching static resources');
        return cache.addAll(STATIC_CACHE_URLS);
      })
      .then(() => {
        console.log('Service Worker: Installation complete');
        return self.skipWaiting();
      })
      .catch(error => {
        console.error('Service Worker: Installation failed', error);
      })
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  console.log('Service Worker: Activating...');
  
  event.waitUntil(
    caches.keys()
      .then(cacheNames => {
        return Promise.all(
          cacheNames.map(cacheName => {
            if (cacheName !== CACHE_NAME) {
              console.log('Service Worker: Deleting old cache', cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      })
      .then(() => {
        console.log('Service Worker: Activation complete');
        return self.clients.claim();
      })
  );
});

// Fetch event - serve cached content when offline
self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);
  
  // Skip non-GET requests
  if (request.method !== 'GET') {
    return;
  }
  
  // Handle API requests
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(
      fetch(request)
        .then(response => {
          // Cache successful API responses
          if (response.status === 200) {
            const responseClone = response.clone();
            caches.open(CACHE_NAME)
              .then(cache => {
                cache.put(request, responseClone);
              });
          }
          return response;
        })
        .catch(() => {
          // Serve cached API response when offline
          return caches.match(request)
            .then(response => {
              if (response) {
                return response;
              }
              // Return offline response for API calls
              return new Response(
                JSON.stringify({
                  error: 'Offline',
                  message: 'You are currently offline. Some features may not be available.',
                  offline: true
                }),
                {
                  status: 503,
                  statusText: 'Service Unavailable',
                  headers: { 'Content-Type': 'application/json' }
                }
              );
            });
        })
    );
    return;
  }
  
  // Handle page requests
  event.respondWith(
    fetch(request)
      .then(response => {
        // Cache successful page responses
        if (response.status === 200) {
          const responseClone = response.clone();
          caches.open(CACHE_NAME)
            .then(cache => {
              cache.put(request, responseClone);
            });
        }
        return response;
      })
      .catch(() => {
        // Serve cached page or offline page
        return caches.match(request)
          .then(response => {
            if (response) {
              return response;
            }
            // Serve offline page for navigation requests
            if (request.mode === 'navigate') {
              return caches.match(OFFLINE_URL)
                .then(response => {
                  if (response) {
                    return response;
                  }
                  // Fallback offline page
                  return new Response(
                    `
                    <!DOCTYPE html>
                    <html lang="en">
                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <title>Offline - ZenaManage</title>
                        <style>
                            body {
                                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                                margin: 0;
                                padding: 0;
                                min-height: 100vh;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                            }
                            .offline-container {
                                background: white;
                                padding: 2rem;
                                border-radius: 12px;
                                box-shadow: 0 10px 25px rgba(0,0,0,0.1);
                                text-align: center;
                                max-width: 400px;
                            }
                            .offline-icon {
                                font-size: 3rem;
                                color: #6b7280;
                                margin-bottom: 1rem;
                            }
                            h1 {
                                color: #374151;
                                margin-bottom: 1rem;
                            }
                            p {
                                color: #6b7280;
                                margin-bottom: 2rem;
                            }
                            .retry-btn {
                                background: #2563eb;
                                color: white;
                                border: none;
                                padding: 0.75rem 1.5rem;
                                border-radius: 6px;
                                font-size: 1rem;
                                cursor: pointer;
                                transition: background-color 0.2s;
                            }
                            .retry-btn:hover {
                                background: #1d4ed8;
                            }
                        </style>
                    </head>
                    <body>
                        <div class="offline-container">
                            <div class="offline-icon">📡</div>
                            <h1>You're Offline</h1>
                            <p>It looks like you're not connected to the internet. Please check your connection and try again.</p>
                            <button class="retry-btn" onclick="window.location.reload()">Try Again</button>
                        </div>
                    </body>
                    </html>
                    `,
                    {
                      status: 200,
                      statusText: 'OK',
                      headers: { 'Content-Type': 'text/html' }
                    }
                  );
                });
            }
            return response;
          });
      })
  );
});

// Background sync for offline actions
self.addEventListener('sync', event => {
  console.log('Service Worker: Background sync triggered', event.tag);
  
  if (event.tag === 'dashboard-sync') {
    event.waitUntil(
      // Sync dashboard data when back online
      fetch('/api/v1/app/dashboard/metrics')
        .then(response => {
          if (response.ok) {
            console.log('Service Worker: Dashboard data synced');
            // Notify clients that data is synced
            self.clients.matchAll().then(clients => {
              clients.forEach(client => {
                client.postMessage({
                  type: 'DATA_SYNCED',
                  message: 'Dashboard data has been updated'
                });
              });
            });
          }
        })
        .catch(error => {
          console.error('Service Worker: Sync failed', error);
        })
    );
  }
});

// Push notifications
self.addEventListener('push', event => {
  console.log('Service Worker: Push notification received');
  
  const options = {
    body: event.data ? event.data.text() : 'New notification from ZenaManage',
    icon: '/icons/icon-192x192.png',
    badge: '/icons/icon-72x72.png',
    vibrate: [100, 50, 100],
    data: {
      dateOfArrival: Date.now(),
      primaryKey: 1
    },
    actions: [
      {
        action: 'explore',
        title: 'View Dashboard',
        icon: '/icons/icon-192x192.png'
      },
      {
        action: 'close',
        title: 'Close',
        icon: '/icons/icon-192x192.png'
      }
    ]
  };
  
  event.waitUntil(
    self.registration.showNotification('ZenaManage', options)
  );
});

// Notification click
self.addEventListener('notificationclick', event => {
  console.log('Service Worker: Notification clicked');
  
  event.notification.close();
  
  if (event.action === 'explore') {
    event.waitUntil(
      clients.openWindow('/app/dashboard')
    );
  }
});
