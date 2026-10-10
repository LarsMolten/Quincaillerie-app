/**
 * Saisie du comptage d'inventaire (tablette) avec le vrai Alpine dans un DOM simulé. Lancement : npm run test:js
 */
import { after, afterEach, before, beforeEach, describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { demarrer, fenetre } from './environnement.mjs';
import comptageInventaire from '../../resources/js/composants/comptage-inventaire.js';

const ligne = (id, nom, actuel, extra = {}) => ({
    id, nom, reference: `REF-${id}`, code_barres: '', categorie: 'Divers', unite: 'u', theorique: actuel, actuel, compte: null, ecart: null, ...extra,
});
const CONFIG = {
    lignes: [ligne(1, 'Ciment', 40), ligne(2, 'Clous', 20), ligne(3, 'Vis', 100, { compte: 100, ecart: 0 })],
    lectureSeule: false,
    urlLigne: '/inventaires/9/lignes/__LIGNE__',
};

const PAGE = `
    <meta name="csrf-token" content="jeton-essai">
    <div id="comptage" x-data="comptageInventaire(${JSON.stringify(CONFIG).replace(/"/g, '&quot;')})">
        <template x-for="l in visibles" x-bind:key="l.id">
            <input x-bind:id="'compte-' + l.id" x-model="l.saisie" x-on:keydown.enter.prevent="toucheEntree(l)">
        </template>
    </div>`;

let requetes;
let reponse;
let Alpine;

const attendre = async () => {
    for (let i = 0; i < 10; i++) {
        await new Promise((resolve) => setImmediate(resolve));
    }
};
const comptage = () => Alpine.$data(document.getElementById('comptage'));
const entree = (id) => document.getElementById(`compte-${id}`).dispatchEvent(new fenetre.KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));

describe('Comptage d\'inventaire', () => {
    before(async () => {
        Alpine = await demarrer(PAGE, [comptageInventaire]);
    });

    after(() => fenetre.happyDOM.abort());

    beforeEach(() => {
        requetes = [];
        reponse = (corps) => Response.json({ compte: corps.quantite, ecart: corps.quantite === null ? null : corps.quantite - 40, actuel: 40 });
        globalThis.fetch = async (url, options = {}) => {
            const corps = JSON.parse(options.body);
            requetes.push({ url, methode: options.method, corps, csrf: options.headers?.['X-CSRF-TOKEN'] });
            return reponse(corps);
        };
    });

    afterEach(() => {
        delete globalThis.fetch;
    });

    it('progression : comptés / total', () => {
        assert.equal(comptage().total, 3);
        assert.equal(comptage().comptees, 1);
        assert.equal(comptage().pourcentage, 33);
    });

    it('Entrée enregistre la ligne en JSON (PATCH + CSRF) et passe au champ suivant', async () => {
        const champ = document.getElementById('compte-1');
        champ.focus();
        comptage().lignes[0].saisie = '37,5';
        entree(1);
        await attendre();

        assert.equal(document.activeElement?.id, 'compte-2', 'Le focus passe à la ligne suivante.');
        assert.deepEqual(requetes, [{ url: '/inventaires/9/lignes/1', methode: 'PATCH', corps: { quantite: 37.5 }, csrf: 'jeton-essai' }]);
        const ciment = comptage().lignes[0];
        assert.equal(ciment.compte, 37.5);
        assert.equal(ciment.ecart, -2.5);
        assert.equal(ciment.etat, 'ok');
        assert.equal(comptage().couleur(ciment), 'text-danger-texte', 'Écart négatif en rouge.');
        assert.equal(comptage().ecartTexte(ciment), '−2,5 u');
        assert.equal(comptage().comptees, 2);
    });

    it('une saisie inchangée n\'est pas renvoyée ; une saisie invalide est signalée sans envoi', async () => {
        await comptage().enregistrer(comptage().lignes[0]);
        assert.equal(requetes.length, 0);

        comptage().lignes[1].saisie = 'abc';
        await comptage().enregistrer(comptage().lignes[1]);
        assert.equal(requetes.length, 0);
        assert.equal(comptage().lignes[1].etat, 'erreur');
    });

    it('filtres « non comptés » et « avec écart », recherche', async () => {
        comptage().filtre = 'non_comptes';
        assert.deepEqual(comptage().visibles.map((l) => l.id), [2]);
        comptage().filtre = 'ecarts';
        assert.deepEqual(comptage().visibles.map((l) => l.id), [1]);
        comptage().filtre = 'tous';
        comptage().recherche = 'ref-3';
        assert.deepEqual(comptage().visibles.map((l) => l.id), [3]);
        comptage().recherche = '';
    });

    it('signale le refus du serveur sur la ligne', async () => {
        reponse = () => Response.json({ errors: { quantite: ['L\'inventaire INV-2026-00001 est validé : il n\'est plus modifiable.'] } }, { status: 422 });
        const clous = comptage().lignes[1];
        clous.saisie = '18';
        await comptage().enregistrer(clous);

        assert.equal(clous.etat, 'erreur');
        assert.match(clous.message, /n'est plus modifiable/);
        assert.equal(clous.compte, null, 'Rien n\'est considéré comme compté.');
    });
});
