import Alpine from 'alpinejs';
import bouton from './composants/bouton';
import menu from './composants/menu';
import modal from './composants/modal';
import recherche from './composants/recherche';
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
});

window.Alpine = Alpine;
Alpine.start();
