/* ============================================
   CAP360 Service Worker — Cache & Offline
   ============================================ */

const CACHE_NAME    = 'cap360-v2.0.0';
const STATIC_ASSETS = [
  './',
  './index.html',
  './manifest.json',
  './assets/styles/main.css',
  './assets/styles/components.css',
  './core/storage.js',
  './core/migration.js',
  './core/engine.js',
  './core/charts.js',
  './core/ui.js',
  './core/router.js',
  './modules/budget/budget.js',
  './modules/cockpit/cockpit.js',
  './modules/sante/sante.js',
  './modules/maison/maison.js',
  './modules/projets/projets.js',
  './modules/vehicules/vehicules.js',
  './modules/coffre/coffre.js',
];

/* ---- Install: cache static assets ---- */
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      return Promise.allSettled(
        STATIC_ASSETS.map(url =>
          cache.add(url).catch(e => console.warn('[SW] Cache miss:', url, e))
        )
      );
    }).then(() => self.skipWaiting())
  );
});

/* ---- Activate: clean old caches ---- */
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(
        keys
          .filter(key => key !== CACHE_NAME)
          .map(key => caches.delete(key))
      )
    ).then(() => self.clients.claim())
  );
});

/* ---- Fetch: cache-first for local, network-first for CDN ---- */
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  // Skip non-GET and cross-origin (except CDN)
  if (event.request.method !== 'GET') return;

  // Chart.js CDN: network first, fallback to cache
  if (url.hostname === 'cdn.jsdelivr.net') {
    event.respondWith(
      fetch(event.request)
        .then(response => {
          const clone = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
          return response;
        })
        .catch(() => caches.match(event.request))
    );
    return;
  }

  // Same-origin: cache first
  if (url.origin === self.location.origin) {
    event.respondWith(
      caches.match(event.request).then(cached => {
        if (cached) return cached;
        return fetch(event.request).then(response => {
          if (response && response.status === 200) {
            const clone = response.clone();
            caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
          }
          return response;
        });
      })
    );
  }
});
