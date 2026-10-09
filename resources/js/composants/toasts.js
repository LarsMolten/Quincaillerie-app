/**
 * Notifications « toast » globales (jamais d'alert()).
 * - JS : window.toast('succes', 'Produit enregistré.')
 * - Alpine : $dispatch('toast', { type: 'erreur', message: '…' })
 * - Laravel : session()->flash('succes' | 'erreur' | 'info', '…'), lu au chargement de la page ;
 *   lien facultatif : ->with('toast_lien', ['libelle' => …, 'url' => …, 'nouvelOnglet' => true]).
 */
const DUREE_PAR_DEFAUT = 5000;
const MAXIMUM = 5;

export default function (Alpine) {
    Alpine.store('toasts', {
        liste: [],
        compteur: 0,
        minuteurs: {},

        ajouter(type, message, duree = DUREE_PAR_DEFAUT, lien = null) {
            const id = ++this.compteur;
            // Un toast avec lien reste affiché plus longtemps, pour laisser le temps de cliquer
            const delai = lien ? Math.max(duree, 9000) : duree;
            this.liste.push({ id, type, message, lien, duree: delai, restant: delai, debut: 0 });

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

    window.toast = (type, message, duree, lien) => Alpine.store('toasts').ajouter(type, message, duree, lien);
    window.addEventListener('toast', (evenement) => {
        const { type = 'info', message = '', duree, lien } = evenement.detail ?? {};
        window.toast(type, message, duree, lien);
    });

    // Messages flash de Laravel injectés par le layout
    document.addEventListener('alpine:initialized', () => {
        const donnees = document.getElementById('toasts-flash')?.textContent;
        JSON.parse(donnees || '[]').forEach(({ type, message, lien }) => window.toast(type, message, undefined, lien));
    });
}
