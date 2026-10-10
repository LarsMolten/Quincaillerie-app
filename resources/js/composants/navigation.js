/**
 * Navigation partielle : un clic sur un lien interne (ou l'envoi d'un formulaire GET) ne recharge plus toute
 * la page. La page demandée est récupérée en HTML complet (aucune adaptation côté serveur), puis seules les
 * zones marquées data-zone (contenu, menus) sont remplacées ; le titre, l'historique, le défilement et les
 * toasts flash suivent. Alpine initialise le nouveau contenu et détruit l'ancien (MutationObserver).
 *
 * Rechargement complet (comportement normal du navigateur) dans tous les autres cas :
 * - lien externe, target, attribut download, touche Ctrl/Maj/Alt, ancre de la même page ;
 * - lien ou formulaire marqué data-navigation="complete" ;
 * - page reçue sans les mêmes zones (connexion après expiration de session, pages d'erreur)
 *   ou avec d'autres ressources CSS/JS (nouvelle version déployée) ; erreur réseau.
 * Une réponse en pièce jointe (PDF « télécharger », Excel) est enregistrée directement, sans seconde requête.
 */
const DELAI_INDICATEUR = 150;

let controleur = null;
let urlAffichee = '';
let options = {};

const zones = (racine) => [...racine.querySelectorAll('[data-zone]')];
const signatureZones = (racine) => zones(racine).map((z) => z.dataset.zone).sort().join('|');
const ressources = (racine) => [...racine.querySelectorAll('head script[src], head link[rel="stylesheet"], head link[rel="modulepreload"]')]
    .map((e) => e.getAttribute('src') ?? e.getAttribute('href'))
    .sort()
    .join('|');
const cheminEtRequete = (url) => {
    const u = new URL(url, window.location.href);
    return u.pathname + u.search;
};

/** La page courante utilise-t-elle la coquille de l'application (zones remplaçables) ? */
const actif = () => document.querySelector('[data-zone="contenu"]') !== null;

/** Lien à intercepter ? (clic gauche simple, même origine, pas une ancre de la même page) */
export function lienNavigable(lien, evenement) {
    if (!(lien instanceof window.HTMLAnchorElement) || evenement.defaultPrevented || evenement.button !== 0
        || evenement.ctrlKey || evenement.metaKey || evenement.shiftKey || evenement.altKey) {
        return false;
    }
    const href = lien.getAttribute('href');
    if (!href || href.startsWith('#') || (lien.target && lien.target !== '_self')
        || lien.hasAttribute('download') || lien.dataset.navigation === 'complete') {
        return false;
    }
    const url = new URL(lien.href, window.location.href);
    if (url.origin !== window.location.origin || !['http:', 'https:'].includes(url.protocol)) {
        return false;
    }

    return !(url.hash && cheminEtRequete(url) === cheminEtRequete(window.location.href));
}

function indicateur(visible) {
    if (visible) {
        document.documentElement.dataset.navigation = 'chargement';
    } else {
        delete document.documentElement.dataset.navigation;
    }
}

function telecharger(blob, reponse) {
    const disposition = reponse.headers.get('content-disposition') ?? '';
    const nom = decodeURIComponent(disposition.match(/filename\*=UTF-8''([^;]+)/i)?.[1]
        ?? disposition.match(/filename="?([^";]+)"?/i)?.[1] ?? 'fichier');
    const lien = Object.assign(document.createElement('a'), { href: URL.createObjectURL(blob), download: nom });
    document.body.append(lien);
    lien.click();
    lien.remove();
    setTimeout(() => URL.revokeObjectURL(lien.href), 1000);
}

/** Remplace les zones, le titre et le jeton CSRF par ceux de la page reçue, puis affiche ses toasts flash. */
function appliquer(page) {
    for (const zone of zones(document)) {
        const nouvelle = document.adoptNode(page.querySelector(`[data-zone="${zone.dataset.zone}"]`));
        const defilement = zone.scrollTop;
        zone.replaceWith(nouvelle);
        // Les menus gardent leur position de défilement (le contenu, lui, repart du haut)
        if (zone.dataset.zone !== 'contenu') {
            nouvelle.scrollTop = defilement;
        }
    }
    document.title = page.title;
    // Couleur d'accent (Paramètres > Apparence) : celle de la page reçue
    if (page.documentElement.dataset.accent) {
        document.documentElement.dataset.accent = page.documentElement.dataset.accent;
    }
    const jeton = page.querySelector('meta[name="csrf-token"]')?.content;
    if (jeton) {
        document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', jeton);
    }
    const flash = page.getElementById('toasts-flash')?.textContent;
    JSON.parse(flash || '[]').forEach(({ type, message, lien }) => window.toast?.(type, message, undefined, lien));
}

/**
 * Charge une page de l'application et remplace son contenu.
 * historique : true = nouvelle entrée (clic), false = retour/avance (popstate) ; defilement : position à restaurer.
 */
