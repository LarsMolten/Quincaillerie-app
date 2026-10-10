import Alpine from 'alpinejs';
import assistantRetour from './composants/assistant-retour';
import bouton from './composants/bouton';
import caisse from './composants/caisse';
import coquille from './composants/coquille';
import factures from './composants/factures';
import formulaireProduit from './composants/formulaire-produit';
import listeDynamique from './composants/liste-dynamique';
import menu from './composants/menu';
import modal from './composants/modal';
import ouvrirPdf from './composants/ouvrir-pdf';
import recherche from './composants/recherche';
import reglements from './composants/reglements';
import saisieAchat from './composants/saisie-achat';
import theme from './composants/theme';
import toasts from './composants/toasts';

// Composants Alpine du design system (voir /design-systeme)
document.addEventListener('alpine:init', () => {
    toasts(Alpine);
    theme(Alpine);
    modal(Alpine);
    menu(Alpine);
    recherche(Alpine);
    bouton(Alpine);
    ouvrirPdf(Alpine);
    coquille(Alpine);
    listeDynamique(Alpine);
    formulaireProduit(Alpine);
    saisieAchat(Alpine);
    caisse(Alpine);
    factures(Alpine);
    reglements(Alpine);
    assistantRetour(Alpine);
});

window.Alpine = Alpine;
Alpine.start();

// PWA : service worker limité aux ressources statiques (jamais les données métier)
if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Sans service worker, l'application fonctionne normalement (en ligne)
        });
    });
}
