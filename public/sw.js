/*
 * Service worker de l'application Quincaillerie.
 *
 * Règle : seules les RESSOURCES STATIQUES sont mises en cache (CSS, JS et polices compilés par Vite
 * dans /build/, icônes de l'application, manifeste). Les pages HTML, les données métier, les requêtes
 * autres que GET et tout ce qui vient d'un autre domaine passent TOUJOURS par le réseau, sans cache :
 * les prix, stocks et ventes affichés sont donc toujours à jour.
 */
const VERSION = 'quincaillerie-statique-v1';

const RESSOURCES_STATIQUES = [/^\/build\//, /^\/icones\//, /^\/manifest\.webmanifest$/];

const estStatique = (requete) => {
    const url = new URL(requete.url);

    return requete.method === 'GET'
        && requete.mode !== 'navigate'
        && url.origin === self.location.origin
        && RESSOURCES_STATIQUES.some((motif) => motif.test(url.pathname));
};

self.addEventListener('install', () => self.skipWaiting());

// Suppression des caches des versions précédentes
self.addEventListener('activate', (evenement) => {
    evenement.waitUntil(
        caches.keys()
            .then((cles) => Promise.all(cles.filter((cle) => cle !== VERSION).map((cle) => caches.delete(cle))))
            .then(() => self.clients.claim()),
    );
});

// Cache d'abord pour les ressources statiques (noms hachés par Vite : jamais périmées)
self.addEventListener('fetch', (evenement) => {
    if (!estStatique(evenement.request)) {
        return; // Réseau uniquement, comportement normal du navigateur
    }

    evenement.respondWith(
        caches.open(VERSION).then(async (cache) => {
            const enCache = await cache.match(evenement.request);
            if (enCache) {
                return enCache;
            }

            const reponse = await fetch(evenement.request);
            if (reponse.ok) {
                cache.put(evenement.request, reponse.clone());
            }

            return reponse;
        }),
    );
});
