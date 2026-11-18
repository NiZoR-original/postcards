'use strict';

const racine = new URL(self.registration.scope);
const prefixe = 'atlas-postal:' + racine.pathname + ':';
const cacheActuel = prefixe + 'hors-ligne-v1';
const ecranHorsLigne = new URL('assets/hors-ligne.html', racine).href;
const pagesPubliques = new Set(['', 'index.php', 'usa-canada.php', 'france.php', 'carte.php', 'en-route.php', 'swaps.php', 'confidentialite.php']);

self.addEventListener('install', event => {
  event.waitUntil(caches.open(cacheActuel).then(cache => cache.add(ecranHorsLigne)));
});

self.addEventListener('activate', event => {
  event.waitUntil((async () => {
    const noms = await caches.keys();
    await Promise.all(noms.filter(nom => nom.startsWith(prefixe) && nom !== cacheActuel).map(nom => caches.delete(nom)));
    await self.clients.claim();
  })());
});

self.addEventListener('fetch', event => {
  const requete = event.request;
  if (requete.method !== 'GET' || requete.mode !== 'navigate') return;
  const url = new URL(requete.url);
  if (url.origin !== racine.origin || !url.pathname.startsWith(racine.pathname)
      || !pagesPubliques.has(url.pathname.slice(racine.pathname.length))) return;

  // Seul l’écran statique est en cache. Les pages, photos et données restent en ligne.
  event.respondWith(fetch(requete).catch(async () => {
    const cache = await caches.open(cacheActuel);
    return await cache.match(ecranHorsLigne) ?? Response.error();
  }));
});
