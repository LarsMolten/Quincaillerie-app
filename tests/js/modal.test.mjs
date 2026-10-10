/**
 * Composant Alpine « modal » (x-modal, x-panneau, tiroir de navigation) exécuté avec le vrai Alpine
 * dans un DOM simulé (happy-dom). Lancement : npm run test:js
 *
 * Régression : le bouton ✕ appelait fermer() depuis un x-on:click posé sur le bouton ; dans ce cas
 * Alpine résout this.$el sur le bouton (et non sur le <dialog>), si bien que la modale restait ouverte.
 */
import { after, before, beforeEach, describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { demarrer, fenetre } from './environnement.mjs';
import modal from '../../resources/js/composants/modal.js';

// Même structure que resources/views/components/modal.blade.php
const MODALE = `
    <button id="declencheur" type="button" x-data x-on:click="$dispatch('ouvrir-modal', 'essai')">Ouvrir</button>
    <dialog id="essai" x-data="modal('essai')" x-on:click="clicFond($event)">
        <div class="p-6">
            <header>
                <h2>Titre</h2>
                <button id="bouton-fermer" type="button" x-on:click="fermer()" aria-label="Fermer">✕</button>
            </header>
            <p id="contenu">Contenu</p>
            <button id="bouton-annuler" type="button" x-on:click="$dispatch('fermer-modal', 'essai')">Annuler</button>
        </div>
    </dialog>`;

const dialogue = () => document.getElementById('essai');
const cliquer = (id) => document.getElementById(id).dispatchEvent(new fenetre.MouseEvent('click', { bubbles: true }));
const ouvrir = async () => {
    cliquer('declencheur');
    await fenetre.happyDOM.waitUntilComplete();
    assert.equal(dialogue().open, true, 'La modale doit être ouverte avant le test.');
};

describe('Composant modal', () => {
    before(() => demarrer(MODALE, [modal]));

    after(() => fenetre.happyDOM.abort());

    beforeEach(() => {
        if (dialogue().open) {
            dialogue().close();
        }
    });

    it('s\'ouvre par l\'événement ouvrir-modal', async () => {
        await ouvrir();
    });

    it('se ferme avec le bouton ✕', async () => {
        await ouvrir();
        cliquer('bouton-fermer');
        assert.equal(dialogue().open, false, 'Le bouton ✕ doit fermer la modale.');
    });

    it('se ferme par un clic sur le voile (le <dialog> lui-même)', async () => {
        await ouvrir();
        dialogue().dispatchEvent(new fenetre.MouseEvent('click', { bubbles: true }));
        assert.equal(dialogue().open, false);
    });

    it('reste ouverte lors d\'un clic dans son contenu', async () => {
        await ouvrir();
        cliquer('contenu');
        assert.equal(dialogue().open, true);
    });

    it('se ferme par l\'événement fermer-modal (boutons du pied)', async () => {
        await ouvrir();
        cliquer('bouton-annuler');
        assert.equal(dialogue().open, false);
    });
});
