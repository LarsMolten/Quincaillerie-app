/**
 * Carte chargée à la demande (tableau de bord) avec le vrai Alpine dans un DOM simulé. Lancement : npm run test:js
 */
import { after, afterEach, before, beforeEach, describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { demarrer, fenetre } from './environnement.mjs';
import carteDifferee from '../../resources/js/composants/carte-differee.js';

const PAGE = `
    <div id="carte" x-data="carteDifferee({ url: '/tableau-de-bord/stock' })">
        <div id="squelette" x-show="chargement">…</div>
        <p id="erreur" x-show="erreur">Chargement impossible.</p>
        <div id="contenu" x-ref="contenu"></div>
    </div>`;

let reponses;
let requetes;
let Alpine;
let liberer;

const attendre = async () => {
    for (let i = 0; i < 10; i++) {
        await new Promise((resolve) => setImmediate(resolve));
    }
};
const carte = () => Alpine.$data(document.getElementById('carte'));

describe('Carte chargée à la demande', () => {
    before(async () => {
        // La première réponse attend un signal : on peut observer le squelette pendant le chargement
        let signal;
        const enAttente = new Promise((resolve) => { signal = resolve; });
        liberer = signal;
        requetes = [];
        reponses = [async () => { await enAttente; return new Response('<ul><li>Ciment 50 kg</li></ul>'); }];
        globalThis.fetch = async (url, options = {}) => {
            requetes.push({ url, entete: options.headers?.['X-Fragment'] });
            return (reponses.shift() ?? (() => new Response('', { status: 500 })))();
        };
        Alpine = await demarrer(PAGE, [carteDifferee]);
    });

    after(() => fenetre.happyDOM.abort());

    beforeEach(() => {
        requetes = [];
    });

    afterEach(async () => {
        await attendre();
    });

    it('affiche le squelette pendant la requête, puis le fragment reçu', async () => {
        assert.equal(carte().chargement, true);
        assert.equal(document.getElementById('squelette').style.display, '');

        liberer();
        await attendre();

        assert.equal(carte().chargement, false);
        assert.equal(document.getElementById('squelette').style.display, 'none');
        assert.match(document.getElementById('contenu').innerHTML, /Ciment 50 kg/);
    });

    it('signale l\'échec, puis « Réessayer » recharge la carte', async () => {
        reponses = [() => new Response('', { status: 500 })];
        await carte().charger();
        assert.equal(carte().erreur, true);

        reponses = [() => new Response('<p>Clous 70 mm</p>')];
        await carte().charger();
        assert.equal(carte().erreur, false);
        assert.match(document.getElementById('contenu').innerHTML, /Clous 70 mm/);
        assert.deepEqual(requetes.map((r) => r.url), ['/tableau-de-bord/stock', '/tableau-de-bord/stock']);
        assert.equal(requetes[0].entete, 'carte');
    });

    it('recharge en revenant sur l\'onglet après plus d\'une minute, pas avant', async () => {
        reponses = [() => new Response('<p>Rafraîchi</p>')];
        document.dispatchEvent(new fenetre.Event('visibilitychange'));
        await attendre();
        assert.equal(requetes.length, 0, 'Chargé il y a moins d\'une minute : pas de nouvelle requête.');

        carte().chargeLe = Date.now() - 61_000;
        document.dispatchEvent(new fenetre.Event('visibilitychange'));
        await attendre();
        assert.equal(requetes.length, 1);
        assert.match(document.getElementById('contenu').innerHTML, /Rafraîchi/);
    });
});
