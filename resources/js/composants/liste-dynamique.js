/**
 * Liste rechargée sans changer de page (recherche instantanée, filtres, tri, pagination).
 *
 * Utilisation :
 *   <div x-data="listeDynamique">
 *       <form x-ref="filtres" method="GET">… champs (recherche, puces) …</form>
 *       <div x-ref="liste">@include('module._liste')</div>
 *       <template x-ref="squelette"><x-squelette … /></template>
 *   </div>
 * Le contrôleur renvoie seulement le partial quand l'en-tête « X-Fragment: liste » est présent.
 * Sans JavaScript, le formulaire et les liens fonctionnent normalement (rechargement complet).
 */
const DELAI_SAISIE = 300;

export default function (Alpine) {
    Alpine.data('listeDynamique', () => ({
        chargement: false,
        minuteur: null,
        controleur: null,

        init() {
            const filtres = this.$refs.filtres;

            // Recherche : délai après la dernière frappe ; Entrée : immédiat
            filtres?.addEventListener('input', (e) => {
                if (e.target.type === 'search') {
                    clearTimeout(this.minuteur);
                    this.minuteur = setTimeout(() => this.chargerDepuisFiltres(), DELAI_SAISIE);
                }
            });
            filtres?.addEventListener('submit', (e) => {
                e.preventDefault();
                clearTimeout(this.minuteur);
                this.chargerDepuisFiltres();
            });

            // Pagination, tri et liens « effacer » à l'intérieur de la liste
            this.$el.addEventListener('click', (e) => {
                const lien = e.target.closest('a[data-liste-lien], [x-ref="liste"] nav[aria-label="Pagination"] a, [x-ref="liste"] th a');
                if (!lien || e.ctrlKey || e.metaKey || e.shiftKey) {
                    return;
                }
                e.preventDefault();
                this.charger(lien.href);
            });
        },

        // Puce de filtre : met à jour le champ caché puis recharge
        filtrer(nom, valeur) {
            const champ = this.$refs.filtres.elements[nom];
            champ.value = valeur;
            this.chargerDepuisFiltres();
        },

        chargerDepuisFiltres() {
            const url = new URL(this.$refs.filtres.action || window.location.href, window.location.origin);
            const donnees = new FormData(this.$refs.filtres);
            url.search = '';
            for (const [cle, valeur] of donnees.entries()) {
                if (valeur !== '') {
                    url.searchParams.set(cle, valeur);
                }
            }
            this.charger(url.toString());
        },

        async charger(url) {
            this.controleur?.abort();
            this.controleur = new AbortController();
            this.chargement = true;
            this.$refs.liste.setAttribute('aria-busy', 'true');

            // Squelette affiché si le chargement dépasse 150 ms (pas de clignotement sur réseau rapide)
            const contenuActuel = this.$refs.liste.innerHTML;
            const minuteurSquelette = setTimeout(() => {
                this.$refs.liste.innerHTML = this.$refs.squelette.innerHTML;
            }, 150);

            try {
                const reponse = await fetch(url, {
                    headers: { 'X-Fragment': 'liste', Accept: 'text/html' },
                    signal: this.controleur.signal,
                });
                // Session expirée (redirection vers la connexion) : rechargement complet
                if (reponse.redirected) {
                    window.location.href = reponse.url;
                    return;
                }
                if (!reponse.ok) {
                    throw new Error(reponse.statusText);
                }
                clearTimeout(minuteurSquelette);
                this.$refs.liste.innerHTML = await reponse.text();
                history.replaceState(history.state, '', url);

                // Les champs cachés des filtres (statut, tri, ordre) suivent l'URL chargée
                const parametres = new URL(url, window.location.origin).searchParams;
                for (const champ of this.$refs.filtres?.elements ?? []) {
                    if (champ.type === 'hidden' && champ.name) {
                        champ.value = parametres.get(champ.name) ?? '';
                    }
                }
                this.$dispatch('liste-chargee', { url });
            } catch (erreur) {
                clearTimeout(minuteurSquelette);
                if (erreur.name === 'AbortError') {
                    return;
                }
                this.$refs.liste.innerHTML = contenuActuel;
                window.toast?.('erreur', 'Impossible de charger la liste. Vérifiez la connexion.');
            } finally {
                this.chargement = false;
                this.$refs.liste.removeAttribute('aria-busy');
            }
        },
    }));
}
