/**
 * Assistant de retour (client ou fournisseur) en 3 étapes : 1. document d'origine, 2. produits et motif,
 * 3. confirmation (répartition avoir / remboursement). Les montants affichés sont indicatifs :
 * RetourService recalcule les quantités retournables, les prix et le règlement à l'enregistrement.
 */
const ariary = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });
const quantites = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 3 });
const nombre = (valeur) => {
    const n = parseFloat(String(valeur ?? '').replace(/[\s  ]/g, '').replace(',', '.'));
    return Number.isFinite(n) ? n : 0;
};
const jetonCsrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const DELAI_RECHERCHE = 250;

export default function (Alpine) {
    Alpine.data('assistantRetour', (config) => ({
        type: config.type,
        motifs: config.motifs,
        modes: config.modes,
        urls: config.urls,

        etape: 1,
        recherche: '',
        resultats: [],
        chargement: false,
        minuteur: null,

        document: null,
        quantites: {},
        motif: '',
        precision: '',
        mode: '',

        envoi: false,
        erreur: '',
        reussite: null,

        init() {
            // Le message d'aide disparaît dès que la saisie change
            this.$watch('motif', () => { this.erreur = ''; });
            this.$watch('precision', () => { this.erreur = ''; });
            this.$watch('quantites', () => { this.erreur = ''; }, { deep: true });

            if (config.document) {
                this.prendre(config.document);
            } else {
                this.chercher();
            }
        },

        /* ---------- Étape 1 : document d'origine ---------- */

        rechercherAvecDelai() {
            clearTimeout(this.minuteur);
            this.minuteur = setTimeout(() => this.chercher(), DELAI_RECHERCHE);
        },

        async chercher() {
            this.chargement = true;
            try {
                const params = new URLSearchParams({ type: this.type, q: this.recherche.trim() });
                const reponse = await fetch(`${this.urls.recherche}?${params}`, { headers: { Accept: 'application/json' } });
                if (!reponse.ok) {
                    throw new Error(String(reponse.status));
                }
                this.resultats = await reponse.json();
            } catch {
                window.toast?.('erreur', 'Recherche impossible. Vérifiez la connexion.');
            } finally {
                this.chargement = false;
            }
        },

        async choisir(id) {
            this.chargement = true;
            try {
                const params = new URLSearchParams({ type: this.type, id });
                const reponse = await fetch(`${this.urls.recherche}?${params}`, { headers: { Accept: 'application/json' } });
                if (!reponse.ok) {
                    throw new Error(String(reponse.status));
                }
                this.prendre(await reponse.json());
            } catch {
                window.toast?.('erreur', 'Ce document ne peut pas être chargé.');
            } finally {
                this.chargement = false;
            }
        },

        prendre(document) {
            this.document = document;
            this.quantites = Object.fromEntries(document.lignes.map((l) => [l.produit_id, '']));
            this.erreur = '';
            this.etape = 2;
        },

        /* ---------- Étape 2 : produits et motif ---------- */

        borner(ligne) {
            const valeur = nombre(this.quantites[ligne.produit_id]);
            if (valeur > ligne.retournable) {
                this.quantites[ligne.produit_id] = String(ligne.retournable);
                window.toast?.('alerte', `« ${ligne.nom} » : ${this.qte(ligne.retournable, ligne.unite)} retournable(s) au plus.`);
            } else if (valeur < 0) {
                this.quantites[ligne.produit_id] = '';
            }
        },

        tout(ligne) {
            this.quantites[ligne.produit_id] = String(ligne.retournable);
        },

        aucun() {
            for (const cle of Object.keys(this.quantites)) {
                this.quantites[cle] = '';
            }
        },

        get lignesRetournees() {
            return (this.document?.lignes ?? [])
                .map((l) => ({ ...l, quantiteRetour: Math.min(nombre(this.quantites[l.produit_id]), l.retournable) }))
                .filter((l) => l.quantiteRetour > 0);
        },

        get total() {
            return this.lignesRetournees.reduce((somme, l) => somme + Math.round(l.quantiteRetour * l.prix_unitaire), 0);
        },

        get motifComplet() {
            return this.motif !== '' && (this.motif !== 'Autre' || this.precision.trim() !== '');
        },

        get peutContinuer() {
            return this.lignesRetournees.length > 0 && this.motifComplet;
        },

        /* ---------- Étape 3 : confirmation ---------- */

        get avoir() {
            return Math.min(this.total, Math.max(0, this.document?.reste ?? 0));
        },

        get rembourse() {
            return this.total - this.avoir;
        },

        get peutValider() {
            return this.peutContinuer && !this.envoi && (this.rembourse <= 0 || this.mode !== '');
        },

        suivant() {
            if (this.etape === 2 && !this.peutContinuer) {
                this.erreur = this.lignesRetournees.length === 0
                    ? 'Indiquez au moins une quantité à retourner.'
                    : 'Choisissez le motif du retour (et précisez-le pour « Autre »).';
                return;
            }
            this.erreur = '';
            this.etape = Math.min(3, this.etape + 1);
        },

        retour(etape) {
            if (etape < this.etape && !this.envoi) {
                this.erreur = '';
                this.etape = etape;
            }
        },

        async enregistrer() {
            if (!this.peutValider) {
                return;
            }
            this.envoi = true;
            this.erreur = '';
            try {
                const reponse = await fetch(this.urls.enregistrer, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': jetonCsrf() },
                    body: JSON.stringify({
                        type: this.type,
                        [this.type === 'fournisseur' ? 'achat_id' : 'vente_id']: this.document.id,
                        lignes: this.lignesRetournees.map((l) => ({ produit_id: l.produit_id, quantite: l.quantiteRetour })),
                        motif: this.motif,
                        precision: this.precision.trim(),
                        mode_remboursement: this.rembourse > 0 ? this.mode : null,
                    }),
                });
                const donnees = await reponse.json().catch(() => ({}));

                if (reponse.status === 201) {
                    this.reussite = donnees;
                    window.toast?.('succes', donnees.message);
                } else if (reponse.status === 422) {
                    this.erreur = donnees.errors ? Object.values(donnees.errors).flat()[0] : (donnees.message ?? 'Retour refusé.');
                } else if (reponse.status === 419) {
                    this.erreur = 'Votre session a expiré : rechargez la page.';
                } else {
                    this.erreur = 'Erreur inattendue : le retour n\'a pas été enregistré. Réessayez.';
                }
            } catch {
                this.erreur = 'Connexion impossible : le retour n\'a pas été enregistré.';
            } finally {
                this.envoi = false;
            }
        },

        ar(montant) {
            return `${ariary.format(Math.round(montant))} Ar`;
        },

        qte(valeur, unite) {
            return `${quantites.format(nombre(valeur))}${unite ? ` ${unite}` : ''}`;
        },
    }));
}
