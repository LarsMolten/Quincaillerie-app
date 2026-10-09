/**
 * État partagé de la coquille de l'application (layouts/app) :
 * - grand écran : barre latérale dépliée ou repliée « icônes seules », mémorisée ;
 * - petit écran : tiroir de navigation (<dialog id="tiroir-navigation">).
 * L'état replié est appliqué avant le rendu par le script du head (data-barre="repliee"), sans flash.
 */
const GRAND_ECRAN = window.matchMedia('(min-width: 64rem)');

export default function (Alpine) {
    Alpine.data('coquille', () => ({
        replie: document.documentElement.dataset.barre === 'repliee',
        estGrand: GRAND_ECRAN.matches,
        tiroirOuvert: false,

        init() {
            GRAND_ECRAN.addEventListener('change', (e) => {
                this.estGrand = e.matches;
                // Le tiroir n'a plus de sens en grand écran
                if (e.matches) {
                    window.dispatchEvent(new CustomEvent('fermer-modal', { detail: 'tiroir-navigation' }));
                }
            });
            document.getElementById('tiroir-navigation')?.addEventListener('close', () => (this.tiroirOuvert = false));
        },

        // Bouton de menu de l'en-tête : replie la barre (grand écran) ou ouvre le tiroir (mobile)
        basculerMenu() {
            this.estGrand ? this.basculerBarre() : this.ouvrirTiroir();
        },

        get menuDeplie() {
            return this.estGrand ? !this.replie : this.tiroirOuvert;
        },

        basculerBarre() {
            this.replie = !this.replie;

            if (this.replie) {
                document.documentElement.dataset.barre = 'repliee';
            } else {
                delete document.documentElement.dataset.barre;
            }

            try {
                localStorage.setItem('barre-repliee', this.replie ? '1' : '0');
            } catch {
                // Stockage indisponible : le choix vaut pour la page en cours
            }
        },

        ouvrirTiroir() {
            this.tiroirOuvert = true;
            window.dispatchEvent(new CustomEvent('ouvrir-modal', { detail: 'tiroir-navigation' }));
        },
    }));
}
