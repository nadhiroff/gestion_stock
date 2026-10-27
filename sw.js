'use strict';

// v3: dynamic scope-based paths to avoid hardcoded "/gestion_stock" issues across devices
const CACHE_NAME = 'fai-gestion-v3';

// Derive base path from SW registration scope (e.g., https://host/gestion_stock/)
const SCOPE_URL = new URL(self.registration.scope);
const BASE_PATH = SCOPE_URL.pathname.replace(/\/$/, ''); // no trailing slash

const asset = (p) => `${BASE_PATH}${p}`;

const ASSETS = [
  // Core shell
  asset('/'),
  asset('/public/index.php'),
  asset('/app/dashboard.php'),
  asset('/assets/css/style.css'),
  asset('/assets/js/app.js'),
  asset('/assets/img/logo.jpg')
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(ASSETS))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

function isHTML(request) {
  const url = new URL(request.url);
  return request.mode === 'navigate' || (request.destination === 'document') || url.pathname.endsWith('.php');
}

self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);
  // Only handle same-origin GET
  if (req.method !== 'GET' || url.origin !== self.location.origin) return;

  // Strategy: network-first for pages (PHP/HTML), cache-first for assets
  if (isHTML(req)) {
    event.respondWith(
      fetch(req)
        .then((res) => {
          try { const clone = res.clone(); caches.open(CACHE_NAME).then((c) => c.put(req, clone)); } catch (_) {}
          return res;
        })
        .catch(() => {
          // Fallback to cached request, then to public/index.php within scope
          const scopeIndex = new Request(asset('/public/index.php'));
          return caches.match(req, { ignoreSearch: true })
            .then((cached) => cached || caches.match(scopeIndex, { ignoreSearch: true }));
        })
    );
    return;
  }

  // Assets: cache-first
  event.respondWith(
    caches.match(req, { ignoreSearch: true }).then((cached) => {
      if (cached) return cached;
      return fetch(req).then((res) => {
        try { const clone = res.clone(); caches.open(CACHE_NAME).then((c) => c.put(req, clone)); } catch (_) {}
        return res;
      });
    })
  );
});
