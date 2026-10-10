/**
 * Directive x-ouvrir-pdf (ticket de caisse, bon d'achat, étiquettes), avec le vrai Alpine dans un DOM simulé.
 * Lancement : npm run test:js
 *
 * Régression : « Imprimer le ticket » était un simple lien target="_blank" vers le PDF. Un gestionnaire
 * de téléchargement intégré au navigateur (Internet Download Manager) intercepte toute réponse
 * application/pdf (navigation ou fetch) et la remplace par un 204 : l'onglet se refermait et l'utilisateur
 * revenait sur la caisse sans ticket. Le clic ne doit donc JAMAIS charger le PDF tel quel : onglet vierge,
 * PDF demandé en JSON (base64), reconstruit localement et affiché depuis une URL blob:.
 */
import { after, afterEach, before, beforeEach, describe, it, mock } from 'node:test';
import assert from 'node:assert/strict';
import { resolveObjectURL } from 'node:buffer';
import { demarrer, fenetre } from './environnement.mjs';
import ouvrirPdf from '../../resources/js/composants/ouvrir-pdf.js';

// Même balisage que l'écran de réussite de la caisse et le formulaire d'étiquettes
const PAGE = `
    <div x-data="{ reussite: { url_ticket: '/ventes/7/ticket' } }">
        <a id="imprimer" href="#" x-bind:href="reussite.url_ticket" target="_blank" x-ouvrir-pdf="reussite.url_ticket">Imprimer le ticket</a>
        <form id="etiquettes" method="GET" action="/produits/etiquettes" target="_blank" x-ouvrir-pdf>
            <input type="hidden" name="produits[]" value="12">
            <input type="hidden" name="quantite" value="3">
        </form>
    </div>`;

const CONTENU_PDF = '%PDF-1.7 ticket';
let onglets;
let requetes;
let toasts;
let reponse;

/** Faux onglet renvoyé par window.open('', '_blank'). */
const nouvelOnglet = () => {
    const onglet = {
        ferme: false,
        adresse: null,
        document: { title: '', body: { textContent: '', style: {} } },
        location: { replace: (url) => { onglet.adresse = url; } },
        close: () => { onglet.ferme = true; },
    };
    onglets.push(onglet);

    return onglet;
};

const cliquer = (options = {}) => document.getElementById('imprimer')
    .dispatchEvent(new fenetre.MouseEvent('click', { bubbles: true, cancelable: true, button: 0, ...options }));

// Laisse se terminer fetch() et la lecture du PDF (les minuteurs sont simulés : setImmediate)
const attendreTraitement = async () => {
    for (let i = 0; i < 10; i++) {
        await new Promise((resolve) => setImmediate(resolve));
    }
};

describe('Directive x-ouvrir-pdf', () => {
    before(() => demarrer(PAGE, [ouvrirPdf]));

    after(() => fenetre.happyDOM.abort());

    beforeEach(() => {
        // Le délai de libération de l'URL blob: (5 min) ne doit pas garder le processus en vie
        mock.timers.enable({ apis: ['setTimeout'] });
        onglets = [];
        requetes = [];
        toasts = [];
        // Réponse de App\Support\ReponsePdf à une demande JSON
        reponse = () => Response.json({ nom: 'ticket-VTE-2026-00007.pdf', pdf: Buffer.from(CONTENU_PDF).toString('base64') });
        fenetre.open = () => nouvelOnglet();
        fenetre.toast = (type, message) => toasts.push({ type, message });
        globalThis.fetch = async (url, options) => {
            requetes.push({ url, accept: options?.headers?.Accept });
            return reponse();
        };
    });

    afterEach(() => {
        mock.timers.reset();
        delete globalThis.fetch;
    });

    it('« Imprimer le ticket » ne navigue pas vers le PDF (interceptable par un gestionnaire de téléchargement)', async () => {
        const navigationNative = cliquer();

        assert.equal(navigationNative, false, 'Le clic ne doit pas suivre le lien vers le PDF : la navigation native doit être annulée.');
        await attendreTraitement();
        assert.equal(onglets.length, 1, 'Un seul nouvel onglet est ouvert au clic.');
        assert.deepEqual(requetes, [{ url: 'http://localhost/ventes/7/ticket', accept: 'application/json' }],
            'Le PDF est demandé en JSON : aucune réponse application/pdf à intercepter.');
        assert.match(onglets[0].adresse ?? '', /^blob:/, 'Le PDF est affiché depuis une URL blob:, jamais depuis l\'URL du ticket.');
        assert.equal(onglets[0].ferme, false);

        const pdf = resolveObjectURL(onglets[0].adresse);
        assert.equal(pdf.type, 'application/pdf');
        assert.equal(await pdf.text(), CONTENU_PDF, 'Le PDF reconstruit est identique à celui du serveur.');
    });

    it('la page de départ ne change pas', async () => {
        const avant = fenetre.location.href;
        cliquer();
        await attendreTraitement();
        assert.equal(fenetre.location.href, avant);
    });

    it('Ctrl + clic garde le comportement natif du navigateur', async () => {
        assert.equal(cliquer({ ctrlKey: true }), true, 'La navigation native n\x27est pas annulée.');
        await attendreTraitement();
        assert.deepEqual(requetes, [], 'La directive ne récupère pas le PDF.');
        assert.ok(onglets.every((onglet) => onglet.adresse === null));
    });

    it('ferme l\'onglet et affiche un toast si le document est refusé', async () => {
        reponse = () => new Response('<html>Interdit</html>', { status: 403, headers: { 'Content-Type': 'text/html' } });
        cliquer();
        await attendreTraitement();
        assert.equal(onglets[0].ferme, true);
        assert.deepEqual(toasts, [{ type: 'erreur', message: 'Vous n\'avez pas le droit d\'ouvrir ce document.' }]);
    });

    it('formulaire GET (étiquettes) : mêmes règles, paramètres conservés', async () => {
        const formulaire = document.getElementById('etiquettes');
        const navigationNative = formulaire.dispatchEvent(new fenetre.Event('submit', { bubbles: true, cancelable: true }));

        assert.equal(navigationNative, false);
        await attendreTraitement();
        assert.equal(requetes[0].url, 'http://localhost/produits/etiquettes?produits%5B%5D=12&quantite=3');
        assert.match(onglets[0].adresse ?? '', /^blob:/);
    });
});
