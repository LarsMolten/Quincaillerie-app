/**
 * Assistant de retour (3 étapes) avec le vrai Alpine dans un DOM simulé. Lancement : npm run test:js
 */
import { after, afterEach, before, beforeEach, describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { demarrer, fenetre } from './environnement.mjs';
import assistantRetour from '../../resources/js/composants/assistant-retour.js';

const CONFIG = {
    type: 'client',
    motifs: ['Produit défectueux', 'Erreur de produit', 'Autre'],
    modes: [{ valeur: 'especes', libelle: 'Espèces' }],
    document: null,
    urls: { recherche: '/retours/documents', enregistrer: '/retours' },
};

const RESUME = { id: 4, numero: 'VTE-2026-00004', facture: 'FAC-2026-00004', date: '9 oct. 2026', tiers: 'Rakoto BTP', total: 220000, reste: 50000 };
const DETAIL = {
    ...RESUME,
    lignes: [
        { produit_id: 1, nom: 'Ciment 50 kg', unite: 'sac', quantite: 5, retourne: 2, retournable: 3, prix_unitaire: 40000 },
        { produit_id: 2, nom: 'Clous 70 mm', unite: 'kg', quantite: 2, retourne: 0, retournable: 2, prix_unitaire: 10000 },
    ],
};

const PAGE = `
    <meta name="csrf-token" content="jeton-essai">
    <div id="assistant" x-data="assistantRetour(${JSON.stringify(CONFIG).replace(/"/g, '&quot;')})"></div>`;

let requetes;
let toasts;
let reponseCreation;
let Alpine;

const attendre = async () => {
    for (let i = 0; i < 10; i++) {
        await new Promise((resolve) => setImmediate(resolve));
    }
};
const assistant = () => Alpine.$data(document.getElementById('assistant'));

describe('Assistant de retour', () => {
    before(async () => {
        globalThis.fetch = (...args) => globalThis.fetchEssai(...args);
        globalThis.fetchEssai = async () => Response.json([RESUME]);
        Alpine = await demarrer(PAGE, [assistantRetour]);
        await attendre();
    });

    after(() => {
        delete globalThis.fetch;
        fenetre.happyDOM.abort();
    });

    beforeEach(() => {
        requetes = [];
        toasts = [];
        reponseCreation = () => Response.json({ message: 'Retour RET-2026-00001 enregistré : stock mis à jour.', numero: 'RET-2026-00001', total: 120000, avoir: 50000, rembourse: 70000, url: '/retours/1', url_bon: '/retours/1/bon' }, { status: 201 });
        fenetre.toast = (type, message) => toasts.push({ type, message });
        globalThis.fetchEssai = async (url, options = {}) => {
            requetes.push({ url, methode: options.method ?? 'GET', corps: options.body, csrf: options.headers?.['X-CSRF-TOKEN'] });
            if (url.startsWith('/retours/documents') && url.includes('id=4')) {
                return Response.json(DETAIL);
            }
            if (url.startsWith('/retours/documents')) {
                return Response.json([RESUME]);
            }
            if (url === '/retours') {
                return reponseCreation();
            }

            return new Response('', { status: 404 });
        };
    });

    afterEach(() => {
        globalThis.fetchEssai = async () => Response.json([]);
    });

    it('étape 1 : liste les documents puis passe à l\'étape 2 au choix d\'une vente', async () => {
        assert.equal(assistant().etape, 1);
        assert.equal(assistant().resultats[0].numero, 'VTE-2026-00004');

        await assistant().choisir(4);
        assert.equal(assistant().etape, 2);
        assert.equal(assistant().document.lignes.length, 2);
        assert.ok(requetes[0].url.includes('type=client') && requetes[0].url.includes('id=4'));
    });

    it('étape 2 : quantité bornée au retournable, motif exigé (« Autre » demande une précision)', async () => {
        const a = assistant();
        const ciment = a.document.lignes[0];

        a.quantites[1] = '9';
        a.borner(ciment);
        assert.equal(a.quantites[1], '3', 'Ramené au retournable.');
        assert.equal(toasts[0].type, 'alerte');

        a.suivant();
        assert.equal(a.etape, 2, 'Pas de motif : on reste à l\'étape 2.');
        assert.match(a.erreur, /motif/);

        a.motif = 'Autre';
        assert.equal(a.peutContinuer, false);
        a.precision = 'Sac déchiré';
        a.quantites[2] = '0';
        assert.equal(a.total, 120000, '3 × 40 000.');
        a.suivant();
        assert.equal(a.etape, 3);
    });

    it('étape 3 : répartit avoir et remboursement, exige le mode puis envoie en JSON avec le jeton CSRF', async () => {
        const a = assistant();
        assert.equal(a.avoir, 50000, 'Limité au reste de la vente.');
        assert.equal(a.rembourse, 70000);
        assert.equal(a.peutValider, false, 'Mode de remboursement obligatoire.');

        a.mode = 'especes';
        await a.enregistrer();
        await attendre();

        const envoi = requetes.find((r) => r.url === '/retours');
        assert.equal(envoi.methode, 'POST');
        assert.equal(envoi.csrf, 'jeton-essai');
        assert.deepEqual(JSON.parse(envoi.corps), {
            type: 'client',
            vente_id: 4,
            lignes: [{ produit_id: 1, quantite: 3 }],
            motif: 'Autre',
            precision: 'Sac déchiré',
            mode_remboursement: 'especes',
        });
        assert.equal(a.reussite.numero, 'RET-2026-00001');
        assert.equal(toasts[0].type, 'succes');
    });

    it('affiche le refus du serveur sans perdre la saisie, et revient aux étapes précédentes', async () => {
        const a = assistant();
        a.reussite = null;
        reponseCreation = () => Response.json({ message: 'Refusé', errors: { lignes: ['Retour impossible pour « Ciment 50 kg » : 1 retournable(s) sur 5, 3 demandé(s).'] } }, { status: 422 });

        await a.enregistrer();
        assert.match(a.erreur, /Retour impossible/);
        assert.equal(a.etape, 3);
        assert.equal(a.quantites[1], '3');

        a.retour(2);
        assert.equal(a.etape, 2);
        a.retour(3);
        assert.equal(a.etape, 2, 'On ne saute pas en avant.');
    });
});
