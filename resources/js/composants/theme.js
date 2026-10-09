/**
 * Bascule de thème clair / sombre / auto.
 * Le choix est mémorisé dans localStorage et, si l'utilisateur est connecté,
 * enregistré dans utilisateurs.preference_theme. Le premier rendu est géré
 * par le script inline du <head> (partials/script-theme) pour éviter tout flash.
 */
const MODES = ['clair', 'sombre', 'auto'];
const LIBELLES = { clair: 'Thème clair', sombre: 'Thème sombre', auto: 'Thème automatique (système)' };
const systemeSombre = window.matchMedia('(prefers-color-scheme: dark)');

export function appliquerTheme(mode) {
    const sombre = mode === 'sombre' || (mode === 'auto' && systemeSombre.matches);
    document.documentElement.classList.toggle('dark', sombre);
    document.documentElement.dataset.theme = mode;
}

export default function (Alpine) {
    Alpine.data('theme', () => ({
        mode: document.documentElement.dataset.theme || 'auto',

        init() {
            // En mode auto, suivre les changements du système en direct
            systemeSombre.addEventListener('change', () => this.mode === 'auto' && appliquerTheme('auto'));
        },

        get libelle() {
            return LIBELLES[this.mode];
        },

        choisir(mode) {
            if (!MODES.includes(mode)) {
                return;
            }
            this.mode = mode;
            appliquerTheme(mode);

            try {
                localStorage.setItem('theme', mode);
            } catch {
                // Stockage indisponible (navigation privée) : le choix reste valable pour la page
            }

            this.enregistrer(mode);
        },

        suivant() {
            this.choisir(MODES[(MODES.indexOf(this.mode) + 1) % MODES.length]);
        },

        enregistrer(mode) {
            const url = document.querySelector('meta[name="preference-theme-url"]')?.content;
            const jeton = document.querySelector('meta[name="csrf-token"]')?.content;

            if (!url || !jeton) {
                return;
            }

            fetch(url, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': jeton },
                body: JSON.stringify({ preference_theme: mode }),
            })
                .then((reponse) => {
                    if (!reponse.ok) {
                        throw new Error(reponse.statusText);
                    }
                })
                .catch(() => window.toast?.('erreur', 'Préférence de thème non enregistrée.'));
        },
    }));
}
