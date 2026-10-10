/**
 * Écran de caisse : catalogue (recherche, scanner, catégories), ticket (quantités, tarif, remises),
 * client, paiement et écran de réussite. Le ticket en cours est conservé dans le navigateur
 * (rechargement, coupure réseau), mais une vente n'est JAMAIS validée sans le serveur : il recalcule
 * les prix, les remises, le crédit et le stock à l'enregistrement.
 * La caisse ne se fie pas à navigator.onLine (connexion Internet du poste) : le serveur est local (même
 * poste ou réseau de la boutique) et reste joignable sans Internet. Seul l'échec réel d'une requête
 * signale un serveur injoignable.
 */
const ariary = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });
const quantites = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 3 });
const nombre = (valeur) => {
    const n = parseFloat(String(valeur ?? '').replace(/[\s  ]/g, '').replace(',', '.'));
    return Number.isFinite(n) ? n : 0;
};
const arrondi = (montant) => Math.round(montant);
const mouvementReduit = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const pointeurPrecis = () => window.matchMedia('(pointer: fine)').matches;

export default function (Alpine) {
    Alpine.data('caisse', (config) => ({
        ecouteurs: {},
        urls: config.urls,
        comptoir: config.comptoir,
        remiseAutorisee: config.remise.autorisee,
        plafondRemise: config.remise.plafond,
        stockNegatif: config.stockNegatif,
        cleStockage: config.cleStockage,

        // Catalogue
        onglet: 'catalogue',
        recherche: '',
        categorie: null,
        produits: [],
        chargement: true,
        minuteur: null,
        requete: 0,

        // Ticket
        lignes: [],
        client: config.comptoir,
        remiseGlobale: { valeur: '', type: 'montant' },
        totalAffiche: 0,
        battement: false,

        // Client (F4)
        rechercheClient: '',
        clientsTrouves: [],
        chargementClients: false,

        // Paiement (F9)
        mode: 'especes',
        recu: '',
        billetsSaisis: false,
        envoi: false,
        erreur: '',
        serveurInjoignable: false,
        reussite: null,

        init() {
            this.restaurer();
            this.totalAffiche = this.total;
            this.charger();

            this.$watch('total', (valeur) => this.animerTotal(valeur));
            this.$watch('lignes', () => this.sauvegarder(), { deep: true });
            this.$watch('client', () => this.sauvegarder());
            this.$watch('remiseGlobale', () => this.sauvegarder(), { deep: true });

            this.ecouteurs = {
                touche: (e) => this.raccourci(e),
                // Retour du réseau : l'alerte est retirée (un nouvel échec la remettra)
                enLigne: () => { this.serveurInjoignable = false; },
            };
            window.addEventListener('keydown', this.ecouteurs.touche);
            window.addEventListener('online', this.ecouteurs.enLigne);
            this.$nextTick(() => this.focusRecherche(true));
        },

        destroy() {
            window.removeEventListener('keydown', this.ecouteurs.touche);
            window.removeEventListener('online', this.ecouteurs.enLigne);
        },

        /* ---------- Calculs (indicatifs : le serveur fait foi) ---------- */

        prix(ligne) {
            return ligne.tarif === 'gros' && ligne.prix_gros !== null ? ligne.prix_gros : ligne.prix_vente;
        },

        brutLigne(ligne) {
            return nombre(ligne.quantite) * this.prix(ligne);
        },

        totalLigne(ligne) {
            return Math.max(0, this.brutLigne(ligne) - nombre(ligne.remise));
        },

        get brut() {
            return this.lignes.reduce((somme, l) => somme + this.brutLigne(l), 0);
        },

        get sousTotal() {
            return this.lignes.reduce((somme, l) => somme + this.totalLigne(l), 0);
        },

        get remiseGlobaleMontant() {
            const valeur = nombre(this.remiseGlobale.valeur);
            const montant = this.remiseGlobale.type === 'pourcentage' ? this.sousTotal * valeur / 100 : valeur;
            return Math.min(arrondi(Math.max(0, montant)), arrondi(this.sousTotal));
        },

        get remises() {
            return this.lignes.reduce((somme, l) => somme + nombre(l.remise), 0) + this.remiseGlobaleMontant;
        },

        get remiseMaximum() {
            return arrondi(this.brut * this.plafondRemise / 100);
        },

        get remiseTropForte() {
            return this.remises > this.remiseMaximum + 0.5;
        },

        get total() {
            return arrondi(this.sousTotal - this.remiseGlobaleMontant);
        },

        get articles() {
            return this.lignes.reduce((somme, l) => somme + nombre(l.quantite), 0);
        },

        get paye() {
            return this.mode === 'credit' ? 0 : Math.min(nombre(this.recu), this.total);
        },

        get monnaie() {
            return this.mode === 'especes' ? Math.max(0, nombre(this.recu) - this.total) : 0;
        },

        get reste() {
            return Math.max(0, this.total - this.paye);
        },

        /** Raison qui empêche de laisser un reste à payer (crédit), ou null. */
        get refusCredit() {
            if (this.reste <= 0) {
                return null;
            }
            if (this.client.comptoir) {
                return 'Le crédit est interdit pour le Client comptoir : encaissez la totalité ou choisissez un client (F4).';
            }
            if (this.client.plafond === null) {
                return `Aucun crédit n'est autorisé pour « ${this.client.nom} » (pas de plafond de crédit).`;
            }
            if (this.reste > this.client.disponible + 0.5) {
                return `Plafond de crédit dépassé : ${this.ar(this.client.disponible)} encore disponibles pour « ${this.client.nom} ».`;
            }
            return null;
        },

        get refusPaiement() {
            if (this.mode !== 'credit' && nombre(this.recu) <= 0) {
                return 'Indiquez le montant reçu, ou choisissez « Crédit ».';
            }
            if (this.mode !== 'credit' && this.mode !== 'especes' && nombre(this.recu) > this.total + 0.5) {
                return 'Ce montant dépasse le total : seules les espèces donnent lieu à un rendu de monnaie.';
            }
            return this.refusCredit;
        },

        get peutValider() {
            return this.lignes.length > 0 && !this.envoi && !this.remiseTropForte && !this.refusPaiement;
        },

        ar(montant) {
            return `${ariary.format(arrondi(montant))} Ar`;
        },

        qte(valeur, unite) {
            return `${quantites.format(nombre(valeur))}${unite ? ` ${unite}` : ''}`;
        },

        animerTotal(cible) {
            const depart = this.totalAffiche;
            if (mouvementReduit() || depart === cible) {
                this.totalAffiche = cible;
                return;
            }
            // Défilement discret du chiffre (180 ms) et léger battement
            this.battement = false;
            this.$nextTick(() => { this.battement = true; });
            const debut = performance.now();
            const pas = (instant) => {
                const t = Math.min(1, (instant - debut) / 180);
                this.totalAffiche = depart + (cible - depart) * (1 - (1 - t) ** 3);
                if (t < 1) {
                    requestAnimationFrame(pas);
                }
            };
            requestAnimationFrame(pas);
        },

        /* ---------- Catalogue ---------- */

        focusRecherche(force = false) {
            // Sur écran tactile, ne pas rouvrir le clavier virtuel après chaque ajout
            if (force || pointeurPrecis()) {
                this.$refs.recherche?.focus();
            }
        },

        saisir() {
            clearTimeout(this.minuteur);
            this.minuteur = setTimeout(() => this.charger(), 220);
        },

        choisirCategorie(id) {
            this.categorie = this.categorie === id ? null : id;
            this.charger();
        },

        async charger() {
            const numero = ++this.requete;
            const params = new URLSearchParams();
            if (this.recherche.trim() !== '') {
                params.set('q', this.recherche.trim());
            }
            if (this.categorie) {
                params.set('categorie', this.categorie);
            }
            this.chargement = true;
            try {
                const reponse = await fetch(`${this.urls.catalogue}?${params}`, { headers: { Accept: 'application/json' } });
                if (!reponse.ok) {
                    throw new Error(String(reponse.status));
                }
                const produits = await reponse.json();
                this.serveurInjoignable = false;
                // Une réponse plus ancienne ne remplace jamais une plus récente
                if (numero === this.requete) {
                    this.produits = produits;
                }
                return produits;
            } catch {
                if (numero === this.requete) {
                    this.serveurInjoignable = true;
                    window.toast?.('erreur', 'Catalogue indisponible : serveur injoignable.');
                }
                return [];
            } finally {
                if (numero === this.requete) {
                    this.chargement = false;
                }
            }
        },

        // Entrée dans la recherche : un code-barres scanné ajoute directement le produit
        async valider() {
            clearTimeout(this.minuteur);
            const terme = this.recherche.trim();
            if (terme === '') {
                return;
            }
            const produits = await this.charger();
            const exact = produits.find((p) => p.code_barres && p.code_barres === terme)
                ?? produits.find((p) => p.reference.toLowerCase() === terme.toLowerCase())
                ?? (produits.length === 1 ? produits[0] : null);
            if (exact) {
                this.ajouter(exact);
                this.recherche = '';
                this.charger();
            } else if (produits.length === 0) {
                window.toast?.('alerte', `Aucun produit actif ne correspond à « ${terme} ».`);
            }
        },

        enRupture(produit) {
            return !this.stockNegatif && produit.stock <= 0;
        },

        dansLeTicket(produitId) {
            return this.lignes.filter((l) => l.produit_id === produitId).reduce((s, l) => s + nombre(l.quantite), 0);
        },

        ajouter(produit) {
            if (this.enRupture(produit)) {
                window.toast?.('alerte', `« ${produit.nom} » est en rupture de stock.`);
                return;
            }
            if (!this.stockNegatif && this.dansLeTicket(produit.produit_id) + 1 > produit.stock + 0.0005) {
                window.toast?.('alerte', `Stock insuffisant pour « ${produit.nom} » : ${this.qte(produit.stock, produit.unite)} disponible(s).`);
                return;
            }
            const existante = this.lignes.find((l) => l.produit_id === produit.produit_id && l.tarif === 'detail');
            if (existante) {
                existante.quantite = String(nombre(existante.quantite) + 1);
                existante.stock = produit.stock;
            } else {
                this.lignes.push({
                    cle: `${produit.produit_id}-${Date.now()}`,
                    produit_id: produit.produit_id,
                    nom: produit.nom,
                    reference: produit.reference,
                    unite: produit.unite,
                    prix_vente: produit.prix_vente,
                    prix_gros: produit.prix_gros,
                    stock: produit.stock,
                    tarif: 'detail',
                    quantite: '1',
                    remise: '',
                });
            }
            this.focusRecherche();
        },

        /* ---------- Ticket ---------- */

        changerQuantite(ligne, pas) {
            const valeur = Math.max(0, nombre(ligne.quantite) + pas);
            if (valeur <= 0) {
                this.retirer(this.lignes.indexOf(ligne));
                return;
            }
            if (pas > 0 && !this.stockNegatif && this.dansLeTicket(ligne.produit_id) + pas > ligne.stock + 0.0005) {
                window.toast?.('alerte', `Stock insuffisant pour « ${ligne.nom} » : ${this.qte(ligne.stock, ligne.unite)} disponible(s).`);
                return;
            }
            ligne.quantite = String(Math.round(valeur * 1000) / 1000);
        },

        quantiteInvalide(ligne) {
            return nombre(ligne.quantite) <= 0
                || (!this.stockNegatif && this.dansLeTicket(ligne.produit_id) > ligne.stock + 0.0005);
        },

        messageQuantite(ligne) {
            return nombre(ligne.quantite) <= 0
                ? 'Quantité invalide.'
                : `Stock insuffisant : ${this.qte(ligne.stock, ligne.unite)} disponible(s).`;
        },

        retirer(index) {
            const [ligne] = this.lignes.splice(index, 1);
            if (!ligne) {
                return;
            }
            // Annulation possible pendant 5 secondes
            window.toast?.('info', `« ${ligne.nom} » retiré du ticket.`, 5000, {
                libelle: 'Annuler',
                action: () => this.lignes.splice(Math.min(index, this.lignes.length), 0, ligne),
            });
        },

        basculerTarif(ligne) {
            if (ligne.prix_gros !== null) {
                ligne.tarif = ligne.tarif === 'gros' ? 'detail' : 'gros';
            }
        },

        viderTicket() {
            this.lignes = [];
            this.client = this.comptoir;
            this.remiseGlobale = { valeur: '', type: 'montant' };
            this.mode = 'especes';
            this.recu = '';
            this.erreur = '';
            this.$dispatch('fermer-modal');
            this.focusRecherche(true);
        },

        /* ---------- Client (F4) ---------- */

        ouvrirClient() {
            this.rechercheClient = '';
            this.chercherClients();
            this.$dispatch('ouvrir-modal', 'caisse-client');
        },

        async chercherClients() {
            this.chargementClients = true;
            try {
                const reponse = await fetch(`${this.urls.clients}?q=${encodeURIComponent(this.rechercheClient.trim())}`, { headers: { Accept: 'application/json' } });
                this.clientsTrouves = reponse.ok ? await reponse.json() : [];
            } catch {
                window.toast?.('erreur', 'Recherche de clients impossible. Vérifiez la connexion.');
            } finally {
                this.chargementClients = false;
            }
        },

        choisirClient(client) {
            this.client = client;
            if (this.mode === 'credit' && this.refusCredit) {
                this.mode = 'especes';
            }
            this.$dispatch('fermer-modal', 'caisse-client');
        },

        /* ---------- Remise (F8) ---------- */

        ouvrirRemise() {
            if (!this.remiseAutorisee) {
                window.toast?.('alerte', 'Vous n\'avez pas le droit d\'accorder une remise.');
                return;
            }
            this.$dispatch('ouvrir-modal', 'caisse-remise');
        },

        /* ---------- Paiement (F9) ---------- */

        ouvrirPaiement() {
            if (this.lignes.length === 0) {
                window.toast?.('info', 'Le ticket est vide : ajoutez au moins un produit.');
                this.focusRecherche(true);
                return;
            }
            this.erreur = '';
            this.choisirMode(this.mode === 'credit' && this.refusCreditSansReste() ? 'especes' : this.mode);
            this.$dispatch('ouvrir-modal', 'caisse-paiement');
        },

        refusCreditSansReste() {
            return this.client.comptoir || this.client.plafond === null;
        },

        choisirMode(mode) {
            this.mode = mode;
            this.billetsSaisis = false;
            if (mode === 'credit') {
                this.recu = '';
                return;
            }
            // Hors espèces, le montant exact est proposé
            if (mode !== 'especes' || nombre(this.recu) <= 0) {
                this.recu = String(this.total);
            }
            this.$nextTick(() => document.getElementById('caisse-recu')?.select());
        },

        ajouterBillet(valeur) {
            const actuel = nombre(this.recu);
            // Premier billet : il remplace le montant exact proposé
            this.recu = String((actuel === this.total && !this.billetsSaisis ? 0 : actuel) + valeur);
            this.billetsSaisis = true;
        },

        montantExact() {
            this.recu = String(this.total);
            this.billetsSaisis = false;
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
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify({
                        client_id: this.client.id,
                        lignes: this.lignes.map((l) => ({
                            produit_id: l.produit_id,
                            quantite: nombre(l.quantite),
                            tarif: l.tarif,
                            remise: nombre(l.remise),
                        })),
                        remise: this.remiseGlobaleMontant,
                        mode_paiement: this.mode,
                        montant_recu: this.mode === 'credit' ? 0 : nombre(this.recu),
                    }),
                });
                const donnees = await reponse.json().catch(() => ({}));
                this.serveurInjoignable = false;

                if (reponse.status === 201) {
                    this.reussite = { ...donnees, mode: this.mode, client: this.client.nom };
                    this.$dispatch('fermer-modal', 'caisse-paiement');
                    this.lignes = [];
                    this.client = this.comptoir;
                    this.remiseGlobale = { valeur: '', type: 'montant' };
                    this.recu = '';
                    this.mode = 'especes';
                    this.effacerSauvegarde();
                    this.charger(); // stocks à jour
                    this.$nextTick(() => document.getElementById('caisse-nouvelle-vente')?.focus());
                    return;
                }
                if (reponse.status === 419) {
                    this.erreur = 'Votre session a expiré : rechargez la page (le ticket est conservé).';
                } else if (reponse.status === 422) {
                    this.erreur = donnees.errors ? Object.values(donnees.errors).flat()[0] : (donnees.message ?? 'Vente refusée.');
                } else {
                    this.erreur = 'Erreur inattendue : la vente n\'a pas été enregistrée. Réessayez.';
                }
            } catch {
                this.serveurInjoignable = true;
                this.erreur = 'Serveur injoignable : la vente n\'a pas été enregistrée. Le ticket est conservé, réessayez.';
            } finally {
                this.envoi = false;
            }
        },

        nouvelleVente() {
            this.reussite = null;
            this.onglet = 'catalogue';
            this.$nextTick(() => this.focusRecherche(true));
        },

        /* ---------- Raccourcis clavier ---------- */

        raccourci(e) {
            const dialogue = document.querySelector('dialog[open]');

            if (this.reussite) {
                if (e.key === 'Escape' || e.key === 'F2') {
                    e.preventDefault();
                    this.nouvelleVente();
                }
                return;
            }

            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                if (dialogue?.id === 'caisse-paiement') {
                    this.enregistrer();
                } else if (!dialogue) {
                    this.ouvrirPaiement();
                }
                return;
            }

            const actions = {
                F1: () => this.$dispatch('ouvrir-modal', 'caisse-aide'),
                F2: () => { this.onglet = 'catalogue'; this.$nextTick(() => this.focusRecherche(true)); },
                F4: () => this.ouvrirClient(),
                F8: () => this.ouvrirRemise(),
                F9: () => this.ouvrirPaiement(),
            };

            if (actions[e.key]) {
                e.preventDefault();
                if (dialogue) {
                    this.$dispatch('fermer-modal');
                }
                actions[e.key]();
                return;
            }

            // Échap hors modale (les modales se ferment seules) : annuler la vente en cours
            if (e.key === 'Escape' && !dialogue && this.lignes.length > 0) {
                e.preventDefault();
                this.$dispatch('ouvrir-modal', 'caisse-abandon');
            }
        },

        /* ---------- Conservation locale du ticket ---------- */

        sauvegarder() {
            try {
                if (this.lignes.length === 0 && this.client.id === this.comptoir.id && nombre(this.remiseGlobale.valeur) === 0) {
                    this.effacerSauvegarde();
                    return;
                }
                localStorage.setItem(this.cleStockage, JSON.stringify({
                    lignes: this.lignes, client: this.client, remiseGlobale: this.remiseGlobale, date: Date.now(),
                }));
            } catch {
                // Stockage indisponible (navigation privée) : la caisse fonctionne sans
            }
        },

        effacerSauvegarde() {
            try {
                localStorage.removeItem(this.cleStockage);
            } catch {
                // Rien à effacer
            }
        },

        restaurer() {
            try {
                const sauvegarde = JSON.parse(localStorage.getItem(this.cleStockage) ?? 'null');
                if (!sauvegarde || !Array.isArray(sauvegarde.lignes)) {
                    return;
                }
                this.lignes = sauvegarde.lignes;
                this.client = sauvegarde.client ?? this.comptoir;
                this.remiseGlobale = sauvegarde.remiseGlobale ?? { valeur: '', type: 'montant' };
                if (this.lignes.length) {
                    // Différé : le conteneur des toasts doit être prêt
                    setTimeout(() => window.toast?.('info', 'Ticket en cours restauré.'), 300);
                }
            } catch {
                this.effacerSauvegarde();
            }
        },
    }));
}