export async function naviguer(url, { historique = true, defilement = null } = {}) {
    controleur?.abort();
    controleur = new AbortController();
    const { signal } = controleur;
    const cible = new URL(url, window.location.href);

    window.dispatchEvent(new CustomEvent('fermer-modal', { detail: 'tiroir-navigation' }));
    const minuteur = setTimeout(() => indicateur(true), DELAI_INDICATEUR);
    document.querySelector('[data-zone="contenu"]')?.setAttribute('aria-busy', 'true');
    const terminer = () => {
        clearTimeout(minuteur);
        indicateur(false);
        document.querySelector('[data-zone="contenu"]')?.removeAttribute('aria-busy');
    };

    try {
        const reponse = await fetch(cible.href, {
            headers: { Accept: 'text/html', 'X-Navigation': 'partielle' },
            credentials: 'same-origin',
            signal,
        });
        const type = reponse.headers.get('content-type') ?? '';

        if (!type.includes('text/html')) {
            if (/attachment/i.test(reponse.headers.get('content-disposition') ?? '')) {
                telecharger(await reponse.blob(), reponse);
                terminer();
            } else {
                options.recharger(cible.href);
            }

            return;
        }

        const page = new DOMParser().parseFromString(await reponse.text(), 'text/html');
        if (signal.aborted) {
            return;
        }
        // Après une redirection, l'URL finale (sans l'ancre, que fetch ne transmet pas)
        const finale = new URL(reponse.redirected && reponse.url ? reponse.url : cible.href);
        finale.hash = reponse.redirected ? '' : cible.hash;
        if (signatureZones(page) !== signatureZones(document) || ressources(page) !== ressources(document)) {
            options.recharger(finale.href);

            return;
        }

        if (historique) {
            // Position de la page quittée, restaurée au retour
            window.history.replaceState({ ...(window.history.state ?? {}), defilement: window.scrollY }, '');
            window.history.pushState({ navigation: true }, '', finale.href);
        }
        urlAffichee = cheminEtRequete(finale.href);

        const remplacer = () => appliquer(page);
        const reduit = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
        if (document.startViewTransition && !reduit) {
            await document.startViewTransition(remplacer).updateCallbackDone;
        } else {
            remplacer();
        }
        terminer();

        const ancre = finale.hash ? document.getElementById(decodeURIComponent(finale.hash.slice(1))) : null;
        if (ancre) {
            ancre.scrollIntoView();
        } else {
            window.scrollTo(0, defilement ?? 0);
        }
        // Accessibilité : le focus passe au nouveau contenu (lu par les lecteurs d'écran)
        if (historique) {
            document.querySelector('[data-zone="contenu"]')?.focus({ preventScroll: true });
        }
    } catch (erreur) {
        if (erreur.name === 'AbortError') {
            return;
        }
        terminer();
        options.recharger(cible.href);
    }
}

/**
 * Installe l'interception des liens, des formulaires GET et des boutons précédent/suivant.
 * recharger : chargement complet de secours (remplaçable dans les tests).
 */
export function installerNavigation({ recharger = (url) => window.location.assign(url) } = {}) {
    options = { recharger };
    urlAffichee = cheminEtRequete(window.location.href);
    if ('scrollRestoration' in window.history) {
        window.history.scrollRestoration = 'manual';
    }

    // Écouteurs sur le document : ceux des composants (liste dynamique, PDF…) passent avant et peuvent annuler
    document.addEventListener('click', (evenement) => {
        const lien = evenement.target.closest?.('a[href]');
        if (actif() && lienNavigable(lien, evenement)) {
            evenement.preventDefault();
            naviguer(lien.href);
        }
    });

    document.addEventListener('submit', (evenement) => {
        const formulaire = evenement.target;
        const bouton = evenement.submitter;
        const methode = (bouton?.getAttribute('formmethod') ?? formulaire.getAttribute('method') ?? 'get').toLowerCase();
        const target = bouton?.getAttribute('formtarget') ?? formulaire.getAttribute('target');
        if (!actif() || evenement.defaultPrevented || methode !== 'get' || (target && target !== '_self')
            || formulaire.dataset.navigation === 'complete') {
            return;
        }
        const url = new URL(bouton?.getAttribute('formaction') ?? formulaire.getAttribute('action') ?? window.location.href, window.location.href);
        if (url.origin !== window.location.origin) {
            return;
        }
        evenement.preventDefault();
        url.search = new URLSearchParams(new FormData(formulaire, bouton ?? undefined)).toString();
        naviguer(url.href);
    });

    // Les listes dynamiques changent l'URL sur place (filtres) : ce n'est pas un changement de page
    document.addEventListener('liste-chargee', () => {
        urlAffichee = cheminEtRequete(window.location.href);
    });

    window.addEventListener('popstate', (evenement) => {
        // Une ancre de la même page déclenche aussi popstate : on ne recharge que si la page change
        if (actif() && cheminEtRequete(window.location.href) !== urlAffichee) {
            naviguer(window.location.href, { historique: false, defilement: evenement.state?.defilement ?? 0 });
        }
    });
}
