/**
 * Caisse sans connexion Internet, avec le vrai Alpine dans un DOM simulé. Lancement : npm run test:js
 * Bug : la validation était bloquée dès que navigator.onLine passait à false (Internet coupé sur le poste),
 * alors que le serveur de l'application (local) restait joignable. Seul l'échec réel d'une requête compte.
 */
import { after, afterEach, before, beforeEach, describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { demarrer, fenetre } from './environnement.mjs';
import caisse from '../../resources/js/composants/caisse.js';

const CONFIG = {
    urls: { catalogue: '/ventes/catalogue', clients: '/ventes/clients', enregistrer: '/ventes' },
    comptoir: { id: 1, nom: 'Client comptoir', telephone: null, comptoir: true, creance: 0, plafond: null, disponible: 0 },
    remise: { autorisee: false, plafond: 0 },
    stockNegatif: false,
    cleStockage: 'caisse-ticket-essai',
};

const CIMENT = { produit_id: 5, nom: 'Ciment Holcim', reference: 'CIM-001', unite: 'sac', prix_vente: 38000, prix_gros: null, stock: 40 };

const PAGE = `
    <meta name="csrf-token" content="jeton-essai">
    <div id="caisse" x-data="caisse(${JSON.stringify(CONFIG).replace(/"/g, '&quot;')})"></div>`;

let requetes;
let serveurJoignable;
let Alpine;

const attendre = async () => {
    for (let i = 0; i < 10; i++) {
        await new Promise((resolve) => setImmediate(resolve));
    }
};
const composant = () => Alpine.$data(document.getElementById('caisse'));

/** Internet coupé sur le poste : navigator.onLine = false et événement « offline ». */
const couperInternet = () => {
    Object.defineProperty(fenetre.navigator, 'onLine', { value: false, configurable: true });
    fenetre.dispatchEvent(new fenetre.Event('offline'));
};

const preparerTicket = () => {
    const caisse = composant();
    caisse.ajouter(CIMENT);
    caisse.mode = 'especes';
    caisse.recu = '50000';
};

describe('Caisse sans connexion Internet', () => {
    before(async () => {
        fenetre.matchMedia = () => ({ matches: true, addEventListener() {}, removeEventListener() {} });
        globalThis.fetch = (...args) => globalThis.fetchEssai(...args);
        globalThis.fetchEssai = async () => Response.json([]);
        Alpine = await demarrer(PAGE, [caisse]);
        await attendre();
    });

    after(() => {
        delete globalThis.fetch;
        fenetre.happyDOM.abort();
    });

    beforeEach(() => {
        requetes = [];
        serveurJoignable = true;
        fenetre.toast = () => {};
        globalThis.fetchEssai = async (url, options = {}) => {
            requetes.push({ url, methode: options.method ?? 'GET' });
            if (!serveurJoignable) {
                throw new TypeError('Failed to fetch');
            }
            if (url === '/ventes' && options.method === 'POST') {
                return Response.json({ numero: 'VTE-2026-00001', facture: 'FAC-2026-00001', url_ticket: '/ventes/1/ticket' }, { status: 201 });
            }

            return Response.json([CIMENT]);
        };
    });

    afterEach(() => {
        Object.defineProperty(fenetre.navigator, 'onLine', { value: true, configurable: true });
        composant().reussite = null;
        composant().erreur = '';
    });

    it('valide la vente quand Internet est coupé mais le serveur local répond', async () => {
        couperInternet();
        preparerTicket();

        assert.equal(composant().peutValider, true, 'La validation n\'est pas bloquée par navigator.onLine.');
        await composant().enregistrer();

        assert.ok(requetes.some((r) => r.url === '/ventes' && r.methode === 'POST'), 'La vente est envoyée au serveur.');
        assert.equal(composant().reussite?.numero, 'VTE-2026-00001');
        assert.equal(composant().reussite?.url_ticket, '/ventes/1/ticket', 'Le ticket est disponible.');
        assert.equal(composant().serveurInjoignable, false);
    });

    it('serveur réellement injoignable : vente non enregistrée, ticket conservé, nouvelle tentative possible', async () => {
        preparerTicket();
        serveurJoignable = false;

        await composant().enregistrer();
        assert.equal(composant().reussite, null);
        assert.equal(composant().serveurInjoignable, true);
        assert.match(composant().erreur, /Serveur injoignable/);
        assert.equal(composant().lignes.length, 1, 'Le ticket est conservé.');
        assert.equal(composant().peutValider, true, 'Le vendeur peut réessayer.');

        serveurJoignable = true;
        await composant().enregistrer();
        assert.equal(composant().reussite?.numero, 'VTE-2026-00001');
        assert.equal(composant().serveurInjoignable, false);
        assert.equal(composant().lignes.length, 0);
    });
});
