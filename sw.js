/*
 * Service worker dédié au mode hors ligne
 */
importScripts('workbox-sw.js');

// Nom du cache utilisé pour stocker la page de repli hors ligne
const CACHE = `${'localhost'}`;

// Page affichée quand la navigation réseau échoue
const offlineFallbackPage = "offline.php";

self.addEventListener("message", (event) => {
  // Permet de forcer l'activation immédiate du nouveau service worker
  if (event.data && event.data.type === "SKIP_WAITING") {
    self.skipWaiting();
  }
});

self.addEventListener('install', async (event) => {
  // Précharge la page de secours dans le cache dès l'installation
  event.waitUntil(
    caches.open(CACHE)
      .then((cache) => cache.add(offlineFallbackPage))
  );
});

// Active le préchargement de navigation Workbox si le navigateur le supporte
if (workbox.navigationPreload.isSupported()) {
  workbox.navigationPreload.enable();
}

self.addEventListener('fetch', (event) => {
  // Ne traite que les vraies navigations de page
  if (event.request.mode === 'navigate') {
    event.respondWith((async () => {
      try {
        // Réutilise la réponse préchargée si elle existe
        const preloadResp = await event.preloadResponse;

        if (preloadResp) {
          return preloadResp;
        }

        // Sinon, tente la réponse réseau normale
        const networkResp = await fetch(event.request);
        return networkResp;
      } catch (error) {
        // En cas d'échec réseau, renvoie la page mise en cache
        const cache = await caches.open(CACHE);
        const cachedResp = await cache.match(offlineFallbackPage);
        return cachedResp;
      }
    })());
  }
});
