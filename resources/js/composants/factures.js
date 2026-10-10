/**
 * Page « Factures » : aperçu PDF dans une modale (A4 / ticket 80 mm), impression, téléchargement,
 * envoi par email et lien de partage WhatsApp.
 *
 * Le PDF est toujours demandé en JSON (base64) puis affiché depuis une URL blob: :
 * les gestionnaires de téléchargement (IDM…) interceptent les réponses application/pdf (voir ouvrir-pdf.js).
 */
import { versBlobPdf } from './ouvrir-pdf.js';

const jetonCsrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export default function (Alpine) {
    Alpine.data('factures', ({ urlFiche, apercu = null }) => ({
        facture: null,
        format: 'a4',
        chargement: false,
        erreur: '',
        adresse: null,
        nomFichier: 'facture.pdf',
        envoi: { email: '', message: '', enCours: false, erreurs: {} },
        partage: { url: '', whatsapp: '', expiration: '', enCours: false },

        init() {
            if (apercu) {
                this.ouvrirApercu(apercu);
            }
        },

        destroy() {
            this.liberer();
        },

        /** Métadonnées de la facture (numéro, client, adresses des actions). */
        async charger(id) {
            if (this.facture?.id === id) {
                return this.facture;
            }
            const reponse = await fetch(urlFiche.replace('__ID__', id), { headers: { Accept: 'application/json' } });
            if (!reponse.ok) {
                throw new Error(String(reponse.status));
            }
            this.facture = await reponse.json();

            return this.facture;
        },

        async ouvrirApercu(id) {
            try {
                await this.charger(id);
            } catch {
                window.toast?.('erreur', 'Facture introuvable ou inaccessible.');
                return;
            }
            this.$dispatch('ouvrir-modal', 'facture-apercu');
            this.afficher(this.format);
        },

        async afficher(format) {
            this.format = format;
            this.chargement = true;
            this.erreur = '';
            try {
                const reponse = await fetch(this.facture.urls[format], {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!reponse.ok) {
                    throw new Error(String(reponse.status));
                }
                const { nom, pdf } = await reponse.json();
                this.liberer();
                this.adresse = URL.createObjectURL(versBlobPdf(pdf));
                this.nomFichier = nom;
            } catch {
                this.erreur = 'Le PDF n\'a pas pu être généré. Réessayez.';
            } finally {
                this.chargement = false;
            }
        },

        liberer() {
            if (this.adresse) {
                URL.revokeObjectURL(this.adresse);
                this.adresse = null;
            }
        },

        imprimer() {
            const cadre = document.getElementById('facture-cadre');
            try {
                cadre.contentWindow.focus();
                cadre.contentWindow.print();
            } catch {
                // Visionneuse PDF sans impression scriptable : ouverture dans un onglet (impression depuis la visionneuse)
                window.open(this.adresse, '_blank');
            }
        },

        telecharger() {
            const lien = Object.assign(document.createElement('a'), { href: this.adresse, download: this.nomFichier });
            document.body.append(lien);
            lien.click();
            lien.remove();
        },

        /* ---------- Envoi par email ---------- */

        async ouvrirEnvoi(id) {
            try {
                await this.charger(id);
            } catch {
                window.toast?.('erreur', 'Facture introuvable ou inaccessible.');
                return;
            }
            this.envoi = { email: this.facture.email ?? '', message: '', enCours: false, erreurs: {} };
            this.$dispatch('ouvrir-modal', 'facture-envoi');
        },

        async envoyer() {
            this.envoi.enCours = true;
            this.envoi.erreurs = {};
            try {
                const reponse = await fetch(this.facture.urls.envoyer, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': jetonCsrf() },
                    body: JSON.stringify({ email: this.envoi.email, message: this.envoi.message }),
                });
                const donnees = await reponse.json().catch(() => ({}));
                if (reponse.ok) {
                    this.$dispatch('fermer-modal', 'facture-envoi');
                    window.toast?.('succes', donnees.message);
                } else if (reponse.status === 422) {
                    this.envoi.erreurs = donnees.errors ?? {};
                } else if (reponse.status === 429) {
                    window.toast?.('alerte', 'Trop d\'envois rapprochés : patientez une minute.');
                } else {
                    window.toast?.('erreur', 'L\'email n\'a pas pu être envoyé. Réessayez.');
                }
            } catch {
                window.toast?.('erreur', 'Connexion impossible : l\'email n\'a pas été envoyé.');
            } finally {
                this.envoi.enCours = false;
            }
        },

        /* ---------- Partage (WhatsApp) ---------- */

        async ouvrirPartage(id) {
            try {
                await this.charger(id);
            } catch {
                window.toast?.('erreur', 'Facture introuvable ou inaccessible.');
                return;
            }
            this.partage = { url: '', whatsapp: '', expiration: '', enCours: true };
            this.$dispatch('ouvrir-modal', 'facture-partage');
            try {
                const reponse = await fetch(this.facture.urls.partager, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': jetonCsrf() },
                });
                if (!reponse.ok) {
                    throw new Error(String(reponse.status));
                }
                this.partage = { ...(await reponse.json()), enCours: false };
            } catch {
                this.$dispatch('fermer-modal', 'facture-partage');
                window.toast?.('erreur', 'Le lien de partage n\'a pas pu être créé.');
            }
        },

        async copier() {
            try {
                await navigator.clipboard.writeText(this.partage.url);
                window.toast?.('succes', 'Lien copié.');
            } catch {
                document.getElementById('facture-lien')?.select();
                window.toast?.('info', 'Sélectionnez le lien puis copiez-le (Ctrl+C).');
            }
        },
    }));
}
