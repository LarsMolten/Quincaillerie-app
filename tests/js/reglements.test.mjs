/**
 * Composant « reglements » (pages Créances et Dettes fournisseurs) avec le vrai Alpine dans un DOM simulé.
 * Lancement : npm run test:js
 */
import { after, afterEach, before, beforeEach, describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { demarrer, fenetre } from './environnement.mjs';
import reglements from '../../resources/js/composants/reglements.js';

const PAGE = `
    <meta name="csrf-token" content="jeton-essai">
    <div id="page" x-data="reglements">
        <div id="detail-7" x-html="details[7]?.html ?? ''"></div>
    </div>`;

const DETAIL = `<button id="encaisser" type="button"
    x-on:click="ouvrirReglement({ url: '/ventes/3/paiements', document: 'FAC-2026-00012', tiers: 'Rakoto BTP', reste: 45000 })">Encaisser</button>`;

let requetes;
let modales;
let toasts;
let rafraichissements;
let reponsePaiement;
let Alpine;

const attendre = async () => {
    for (let i = 0; i < 10; i++) {
        await new Promise((resolve) => setImmediate(resolve));
    }
};
const composant = () => Alpine.$data(document.getElementById('page'));

describe('Créances et dettes : détail et modale de paiement', () => {
    before(async () => {
        fenetre.addEventListener('ouvrir-modal', (e) => modales.push(['ouvrir', e.detail]));
        fenetre.addEventListener('fermer-modal', (e) => modales.push(['fermer', e.detail]));
        fenetre.addEventListener('reglement-enregistre', () => rafraichissements++);
        Alpine = await demarrer(PAGE, [reglements]);
    });

    after(() => fenetre.happyDOM.abort());

    beforeEach(() => {
        requetes = [];
        modales = [];
        toasts = [];
        rafraichissements = 0;
        reponsePaiement = () => Response.json({ message: 'Paiement REC-2026-00004 enregistré.', numero: 'REC-2026-00004', reste: 25000, url_recu: '/paiements/4/recu' });
        fenetre.toast = (type, message, duree, lien) => toasts.push({ type, message, lien });
        globalThis.fetch = async (url, options = {}) => {
            requetes.push({ url, methode: options.method ?? 'GET', corps: options.body, csrf: options.headers?.['X-CSRF-TOKEN'], fragment: options.headers?.['X-Fragment'] });
            if (url === '/creances/7') {
                return new Response(DETAIL, { headers: { 'Content-Type': 'text/html' } });
            }
            if (url === '/ventes/3/paiements') {
                return reponsePaiement();
            }

            return new Response('', { status: 404 });
        };
    });

    afterEach(() => {
        delete globalThis.fetch;
    });

    it('déplie le détail d\'un client (fragment chargé une seule fois) et le replie', async () => {
        await composant().basculer(7, '/creances/7');
        await attendre();
        assert.equal(composant().details[7].ouvert, true);
        assert.ok(document.getElementById('encaisser'), 'Les factures impayées sont affichées.');

        await composant().basculer(7, '/creances/7');
        assert.equal(composant().details[7].ouvert, false);
        await composant().basculer(7, '/creances/7');
        assert.equal(requetes.filter((r) => r.url === '/creances/7').length, 1, 'Le détail déjà chargé n\'est pas redemandé.');
    });

    it('« Encaisser » ouvre la modale préremplie avec le reste à payer', async () => {
        document.getElementById('encaisser').click();
        await attendre();

        assert.deepEqual(modales, [['ouvrir', 'modale-reglement']]);
        const { reglement } = composant();
        assert.equal(reglement.document, 'FAC-2026-00012');
        assert.equal(reglement.montant, '45000');
        assert.equal(reglement.mode, 'especes');
        assert.match(reglement.date, /^\d{4}-\d{2}-\d{2}$/);
    });

    it('enregistre en JSON avec le jeton CSRF, propose le reçu et recharge la liste et le détail ouvert', async () => {
        Object.assign(composant().reglement, { montant: '20000', mode: 'mobile_money', reference: 'MVOLA-5' });
        await composant().enregistrer();
        await attendre();

        const envoi = requetes.find((r) => r.url === '/ventes/3/paiements');
        assert.equal(envoi.methode, 'POST');
        assert.equal(envoi.csrf, 'jeton-essai');
        assert.deepEqual(JSON.parse(envoi.corps), { montant: '20000', mode: 'mobile_money', reference: 'MVOLA-5', date_paiement: composant().reglement.date });

        assert.deepEqual(modales, [['fermer', 'modale-reglement']]);
        assert.equal(toasts[0].type, 'succes');
        assert.deepEqual(toasts[0].lien, { libelle: 'Imprimer le reçu', url: '/paiements/4/recu', nouvelOnglet: true, pdf: true });
        assert.equal(rafraichissements, 1, 'La liste (synthèse comprise) est rechargée.');
        assert.equal(requetes.filter((r) => r.url === '/creances/7').length, 1, 'Le détail ouvert est rechargé.');
    });

    it('affiche le refus du serveur sous le champ montant, sans fermer la modale', async () => {
        reponsePaiement = () => Response.json({ message: 'Refusé', errors: { montant: ['Le paiement (90 000 Ar) dépasse le reste à payer.'] } }, { status: 422 });
        composant().reglement.montant = '90000';
        await composant().enregistrer();

        assert.deepEqual(composant().reglement.erreurs.montant, ['Le paiement (90 000 Ar) dépasse le reste à payer.']);
        assert.equal(composant().reglement.enCours, false);
        assert.deepEqual(modales, []);
        assert.equal(rafraichissements, 0);
    });
});
