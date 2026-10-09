/**
 * Notifications « toast » globales (jamais d'alert()).
 * - JS : window.toast('succes', 'Produit enregistré.')
 * - Alpine : $dispatch('toast', { type: 'erreur', message: '…' })
 * - Laravel : session()->flash('succes' | 'erreur' | 'info', '…'), lu au chargement de la page.
 */
const DUREE_PAR_DEFAUT = 5000;
const MAXIMUM = 5;

export default function (Alpine) {
    Alpine.store('toasts', {
        liste: [],
        compteur: 0,
        minuteurs: {},

        ajouter(type, message, duree = DUREE_PAR_DEFAUT) {
            const id = ++this.compteur;
            this.liste.push({ id, type, message, duree, restant: duree, debut: 0 });

            // Empilement limité : les plus anciens disparaissent
            while (this.liste.length > MAXIMUM) {
                this.fermer(this.liste[0].id);
            }

            this.reprendre(id);
        },

        fermer(id) {
            clearTimeout(this.minuteurs[id]);
            delete this.minuteurs[id];
            this.liste = this.liste.filter((t) => t.id !== id);
        },

        // Pause au survol ou au focus, pour laisser le temps de lire
        pause(id) {
            const toast = this.liste.find((t) => t.id === id);
            if (!toast || !this.minuteurs[id]) {
                return;
            }
            clearTimeout(this.minuteurs[id]);
            delete this.minuteurs[id];
            toast.restant -= Date.now() - toast.debut;
        },

        reprendre(id) {
            const toast = this.liste.find((t) => t.id === id);
            if (!toast || this.minuteurs[id] || toast.duree === 0) {
                return;
            }
            toast.debut = Date.now();
            this.minuteurs[id] = setTimeout(() => this.fermer(id), Math.max(toast.restant, 1000));
        },
    });

    window.toast = (type, message, duree) => Alpine.store('toasts').ajouter(type, message, duree);
    window.addEventListener('toast', (evenement) => {
        const { type = 'info', message = '', duree } = evenement.detail ?? {};
        window.toast(type, message, duree);
    });

    // Messages flash de Laravel injectés par le layout
    document.addEventListener('alpine:initialized', () => {
        const donnees = document.getElementById('toasts-flash')?.textContent;
        JSON.parse(donnees || '[]').forEach(({ type, message }) => window.toast(type, message));
    });
}
