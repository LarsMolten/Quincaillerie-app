/**
 * Écran « Nouvel achat » : recherche de produits (clavier, scanner), lignes modifiables,
 * total et reste à payer calculés en direct. Le serveur recalcule tout à l'enregistrement.
 */
const ariary = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });
const nombre = (valeur) => {
    const n = parseFloat(String(valeur ?? '').replace(/[\s ]/g, '').replace(',', '.'));
    return Number.isFinite(n) ? n : 0;
};

export default function (Alpine) {
    Alpine.data('saisieAchat', ({ urlProduits, lignes = [], fournisseur = '', montantPaye = '', modePaiement = 'especes' }) => ({
        recherche: '',
        resultats: [],
        actif: 0,
        recherchant: false,
        minuteur: null,
        lignes: lignes.map((ligne) => ({ ...ligne })),
        fournisseur: String(fournisseur ?? ''),
        montantPaye: String(montantPaye ?? ''),
        modePaiement,

        get total() {
            return this.lignes.reduce((somme, l) => somme + nombre(l.quantite) * nombre(l.prix_achat), 0);
        },

        get articles() {
            return this.lignes.reduce((somme, l) => somme + nombre(l.quantite), 0);
        },

        get reste() {
            return Math.max(0, this.total - nombre(this.montantPaye));
        },

        get depassement() {
            return nombre(this.montantPaye) > this.total + 0.001;
        },

        get peutEnregistrer() {
            return this.lignes.length > 0 && this.fournisseur !== '' && !this.depassement
                && this.lignes.every((l) => nombre(l.quantite) > 0 && nombre(l.prix_achat) >= 0);
        },

        ar(montant) {
            return `${ariary.format(Math.round(montant))} Ar`;
        },

        totalLigne(ligne) {
            return nombre(ligne.quantite) * nombre(ligne.prix_achat);
        },

        // Recherche : délai après la frappe ; Entrée immédiate (lecture d'un code-barres au scanner)
        saisir() {
            clearTimeout(this.minuteur);
            this.minuteur = setTimeout(() => this.chercher(), 250);
        },

        async chercher() {
            const terme = this.recherche.trim();
            if (terme === '') {
                this.resultats = [];
                return;
            }
            this.recherchant = true;
            try {
                const reponse = await fetch(`${urlProduits}?q=${encodeURIComponent(terme)}`, { headers: { Accept: 'application/json' } });
                this.resultats = reponse.ok ? await reponse.json() : [];
                this.actif = 0;
            } catch {
                window.toast?.('erreur', 'Recherche impossible. Vérifiez la connexion.');
            } finally {
                this.recherchant = false;
            }
        },

        async valider() {
            clearTimeout(this.minuteur);
            const terme = this.recherche.trim();
            if (terme === '') {
                return;
            }
            // Code-barres scanné : recherche immédiate puis ajout du produit trouvé
            if (this.resultats.length === 0 || /^\d{8,14}$/.test(terme)) {
                await this.chercher();
            }
            const choisi = this.resultats[this.actif] ?? (this.resultats.length === 1 ? this.resultats[0] : null);
            if (choisi) {
                this.ajouter(choisi);
            } else {
                window.toast?.('alerte', `Aucun produit actif ne correspond à « ${terme} ».`);
            }
        },

        deplacer(pas) {
            if (this.resultats.length) {
                this.actif = (this.actif + pas + this.resultats.length) % this.resultats.length;
            }
        },

        ajouter(produit) {
            const existante = this.lignes.find((l) => l.produit_id === produit.produit_id);
            if (existante) {
                existante.quantite = String(nombre(existante.quantite) + 1);
            } else {
                this.lignes.push({ ...produit, quantite: '1' });
            }
            this.recherche = '';
            this.resultats = [];
            this.$nextTick(() => {
                // Focus sur la quantité de la ligne ajoutée, pour la modifier tout de suite
                const index = this.lignes.findIndex((l) => l.produit_id === produit.produit_id);
                document.getElementById(`quantite-${index}`)?.select();
            });
        },

        retirer(index) {
            this.lignes.splice(index, 1);
            this.$refs.recherche.focus();
        },

        payerTotal() {
            this.montantPaye = String(Math.round(this.total));
        },

        payerPartiel() {
            this.montantPaye = '';
            this.$nextTick(() => document.getElementById('champ-montant-paye')?.focus());
        },

        aCredit() {
            this.montantPaye = '0';
        },
    }));
}
