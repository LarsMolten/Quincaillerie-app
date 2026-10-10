/**
 * Environnement commun des tests JS : DOM simulé (happy-dom) exposé en globales comme dans un navigateur,
 * puis Alpine (build ESM, celui utilisé par Vite) avec les composants demandés.
 */
import { Window } from 'happy-dom';

export const fenetre = new Window({ url: 'http://localhost/' });

// Globales du navigateur attendues par Alpine (classes du DOM, document, window…), sans écraser celles de Node,
// sauf les événements et FormData : ceux de Node ne fonctionnent pas avec les éléments du DOM simulé
Object.defineProperty(globalThis, 'window', { value: fenetre, configurable: true, writable: true });
for (const nom of Object.getOwnPropertyNames(fenetre)) {
    const imposee = ['Event', 'CustomEvent', 'EventTarget', 'FormData'].includes(nom);
    if ((imposee || !(nom in globalThis)) && (/^[A-Z]/.test(nom) || ['document', 'navigator', 'requestAnimationFrame', 'getComputedStyle'].includes(nom))) {
        Object.defineProperty(globalThis, nom, { value: fenetre[nom], configurable: true, writable: true });
    }
}

/**
 * Insère le HTML, enregistre les composants (fonctions (Alpine) => void) et démarre Alpine.
 */
export async function demarrer(html, composants) {
    document.body.innerHTML = html;
    const Alpine = (await import('alpinejs/dist/module.esm.js')).default;
    composants.forEach((enregistrer) => enregistrer(Alpine));
    Alpine.start();
    await fenetre.happyDOM.waitUntilComplete();

    return Alpine;
}
