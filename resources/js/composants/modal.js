/**
 * Modales basées sur <dialog> : le navigateur gère le piégeage du focus (arrière-plan inerte)
 * et la touche Échap. Ouverture : $dispatch('ouvrir-modal', 'identifiant').
 */
export default function (Alpine) {
    Alpine.data('modal', (id) => ({
        id,
        declencheur: null,

        init() {
            window.addEventListener('ouvrir-modal', (e) => e.detail === this.id && this.ouvrir());
            window.addEventListener('fermer-modal', (e) => (e.detail === this.id || !e.detail) && this.fermer());
            // Retour du focus sur l'élément qui a ouvert la modale (Échap compris)
            this.$el.addEventListener('close', () => this.declencheur?.focus?.());
        },

        ouvrir() {
            this.declencheur = document.activeElement;
            this.$el.showModal();
        },

        fermer() {
            if (this.$el.open) {
                this.$el.close();
            }
        },

        // Clic sur le voile (hors du panneau) : fermeture
        clicFond(evenement) {
            if (evenement.target === this.$el) {
                this.fermer();
            }
        },
    }));
}
