// ============================================================
// Zone85 — Service Worker V10
// Cache-first sur assets, network-first sur pages PHP.
// ============================================================

'use strict';

const SW_VERSION   = 'zone85-v10.0';
const CACHE_STATIC = SW_VERSION + '-static';
const CACHE_PAGES  = SW_VERSION + '-pages';

// Assets mis en cache à l'installation (cache-first)
const PRECACHE_ASSETS = [
  '/assets/css/zone85.css',
  '/assets/js/hidden-hunt.js',
  '/assets/img/logo-header.png',
  '/assets/img/ZONE852025.png',
  '/assets/img/pwa/icon-192.png',
  '/assets/img/pwa/icon-512.png',
  '/offline.php',
];

// ── Install ─────────────────────────────────────────────────
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_STATIC).then(cache => {
      // Ignore les erreurs sur les ressources manquantes (build optionnel)
      return Promise.allSettled(
        PRECACHE_ASSETS.map(url => cache.add(url).catch(() => null))
      );
    })
  );
  self.skipWaiting();
});

// ── Activate ────────────────────────────────────────────────
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(
        keys.filter(k => k !== CACHE_STATIC && k !== CACHE_PAGES)
            .map(k => caches.delete(k))
      )
    )
  );
  self.clients.claim();
});

// ── Fetch ────────────────────────────────────────────────────
self.addEventListener('fetch', (event) => {
  const req = event.request;

  // Ignorer les requêtes non-GET, cross-origin, chrome-extension, admin
  if (req.method !== 'GET') return;
  if (!req.url.startsWith(self.location.origin)) return;
  if (req.url.includes('/admin/')) return;
  if (req.url.includes('/ajax/')) return;
  if (req.url.includes('/tools/')) return;

  const url = new URL(req.url);

  // Assets statiques (css, js, img, fonts) → Cache-first
  if (
    url.pathname.startsWith('/assets/') ||
    url.pathname.startsWith('/uploads/') ||
    url.pathname.match(/\.(png|jpg|jpeg|webp|gif|svg|woff2?|ttf|otf|ico)$/)
  ) {
    event.respondWith(cacheFirst(req, CACHE_STATIC));
    return;
  }

  // Pages PHP → Network-first avec fallback cache puis offline
  if (url.pathname.endsWith('.php') || url.pathname === '/' || url.pathname.endsWith('/')) {
    event.respondWith(networkFirstPage(req));
    return;
  }
});

// ── Helpers ──────────────────────────────────────────────────

async function cacheFirst(req, cacheName) {
  const cached = await caches.match(req);
  if (cached) return cached;
  try {
    const response = await fetch(req);
    if (response.ok) {
      const cache = await caches.open(cacheName);
      cache.put(req, response.clone());
    }
    return response;
  } catch {
    return new Response('', { status: 503 });
  }
}

async function networkFirstPage(req) {
  const cache = await caches.open(CACHE_PAGES);
  try {
    const response = await fetch(req);
    if (response.ok) {
      cache.put(req, response.clone());
    }
    return response;
  } catch {
    const cached = await cache.match(req);
    if (cached) return cached;
    const offline = await caches.match('/offline.php');
    return offline || new Response(
      '<html><body><h1>Hors ligne</h1><p>Vérifiez votre connexion.</p></body></html>',
      { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
    );
  }
}

// ── Push Notifications ───────────────────────────────────────
self.addEventListener('push', (event) => {
  if (!event.data) return;
  let data = {};
  try { data = event.data.json(); } catch { data = { title: 'Zone85', body: event.data.text() }; }

  const options = {
    body:    data.body    || 'Nouvelle notification Zone85',
    icon:    data.icon    || '/assets/img/pwa/icon-192.png',
    badge:   data.badge   || '/assets/img/pwa/icon-72.png',
    image:   data.image   || null,
    data:    data.data    || { url: '/' },
    vibrate: [100, 50, 100],
    actions: data.actions || [],
    tag:     data.tag     || 'zone85-notif',
    renotify: true,
  };

  event.waitUntil(
    self.registration.showNotification(data.title || 'Zone85', options)
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = event.notification.data?.url || '/';
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(list => {
      for (const client of list) {
        if (client.url === url && 'focus' in client) return client.focus();
      }
      if (clients.openWindow) return clients.openWindow(url);
    })
  );
});
