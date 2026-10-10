/**
 * Modales basées sur <dialog> : le navigateur gère le piégeage du focus (arrière-plan inerte)
 * et la touche Échap. Ouverture : $dispatch('ouvrir-modal', 'identifiant').
 * Les écouteurs globaux sont retirés quand la modale quitte la page (ex. liste rechargée par listeDynamique).
 *
 * Le <dialog> est mémorisé dans init() : appelée depuis un x-on posé sur un élément enfant
 * (bouton ✕ par exemple), une méthode verrait this.$el désigner cet enfant, pas la modale.
 */
export default function (Alpine) {
    Alpine.data('modal', (id) => ({
        id,
        dialogue: null,
        declencheur: null,
        ecouteurs: {},

        init() {
            this.dialogue = this.$el;
            this.ecouteurs = {
                ouvrir: (e) => e.detail === this.id && this.ouvrir(),
                fermer: (e) => (e.detail === this.id || !e.detail) && this.fermer(),
            };
            window.addEventListener('ouvrir-modal', this.ecouteurs.ouvrir);
            window.addEventListener('fermer-modal', this.ecouteurs.fermer);
            // Retour du focus sur l'élément qui a ouvert la modale (Échap compris)
            this.dialogue.addEventListener('close', () => this.declencheur?.isConnected && this.declencheur.focus());
        },

        destroy() {
            window.removeEventListener('ouvrir-modal', this.ecouteurs.ouvrir);
            window.removeEventListener('fermer-modal', this.ecouteurs.fermer);
        },

        ouvrir() {
            if (!this.dialogue.isConnected || this.dialogue.open) {
                return;
            }
            this.declencheur = document.activeElement;
            this.dialogue.showModal();
        },

        fermer() {
            if (this.dialogue.open) {
                this.dialogue.close();
            }
        },

        // Clic sur le voile (hors du panneau) : fermeture
        clicFond(evenement) {
            if (evenement.target === this.dialogue) {
                this.fermer();
            }
        },
    }));
}
