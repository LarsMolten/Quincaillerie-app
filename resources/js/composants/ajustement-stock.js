/**
 * Modale d'ajustement manuel du stock (pages Entrées et Sorties, droit stock.ajuster) :
 * recherche du produit, type (ajustement ou perte), quantité, motif obligatoire, envoi en JSON.
 */
const quantites = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 3 });
const jetonCsrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const DELAI_RECHERCHE = 250;

export default function (Alpine) {
    Alpine.data('ajustementStock', ({ urls, types }) => ({
        types,
        recherche: '',
        resultats: [],
        minuteur: null,
        produit: null,
        type: types[0]?.valeur ?? '',
        quantite: '',
        motif: '',
        envoi: false,
        erreurs: {},

        rechercherAvecDelai() {
            clearTimeout(this.minuteur);
            this.minuteur = setTimeout(() => this.chercher(), DELAI_RECHERCHE);
        },

        async chercher() {
            const terme = this.recherche.trim();
            if (terme === '') {
                this.resultats = [];
                return;
            }
            try {
                const reponse = await fetch(`${urls.produits}?${new URLSearchParams({ q: terme })}`, { headers: { Accept: 'application/json' } });
                this.resultats = reponse.ok ? await reponse.json() : [];
            } catch {
                this.resultats = [];
            }
        },

        choisir(produit) {
            this.produit = produit;
            this.resultats = [];
            this.recherche = '';
            delete this.erreurs.produit_id;
            this.$nextTick(() => document.getElementById('ajustement-quantite')?.focus());
        },

        qte(valeur, unite) {
            return `${quantites.format(Number(valeur) || 0)}${unite ? ` ${unite}` : ''}`;
        },

        reinitialiser() {
            Object.assign(this, { recherche: '', resultats: [], produit: null, type: this.types[0]?.valeur ?? '', quantite: '', motif: '', erreurs: {} });
        },

        async enregistrer() {
            if (this.envoi) {
                return;
            }
            this.envoi = true;
            this.erreurs = {};
            try {
                const reponse = await fetch(urls.enregistrer, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': jetonCsrf() },
                    body: JSON.stringify({ produit_id: this.produit?.id ?? null, type: this.type, quantite: this.quantite, motif: this.motif }),
                });
                const donnees = await reponse.json().catch(() => ({}));

                if (reponse.status === 201) {
                    this.$dispatch('fermer-modal', 'modale-ajustement');
                    window.toast?.('succes', donnees.message);
                    this.$dispatch('ajustement-enregistre');
                    this.reinitialiser();
                } else if (reponse.status === 422) {
                    this.erreurs = donnees.errors ?? { quantite: [donnees.message] };
                } else if (reponse.status === 419) {
                    window.toast?.('erreur', 'Votre session a expiré : rechargez la page.');
                } else {
                    window.toast?.('erreur', 'L\'ajustement n\'a pas été enregistré. Réessayez.');
                }
            } catch {
                window.toast?.('erreur', 'Connexion impossible : l\'ajustement n\'a pas été enregistré.');
            } finally {
                this.envoi = false;
            }
        },
    }));
}
