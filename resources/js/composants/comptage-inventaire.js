/**
 * Saisie du comptage d'un inventaire, pensée pour la tablette : gros champs numériques, Entrée enregistre la
 * ligne puis passe à la suivante visible, recherche, filtres (tous, non comptés, avec écart), progression.
 * Chaque ligne est enregistrée seule en JSON ; l'écart affiché est provisoire (stock actuel) jusqu'à la validation.
 */
const quantites = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 3 });
const jetonCsrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const nombre = (valeur) => {
    const texte = String(valeur ?? '').replace(/[\s  ]/g, '').replace(',', '.');
    if (texte === '') {
        return null;
    }
    const n = Number(texte);
    return Number.isFinite(n) ? n : NaN;
};

export default function (Alpine) {
    Alpine.data('comptageInventaire', ({ lignes, lectureSeule, urlLigne }) => ({
        lignes: lignes.map((l) => ({ ...l, saisie: l.compte === null ? '' : String(l.compte), etat: '', message: '' })),
        lectureSeule,
        recherche: '',
        filtre: 'tous',

        get visibles() {
            const terme = this.recherche.trim().toLowerCase();
            return this.lignes.filter((l) => {
                if (this.filtre === 'non_comptes' && l.compte !== null) {
                    return false;
                }
                if (this.filtre === 'ecarts' && !(l.ecart !== null && l.ecart !== 0)) {
                    return false;
                }
                return terme === ''
                    || l.nom.toLowerCase().includes(terme)
                    || l.reference.toLowerCase().includes(terme)
                    || l.code_barres === terme;
            });
        },

        get total() {
            return this.lignes.length;
        },

        get comptees() {
            return this.lignes.filter((l) => l.compte !== null).length;
        },

        get pourcentage() {
            return this.total === 0 ? 0 : Math.round((this.comptees / this.total) * 100);
        },

        get avecEcart() {
            return this.lignes.filter((l) => l.ecart !== null && l.ecart !== 0).length;
        },

        /** Couleur de l'écart : vert (surplus), rouge (manquant), neutre (juste ou non compté). */
        couleur(ligne) {
            if (ligne.ecart === null || ligne.ecart === 0) {
                return 'text-texte-doux';
            }
            return ligne.ecart > 0 ? 'text-succes-texte' : 'text-danger-texte';
        },

        ecartTexte(ligne) {
            if (ligne.ecart === null) {
                return '—';
            }
            return `${ligne.ecart > 0 ? '+' : ligne.ecart < 0 ? '−' : ''}${this.qte(Math.abs(ligne.ecart), ligne.unite)}`;
        },

        qte(valeur, unite) {
            return `${quantites.format(valeur)}${unite ? ` ${unite}` : ''}`;
        },

        /** Entrée : passe tout de suite à la ligne visible suivante, puis enregistre celle-ci. */
        async toucheEntree(ligne) {
            const visibles = this.visibles;
            const suivante = visibles[visibles.indexOf(ligne) + 1];
            if (suivante) {
                const champ = document.getElementById(`compte-${suivante.id}`);
                champ?.focus();
                champ?.select?.();
            }
            await this.enregistrer(ligne);
        },

        async enregistrer(ligne) {
            if (this.lectureSeule) {
                return;
            }
            const valeur = nombre(ligne.saisie);
            if (valeur === ligne.compte) {
                return; // inchangé
            }
            if (Number.isNaN(valeur) || (valeur !== null && valeur < 0)) {
                ligne.etat = 'erreur';
                ligne.message = 'Saisissez une quantité positive.';
                return;
            }

            ligne.etat = 'envoi';
            ligne.message = '';
            try {
                const reponse = await fetch(urlLigne.replace('__LIGNE__', ligne.id), {
                    method: 'PATCH',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': jetonCsrf() },
                    body: JSON.stringify({ quantite: valeur }),
                });
                const donnees = await reponse.json().catch(() => ({}));

                if (!reponse.ok) {
                    ligne.etat = 'erreur';
                    ligne.message = donnees.errors?.quantite?.[0] ?? donnees.message ?? 'Comptage non enregistré.';
                    return;
                }
                Object.assign(ligne, { compte: donnees.compte, ecart: donnees.ecart, actuel: donnees.actuel, etat: 'ok' });
            } catch {
                ligne.etat = 'erreur';
                ligne.message = 'Connexion impossible : comptage non enregistré.';
            }
        },
    }));
}
