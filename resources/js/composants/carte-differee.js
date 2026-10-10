/**
 * Carte chargée après la page (tableau de bord) : fragment HTML demandé au montage, squelette pendant
 * la requête, message et bouton « Réessayer » en cas d'échec ; rechargée quand l'onglet redevient visible
 * après plus d'une minute (les statistiques serveur sont mises en cache 60 secondes).
 *
 * Utilisation : <div x-data="carteDifferee({ url })"><template x-ref="squelette">…</template><div x-ref="contenu"></div></div>
 */
const DELAI_RAFRAICHISSEMENT = 60_000;

export default function (Alpine) {
    Alpine.data('carteDifferee', ({ url }) => ({
        chargement: true,
        erreur: false,
        chargeLe: 0,
        visibilite: null,

        init() {
            this.charger();
            this.visibilite = () => {
                if (document.visibilityState === 'visible' && Date.now() - this.chargeLe > DELAI_RAFRAICHISSEMENT) {
                    this.charger();
                }
            };
            document.addEventListener('visibilitychange', this.visibilite);
        },

        destroy() {
            document.removeEventListener('visibilitychange', this.visibilite);
        },

        async charger() {
            this.chargement = true;
            this.erreur = false;
            try {
                const reponse = await fetch(url, { headers: { Accept: 'text/html', 'X-Fragment': 'carte' } });
                if (!reponse.ok) {
                    throw new Error(String(reponse.status));
                }
                this.$refs.contenu.innerHTML = await reponse.text();
                this.chargeLe = Date.now();
            } catch {
                this.erreur = true;
            } finally {
                this.chargement = false;
            }
        },
    }));
}
