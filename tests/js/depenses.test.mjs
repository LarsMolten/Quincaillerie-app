/**
 * Composant « depenses » (modale de saisie / modification, suppression) avec le vrai Alpine dans un DOM simulé.
 * Lancement : npm run test:js
 */
import { after, afterEach, before, beforeEach, describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { demarrer, fenetre } from './environnement.mjs';
import depenses from '../../resources/js/composants/depenses.js';

const PAGE = `
    <meta name="csrf-token" content="jeton-essai">
    <div id="page" x-data="depenses({ urlCreation: '/depenses' })"></div>`;

let requetes;
let evenements;
let toasts;
let reponse;
let Alpine;

const page = () => Alpine.$data(document.getElementById('page'));

describe('Dépenses : modale de saisie et suppression', () => {
    before(async () => {
        for (const nom of ['ouvrir-modal', 'fermer-modal', 'depenses-modifiees']) {
            fenetre.addEventListener(nom, (e) => evenements.push([nom, e.detail]));
        }
        Alpine = await demarrer(PAGE, [depenses]);
    });

    after(() => fenetre.happyDOM.abort());

    beforeEach(() => {
        requetes = [];
        evenements = [];
        toasts = [];
        reponse = () => Response.json({ message: 'Dépense « Loyer » enregistrée : 600 000 Ar.' }, { status: 201 });
        fenetre.toast = (type, message) => toasts.push({ type, message });
        globalThis.fetch = async (url, options = {}) => {
            requetes.push({ url, methode: options.method, corps: options.body ? JSON.parse(options.body) : null, csrf: options.headers?.['X-CSRF-TOKEN'] });
            return reponse();
        };
    });

    afterEach(() => {
        delete globalThis.fetch;
    });

    it('« Nouvelle dépense » ouvre une modale vide datée du jour, payée en espèces', () => {
        page().formulaire.libelle = 'reste d\'une saisie';
        page().nouvelle();

        assert.deepEqual(evenements, [['ouvrir-modal', 'modale-depense']]);
        assert.equal(page().cible, null);
        assert.equal(page().formulaire.libelle, '');
        assert.equal(page().formulaire.mode_paiement, 'especes');
        assert.match(page().formulaire.date_depense, /^\d{4}-\d{2}-\d{2}$/);
    });

    it('crée en POST JSON avec le jeton CSRF, ferme la modale et recharge la liste', async () => {
        Object.assign(page().formulaire, { categorie: 'loyer', libelle: 'Loyer', montant: '600 000' });
        await page().enregistrer();

        assert.equal(requetes[0].url, '/depenses');
        assert.equal(requetes[0].methode, 'POST');
        assert.equal(requetes[0].csrf, 'jeton-essai');
        assert.equal(requetes[0].corps.montant, '600 000');
        assert.deepEqual(evenements.map(([nom]) => nom), ['fermer-modal', 'depenses-modifiees']);
        assert.equal(toasts[0].type, 'succes');
    });

    it('modifie en PUT sur l\'adresse de la ligne, formulaire prérempli', async () => {
        page().modifier({ url: '/depenses/7', categorie: 'eau', libelle: 'JIRAMA eau', montant: 30000, date_depense: '2026-10-02', mode_paiement: 'virement', notes: null });

        assert.equal(page().formulaire.montant, '30000');
        assert.equal(page().formulaire.notes, '');
        assert.equal(page().formulaire.categorie, 'eau');

        reponse = () => Response.json({ message: 'Dépense « JIRAMA eau » modifiée.' });
        await page().enregistrer();
        assert.equal(requetes[0].url, '/depenses/7');
        assert.equal(requetes[0].methode, 'PUT');
    });

    it('affiche les erreurs de validation sans fermer la modale', async () => {
        reponse = () => Response.json({ message: 'Erreur', errors: { montant: ['Le montant doit être supérieur à 0.'] } }, { status: 422 });
        page().nouvelle();
        evenements = [];
        await page().enregistrer();

        assert.deepEqual(page().erreurs.montant, ['Le montant doit être supérieur à 0.']);
        assert.deepEqual(evenements, []);
        assert.equal(page().envoi, false);
    });

    it('supprime après confirmation (DELETE) ; un refus est signalé par un toast', async () => {
        page().confirmerSuppression({ url: '/depenses/7', libelle: 'JIRAMA eau', montant: '30 000 Ar' });
        reponse = () => Response.json({ message: 'Dépense « JIRAMA eau » supprimée.' });
        await page().supprimer();
        assert.equal(requetes[0].methode, 'DELETE');
        assert.ok(evenements.some(([nom]) => nom === 'depenses-modifiees'));

        page().confirmerSuppression({ url: '/depenses/8', libelle: 'Autre', montant: '1 Ar' });
        reponse = () => Response.json({ message: 'Seul l\'Administrateur peut supprimer une dépense.' }, { status: 403 });
        await page().supprimer();
        assert.deepEqual(toasts.at(-1), { type: 'erreur', message: 'Seul l\'Administrateur peut supprimer une dépense.' });
    });
});
