/**
 * Page « Dépenses » : modale unique de saisie / modification (envoi JSON) et confirmation de suppression
 * (Administrateur). Après chaque enregistrement, la liste, le total et l'anneau sont rechargés
 * (événement « depenses-modifiees » écouté par listeDynamique).
 */
const jetonCsrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const aujourdhui = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};
const vide = () => ({ categorie: '', libelle: '', montant: '', date_depense: aujourdhui(), mode_paiement: 'especes', notes: '' });

export default function (Alpine) {
    Alpine.data('depenses', ({ urlCreation }) => ({
        formulaire: vide(),
        cible: null, // { url, libelle } en modification
        erreurs: {},
        envoi: false,
        suppression: null, // { url, libelle, montant }

        nouvelle() {
            this.formulaire = vide();
            this.cible = null;
            this.erreurs = {};
            this.$dispatch('ouvrir-modal', 'modale-depense');
        },

        modifier(depense) {
            const { url, ...champs } = depense;
            this.formulaire = { ...vide(), ...champs, montant: String(champs.montant), notes: champs.notes ?? '' };
            this.cible = { url, libelle: champs.libelle };
            this.erreurs = {};
            this.$dispatch('ouvrir-modal', 'modale-depense');
        },

        async enregistrer() {
            if (this.envoi) {
                return;
            }
            this.envoi = true;
            this.erreurs = {};
            try {
                const reponse = await fetch(this.cible?.url ?? urlCreation, {
                    method: this.cible ? 'PUT' : 'POST',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': jetonCsrf() },
                    body: JSON.stringify(this.formulaire),
                });
                const donnees = await reponse.json().catch(() => ({}));

                if (reponse.ok) {
                    this.$dispatch('fermer-modal', 'modale-depense');
                    window.toast?.('succes', donnees.message);
                    this.$dispatch('depenses-modifiees');
                } else if (reponse.status === 422) {
                    this.erreurs = donnees.errors ?? {};
                } else if (reponse.status === 419) {
                    window.toast?.('erreur', 'Votre session a expiré : rechargez la page.');
                } else {
                    window.toast?.('erreur', donnees.message ?? 'La dépense n\'a pas été enregistrée. Réessayez.');
                }
            } catch {
                window.toast?.('erreur', 'Connexion impossible : la dépense n\'a pas été enregistrée.');
            } finally {
                this.envoi = false;
            }
        },

        confirmerSuppression(depense) {
            this.suppression = depense;
            this.$dispatch('ouvrir-modal', 'modale-suppression-depense');
        },

        async supprimer() {
            if (this.envoi || !this.suppression) {
                return;
            }
            this.envoi = true;
            try {
                const reponse = await fetch(this.suppression.url, {
                    method: 'DELETE',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': jetonCsrf() },
                });
                const donnees = await reponse.json().catch(() => ({}));
                this.$dispatch('fermer-modal', 'modale-suppression-depense');

                if (reponse.ok) {
                    window.toast?.('succes', donnees.message);
                    this.$dispatch('depenses-modifiees');
                } else {
                    window.toast?.('erreur', donnees.message ?? 'La dépense n\'a pas été supprimée.');
                }
            } catch {
                window.toast?.('erreur', 'Connexion impossible : la dépense n\'a pas été supprimée.');
            } finally {
                this.envoi = false;
                this.suppression = null;
            }
        },
    }));
}
