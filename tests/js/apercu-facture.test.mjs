/**
 * Composant « factures » (page Factures) avec le vrai Alpine dans un DOM simulé. Lancement : npm run test:js
 * L'aperçu ne charge jamais l'URL du PDF dans l'iframe (interceptée par les gestionnaires de téléchargement) :
 * PDF demandé en JSON, reconstruit localement, affiché depuis une URL blob:.
 */
import { after, afterEach, before, beforeEach, describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { resolveObjectURL } from 'node:buffer';
import { demarrer, fenetre } from './environnement.mjs';
import factures from '../../resources/js/composants/factures.js';

const PAGE = `
    <meta name="csrf-token" content="jeton-essai">
    <div id="page" x-data="factures({ urlFiche: '/factures/__ID__', apercu: null })"
         x-on:facture-apercu.window="ouvrirApercu($event.detail)">
        <iframe id="facture-cadre" x-bind:src="adresse"></iframe>
    </div>`;

const FICHE = {
    id: 7,
    numero: 'FAC-2026-00007',
    client: 'Rakoto BTP',
    email: 'compta@rakoto.mg',
    urls: { a4: '/factures/7/pdf', ticket: '/factures/7/pdf?format=ticket', envoyer: '/factures/7/envoi', partager: '/factures/7/partage' },
};

let requetes;
let modales;
let toasts;

const attendre = async () => {
    for (let i = 0; i < 10; i++) {
        await new Promise((resolve) => setImmediate(resolve));
    }
};
let Alpine;
const composant = () => Alpine.$data(document.getElementById('page'));

describe('Aperçu des factures', () => {
    before(async () => {
        Alpine = await demarrer(PAGE, [factures]);
    });

    after(() => fenetre.happyDOM.abort());

    beforeEach(() => {
        requetes = [];
        modales = [];
        toasts = [];
        fenetre.toast = (type, message) => toasts.push({ type, message });
        fenetre.addEventListener('ouvrir-modal', (e) => modales.push(e.detail));
        globalThis.fetch = async (url, options = {}) => {
            requetes.push({ url, methode: options.method ?? 'GET', accept: options.headers?.Accept, corps: options.body, csrf: options.headers?.['X-CSRF-TOKEN'] });
            if (url === '/factures/7') {
                return Response.json(FICHE);
            }
            if (url.startsWith('/factures/7/pdf')) {
                const contenu = url.includes('ticket') ? '%PDF ticket' : '%PDF a4';
                return Response.json({ nom: 'facture-FAC-2026-00007.pdf', pdf: Buffer.from(contenu).toString('base64') });
            }
            if (url === '/factures/7/envoi') {
                return Response.json({ message: 'Facture FAC-2026-00007 envoyée à compta@rakoto.mg.' });
            }

            return new Response('', { status: 404 });
        };
    });

    afterEach(() => {
        delete globalThis.fetch;
    });

    it('affiche le PDF depuis une URL blob:, demandé en JSON', async () => {
        fenetre.dispatchEvent(new fenetre.CustomEvent('facture-apercu', { detail: 7 }));
        await attendre();

        assert.ok(modales.includes('facture-apercu'), 'La modale d\'aperçu est ouverte.');
        const pdf = requetes.find((r) => r.url === '/factures/7/pdf');
        assert.equal(pdf?.accept, 'application/json', 'Le PDF est demandé en JSON, jamais tel quel.');

        const cadre = document.getElementById('facture-cadre');
        assert.match(cadre.getAttribute('src') ?? '', /^blob:/, 'L\'iframe affiche une URL blob:.');
        assert.equal(await resolveObjectURL(cadre.getAttribute('src')).text(), '%PDF a4');
    });

    it('bascule au format ticket 80 mm', async () => {
        await composant().afficher('ticket');
        await attendre();
        assert.equal(await resolveObjectURL(document.getElementById('facture-cadre').getAttribute('src')).text(), '%PDF ticket');
    });

    it('envoie l\'email en JSON avec le jeton CSRF et l\'adresse du client préremplie', async () => {
        await composant().ouvrirEnvoi(7);
        assert.equal(composant().envoi.email, 'compta@rakoto.mg');

        await composant().envoyer();
        const envoi = requetes.find((r) => r.url === '/factures/7/envoi');
        assert.equal(envoi.methode, 'POST');
        assert.equal(envoi.csrf, 'jeton-essai');
        assert.deepEqual(JSON.parse(envoi.corps), { email: 'compta@rakoto.mg', message: '' });
        assert.deepEqual(toasts, [{ type: 'succes', message: 'Facture FAC-2026-00007 envoyée à compta@rakoto.mg.' }]);
    });
});
