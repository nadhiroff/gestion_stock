'use strict';

// Ancien Service Worker neutralisé.
// Objectif: se désinscrire et supprimer les caches pour laisser uniquement le SW racine (sw.js) actif.

async function cleanupAndUnregister() {
  try {
    // Supprimer tous les caches de l'ancien SW
    const keys = await caches.keys();
    await Promise.all(keys.map((k) => caches.delete(k)));
  } catch (_) { /* ignore */ }

  try {
    const reg = await self.registration.unregister();
    if (!reg) {
      // Fallback: tenter via clients
      const regs = await self.registration.scope ? await self.clients.matchAll() : [];
      // rien d'autre à faire ici
    }
  } catch (_) { /* ignore */ }
}

self.addEventListener('install', (event) => {
  event.waitUntil(cleanupAndUnregister().then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(cleanupAndUnregister().then(() => self.clients.claim()));
});

// Ne rien intercepter
self.addEventListener('fetch', () => {});
