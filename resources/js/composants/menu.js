/**
 * Menu déroulant d'actions « ⋯ » accessible au clavier :
 * Entrée, Espace ou Flèche bas pour ouvrir, flèches pour naviguer, Début/Fin, Échap pour fermer.
 * Le panneau est en position fixe (calculée à l'ouverture) pour ne pas être rogné
 * par le conteneur défilant d'un tableau ; il s'ouvre vers le haut s'il manque de place.
 */
const MARGE = 8;

export default function (Alpine) {
    Alpine.data('menuActions', () => ({
        ouvert: false,
        position: '',

        elements() {
            return [...this.$refs.liste.querySelectorAll('[role="menuitem"]:not([disabled])')];
        },

        positionner() {
            const bouton = this.$refs.bouton.getBoundingClientRect();
            const hauteur = this.$refs.liste.offsetHeight;
            const versLeHaut = bouton.bottom + MARGE + hauteur > window.innerHeight && bouton.top - MARGE - hauteur > 0;
            const haut = versLeHaut ? bouton.top - MARGE - hauteur : bouton.bottom + MARGE;
            const droite = Math.max(MARGE, window.innerWidth - bouton.right);

            this.position = `top: ${haut}px; right: ${droite}px;`;
        },

        ouvrir(focus = 'premier') {
            this.ouvert = true;
            this.$nextTick(() => {
                this.positionner();
                const elements = this.elements();
                (focus === 'dernier' ? elements.at(-1) : elements[0])?.focus();
            });
        },

        fermer(rendreFocus = true) {
            if (!this.ouvert) {
                return;
            }
            this.ouvert = false;
            if (rendreFocus) {
                this.$refs.bouton.focus();
            }
        },

        basculer() {
            this.ouvert ? this.fermer() : this.ouvrir();
        },

        deplacer(pas) {
            const elements = this.elements();
            const index = elements.indexOf(document.activeElement);
            elements[(index + pas + elements.length) % elements.length]?.focus();
        },

        extremite(position) {
            const elements = this.elements();
            (position === 'debut' ? elements[0] : elements.at(-1))?.focus();
        },
    }));
}
