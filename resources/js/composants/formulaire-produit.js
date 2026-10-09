/**
 * Panneau de création / modification d'un produit :
 * remplissage (nouveau ou existant), aperçu de la photo, génération EAN-13, marge en direct.
 * Ouverture : $dispatch('nouveau-produit') ou $dispatch('editer-produit', { cible, …champs }).
 */
// « cible » et non « id » : le panneau (composant modal) a déjà son propre « id »
const VIDE = {
    cible: null,
    reference: '',
    code_barres: '',
    nom: '',
    description: '',
    categorie_id: '',
    unite_id: '',
    prix_achat: '',
    prix_vente: '',
    prix_gros: '',
    stock_minimum: '0',
    stock_initial: '',
    stock_actuel: null,
    photo: null,
};

const ariary = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });

export default function (Alpine) {
    Alpine.data('formulaireProduit', ({ valeurs = {}, ouvrir = false, urlProduits, urlCodeBarres }) => ({
        ...VIDE,
        ...valeurs,
        apercu: valeurs.photo ?? null,
        retirerPhoto: false,
        generation: false,

        init() {
            if (ouvrir) {
                this.$nextTick(() => this.ouvrirPanneau());
            }
        },

        get action() {
            return this.cible ? `${urlProduits}/${this.cible}` : urlProduits;
        },

        // Marge en direct : montant et taux par rapport au prix d'achat
        get marge() {
            const achat = Number(this.prix_achat) || 0;
            const vente = Number(this.prix_vente) || 0;
            if (!this.prix_vente || !this.prix_achat) {
                return null;
            }
            const montant = vente - achat;
            return {
                montant,
                pourcentage: achat > 0 ? (montant / achat) * 100 : null,
                negative: montant < 0,
            };
        },

        get texteMarge() {
            if (!this.marge) {
                return 'Saisissez les prix d\'achat et de vente pour calculer la marge.';
            }
            const signe = this.marge.montant > 0 ? '+' : '';
            const pourcentage = this.marge.pourcentage === null
                ? ''
                : ` (${signe}${this.marge.pourcentage.toLocaleString('fr-FR', { maximumFractionDigits: 1 })} %)`;
            return `${signe}${ariary.format(this.marge.montant)} Ar${pourcentage}`;
        },

        remplir(donnees = {}) {
            Object.assign(this, VIDE, donnees);
            this.apercu = donnees.photo ?? null;
            this.retirerPhoto = false;
            if (this.$refs.photo) {
                this.$refs.photo.value = '';
            }
            this.ouvrirPanneau();
        },

        ouvrirPanneau() {
            this.$dispatch('ouvrir-modal', 'panneau-produit');
        },

        choisirPhoto(evenement) {
            const fichier = evenement.target.files[0];
            if (!fichier) {
                return;
            }
            if (fichier.size > 4 * 1024 * 1024) {
                window.toast?.('erreur', 'La photo ne doit pas dépasser 4 Mo.');
                evenement.target.value = '';
                return;
            }
            this.apercu = URL.createObjectURL(fichier);
            this.retirerPhoto = false;
        },

        enleverPhoto() {
            this.apercu = null;
            this.retirerPhoto = true;
            this.$refs.photo.value = '';
        },

        async genererCodeBarres() {
            this.generation = true;
            try {
                const reponse = await fetch(urlCodeBarres, { headers: { Accept: 'application/json' } });
                if (!reponse.ok) {
                    throw new Error(reponse.statusText);
                }
                this.code_barres = (await reponse.json()).code;
            } catch {
                window.toast?.('erreur', 'Impossible de générer un code-barres. Vérifiez la connexion.');
            } finally {
                this.generation = false;
            }
        },
    }));
}
