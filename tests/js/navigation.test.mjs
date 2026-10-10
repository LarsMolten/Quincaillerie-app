/**
 * Navigation partielle (resources/js/composants/navigation.js) avec le vrai Alpine dans un DOM simulé.
 * Lancement : npm run test:js
 */
import { after, before, beforeEach, describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { demarrer, fenetre } from './environnement.mjs';
import { installerNavigation } from '../../resources/js/composants/navigation.js';

// Même structure que layouts/app.blade.php (zones data-zone), en version réduite
const page = ({ titre, actif, contenu, script = '/build/app.js', toasts = '[]' }) => `<!DOCTYPE html>
<html><head><title>${titre}</title><meta name="csrf-token" content="jeton-${titre}"><script type="module" src="${script}"></script></head>
<body>
    <nav data-zone="menu-lateral">
        <a id="menu-produits" href="/produits" ${actif === 'produits' ? 'aria-current="page"' : ''}>Produits</a>
        <a id="menu-clients" href="/clients" ${actif === 'clients' ? 'aria-current="page"' : ''}>Clients</a>
    </nav>
    <main id="contenu" data-zone="contenu" tabindex="-1">${contenu}</main>
    <script type="application/json" id="toasts-flash">${toasts}</script>
</body></html>`;

const PRODUITS = page({
    titre: 'Produits',
    actif: 'produits',
    contenu: `<h1>Produits</h1>
        <a id="lien-client" href="/clients?page=2">Clients page 2</a>
        <a id="lien-externe" href="https://exemple.org/">Externe</a>
        <a id="lien-onglet" href="/factures/1/pdf" target="_blank">PDF</a>
        <a id="lien-export" href="/rapports/ventes/excel">Excel</a>
        <a id="lien-ancre" href="#bas">Bas</a>
        <a id="lien-erreur" href="/interdit">Interdit</a>
        <form id="filtres" method="GET" action="/produits"><input name="recherche" value="ciment"><button id="filtrer">Filtrer</button></form>
        <form id="poste" method="POST" action="/produits"><button id="envoyer">Envoyer</button></form>`,
});

let requetes;
let reponses;
let recharges;
let toasts;
let Alpine;

const attendre = async () => {
    for (let i = 0; i < 20; i++) {
        await new Promise((resolve) => setImmediate(resolve));
    }
    await fenetre.happyDOM.waitUntilComplete();
};
const cliquer = (id, init = {}) => {
    const evenement = new fenetre.MouseEvent('click', { bubbles: true, cancelable: true, button: 0, ...init });
    document.getElementById(id).dispatchEvent(evenement);

    return evenement;
};
const html = (corps, entetes = {}) => new Response(corps, { headers: { 'content-type': 'text/html; charset=UTF-8', ...entetes } });

describe('Navigation partielle', () => {
    before(async () => {
        // Composant Alpine dans le contenu : il doit être initialisé après remplacement (et l'ancien détruit)
        const compteur = (A) => A.data('compteur', () => ({
            init() { document.body.dataset.inits = Number(document.body.dataset.inits ?? 0) + 1; },
            destroy() { document.body.dataset.detruits = Number(document.body.dataset.detruits ?? 0) + 1; },
        }));
        globalThis.fetch = async (url, options = {}) => {
            requetes.push({ url, entete: options.headers?.['X-Navigation'] });
            return reponses.shift()();
        };
        globalThis.DOMParser = fenetre.DOMParser;
        fenetre.toast = (type, message) => toasts.push(`${type}:${message}`);
        Alpine = await demarrer(new fenetre.DOMParser().parseFromString(PRODUITS, 'text/html').body.innerHTML, [compteur]);
        document.title = 'Produits';
        document.head.innerHTML = '<title>Produits</title><meta name="csrf-token" content="jeton-Produits"><script type="module" src="/build/app.js"></script>';
        fenetre.history.replaceState(null, '', '/produits');
        installerNavigation({ recharger: (url) => recharges.push(url) });
    });

    after(() => fenetre.happyDOM.abort());

    beforeEach(() => {
        requetes = [];
        reponses = [];
        recharges = [];
        toasts = [];
    });

    it('remplace le contenu et le menu actif sans recharger la page', async () => {
        const nav = document.querySelector('[data-zone="menu-lateral"]');
        nav.parentElement.dataset.temoin = 'conserve';
        nav.scrollTop = 120;
        reponses.push(() => html(page({
            titre: 'Clients',
            actif: 'clients',
            contenu: '<h1>Clients</h1><div x-data="compteur"></div>',
            toasts: '[{"type":"succes","message":"Client enregistré."}]',
        })));

        const evenement = cliquer('menu-clients');
        await attendre();

        assert.equal(evenement.defaultPrevented, true, 'Le navigateur ne doit pas suivre le lien.');
        assert.deepEqual(requetes, [{ url: 'http://localhost/clients', entete: 'partielle' }]);
        assert.equal(document.querySelector('main h1').textContent, 'Clients');
        assert.equal(document.getElementById('menu-clients').getAttribute('aria-current'), 'page');
        assert.equal(document.getElementById('menu-produits').hasAttribute('aria-current'), false);
        assert.equal(document.title, 'Clients');
        assert.equal(document.querySelector('meta[name="csrf-token"]').content, 'jeton-Clients');
        assert.equal(fenetre.location.pathname, '/clients');
        assert.equal(document.body.dataset.temoin, 'conserve', 'Le reste de la page est conservé.');
        assert.equal(document.querySelector('[data-zone="menu-lateral"]').scrollTop, 120, 'Le menu garde sa position de défilement.');
        assert.equal(document.body.dataset.inits, '1', 'Les composants Alpine du nouveau contenu sont initialisés.');
        assert.deepEqual(toasts, ['succes:Client enregistré.']);
        assert.equal(document.activeElement, document.getElementById('contenu'));
        assert.equal(recharges.length, 0);
    });

    it('revient à la page précédente avec le bouton Retour', async () => {
        reponses.push(() => html(PRODUITS));
        fenetre.history.back();
        await attendre();

        assert.equal(fenetre.location.pathname, '/produits');
        assert.equal(document.querySelector('main h1').textContent, 'Produits');
        assert.equal(document.body.dataset.detruits, '1', 'Les composants de l\'ancien contenu sont détruits.');
    });

    it('envoie les formulaires GET en navigation partielle, pas les POST', async () => {
        reponses.push(() => html(page({ titre: 'Produits', actif: 'produits', contenu: '<h1>Résultats ciment</h1>' })));
        document.getElementById('filtres').requestSubmit(document.getElementById('filtrer'));
        await attendre();

        assert.equal(requetes[0].url, 'http://localhost/produits?recherche=ciment');
        assert.equal(document.querySelector('main h1').textContent, 'Résultats ciment');
        assert.equal(fenetre.location.search, '?recherche=ciment');

        reponses.push(() => html(PRODUITS));
        fenetre.history.back();
        await attendre();
        requetes = [];

        const envoi = new fenetre.SubmitEvent('submit', { bubbles: true, cancelable: true });
        document.getElementById('poste').dispatchEvent(envoi);
        assert.equal(envoi.defaultPrevented, false, 'Un formulaire POST garde l\'envoi normal.');
        assert.equal(requetes.length, 0);
    });

    it('laisse le navigateur gérer les liens externes, nouvel onglet, ancres et Ctrl+clic', () => {
        // Relevé après l'écouteur du document, puis annulé pour que happy-dom ne suive pas réellement le lien
        const releves = [];
        const relever = (e) => {
            releves.push(e.defaultPrevented);
            e.preventDefault();
        };
        fenetre.addEventListener('click', relever);
        for (const [id, init] of [['lien-externe'], ['lien-onglet'], ['lien-ancre'], ['lien-client', { ctrlKey: true }]]) {
            cliquer(id, init);
        }
        fenetre.removeEventListener('click', relever);

        assert.deepEqual(releves, [false, false, false, false], 'Aucun de ces liens ne doit être intercepté.');
        assert.equal(requetes.length, 0);
    });

    it('enregistre directement une réponse en pièce jointe, sans changer de page', async () => {
        reponses.push(() => new Response('xlsx', {
            headers: { 'content-type': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'content-disposition': 'attachment; filename=rapport-ventes.xlsx' },
        }));
        const telechargements = [];
        const original = fenetre.HTMLAnchorElement.prototype.click;
        fenetre.HTMLAnchorElement.prototype.click = function () { telechargements.push(this.download); };
        cliquer('lien-export');
        await attendre();
        fenetre.HTMLAnchorElement.prototype.click = original;

        assert.deepEqual(telechargements, ['rapport-ventes.xlsx']);
        assert.equal(fenetre.location.pathname, '/produits');
        assert.equal(document.querySelector('main h1').textContent, 'Produits');
        assert.equal(recharges.length, 0, 'Pas de seconde requête.');
    });

    it('recharge complètement une page sans la coquille (erreur, connexion) ou après un déploiement', async () => {
        reponses.push(() => html('<!DOCTYPE html><html><head><title>Accès refusé</title></head><body><main><h1>403</h1></main></body></html>'));
        cliquer('lien-erreur');
        await attendre();
        assert.deepEqual(recharges, ['http://localhost/interdit']);
        assert.equal(document.querySelector('main h1').textContent, 'Produits');

        recharges = [];
        reponses.push(() => html(page({ titre: 'Clients', actif: 'clients', contenu: '<h1>Clients</h1>', script: '/build/app-nouveau.js' })));
        cliquer('menu-clients');
        await attendre();
        assert.deepEqual(recharges, ['http://localhost/clients']);
        assert.equal(fenetre.location.pathname, '/produits');
    });
});
