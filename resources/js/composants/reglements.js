/**
 * Pages « Créances » et « Dettes fournisseurs » : détail dépliable de chaque client ou fournisseur
 * (documents impayés chargés en fragment) et modale de paiement envoyée en JSON.
 *
 * Après un paiement : toast avec le lien du reçu PDF (ouvert via x-ouvrir-pdf), puis la liste et
 * les détails ouverts sont rechargés (événement « reglement-enregistre »).
 */
const jetonCsrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const aujourdhui = () => {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
};

export default function (Alpine) {
    Alpine.data('reglements', () => ({
        details: {},
        reglement: { url: '', document: '', tiers: '', reste: 0, montant: '', mode: 'especes', reference: '', date: '', enCours: false, erreurs: {} },

        /* ---------- Détail d'un tiers ---------- */

        async basculer(id, url) {
            const detail = this.details[id];
            if (detail?.ouvert) {
                detail.ouvert = false;
                return;
            }
            this.details[id] = { ouvert: true, url, html: detail?.html ?? '', erreur: false };
            if (!detail?.html) {
                await this.chargerDetail(id);
            }
        },

        async chargerDetail(id) {
            const detail = this.details[id];
            try {
                const reponse = await fetch(detail.url, { headers: { Accept: 'text/html', 'X-Fragment': 'detail' } });
                if (!reponse.ok) {
                    throw new Error(String(reponse.status));
                }
                detail.html = await reponse.text();
                detail.erreur = false;
            } catch {
                detail.erreur = true;
            }
        },

        /* ---------- Modale de paiement ---------- */

        ouvrirReglement({ url, document: numero, tiers, reste }) {
            this.reglement = {
                url,
                document: numero,
                tiers,
                reste: Number(reste),
                montant: String(Math.round(Number(reste))),
                mode: 'especes',
                reference: '',
                date: aujourdhui(),
                enCours: false,
                erreurs: {},
            };
            this.$dispatch('ouvrir-modal', 'modale-reglement');
            this.$nextTick(() => document.getElementById('reglement-montant')?.select());
        },

        async enregistrer() {
            if (this.reglement.enCours) {
                return;
            }
            this.reglement.enCours = true;
            this.reglement.erreurs = {};
            try {
                const reponse = await fetch(this.reglement.url, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': jetonCsrf() },
                    body: JSON.stringify({
                        montant: this.reglement.montant,
                        mode: this.reglement.mode,
                        reference: this.reglement.reference,
                        date_paiement: this.reglement.date,
                    }),
                });
                const donnees = await reponse.json().catch(() => ({}));

                if (reponse.ok) {
                    this.$dispatch('fermer-modal', 'modale-reglement');
                    window.toast?.('succes', donnees.message, 9000, { libelle: 'Imprimer le reçu', url: donnees.url_recu, nouvelOnglet: true, pdf: true });
                    this.rafraichir();
                } else if (reponse.status === 422) {
                    this.reglement.erreurs = donnees.errors ?? { montant: [donnees.message ?? 'Paiement refusé.'] };
                } else if (reponse.status === 419) {
                    window.toast?.('erreur', 'Votre session a expiré : rechargez la page.');
                } else {
                    window.toast?.('erreur', 'Le paiement n\'a pas été enregistré. Réessayez.');
                }
            } catch {
                window.toast?.('erreur', 'Connexion impossible : le paiement n\'a pas été enregistré.');
            } finally {
                this.reglement.enCours = false;
            }
        },

        /** Recharge la liste (synthèse comprise) et les détails ouverts ; les autres seront rechargés à l'ouverture. */
        rafraichir() {
            this.$dispatch('reglement-enregistre');
            for (const [id, detail] of Object.entries(this.details)) {
                if (detail.ouvert) {
                    this.chargerDetail(id);
                } else {
                    detail.html = '';
                }
            }
        },
    }));
}
