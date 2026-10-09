/**
 * Modales basées sur <dialog> : le navigateur gère le piégeage du focus (arrière-plan inerte)
 * et la touche Échap. Ouverture : $dispatch('ouvrir-modal', 'identifiant').
 * Les écouteurs globaux sont retirés quand la modale quitte la page (ex. liste rechargée par listeDynamique).
 */
export default function (Alpine) {
    Alpine.data('modal', (id) => ({
        id,
        declencheur: null,
        ecouteurs: {},

        init() {
            this.ecouteurs = {
                ouvrir: (e) => e.detail === this.id && this.ouvrir(),
                fermer: (e) => (e.detail === this.id || !e.detail) && this.fermer(),
            };
            window.addEventListener('ouvrir-modal', this.ecouteurs.ouvrir);
            window.addEventListener('fermer-modal', this.ecouteurs.fermer);
            // Retour du focus sur l'élément qui a ouvert la modale (Échap compris)
            this.$el.addEventListener('close', () => this.declencheur?.isConnected && this.declencheur.focus());
        },

        destroy() {
            window.removeEventListener('ouvrir-modal', this.ecouteurs.ouvrir);
            window.removeEventListener('fermer-modal', this.ecouteurs.fermer);
        },

        ouvrir() {
            if (!this.$el.isConnected || this.$el.open) {
                return;
            }
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
