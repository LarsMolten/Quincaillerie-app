/**
 * Graphique en anneau (Chart.js chargé à la demande). Couleurs lues dans les jetons du thème
 * (--graphique-N, --surface) : aucune couleur codée en dur ; redessiné au changement de thème.
 * Sans animation si l'utilisateur préfère réduire les mouvements.
 *
 * Utilisation : <div x-data="graphiqueAnneau({ series: [{ libelle, valeur, jeton }], unite: 'Ar' })"><canvas x-ref="canvas"></canvas></div>
 * La légende accessible (liste des valeurs) est rendue côté serveur à côté du canvas.
 */
import { couleurJeton, observerTheme } from './couleurs-theme.js';

const ariary = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });

export default function (Alpine) {
    Alpine.data('graphiqueAnneau', ({ series, unite = 'Ar' }) => {
        // Hors de l'état réactif d'Alpine : Chart.js ne supporte pas d'être enveloppé dans un proxy réactif
        let graphique = null;
        let observateur = null;

        const colorer = () => {
            if (!graphique) {
                return;
            }
            const jeu = graphique.data.datasets[0];
            jeu.backgroundColor = series.map((s) => couleurJeton(s.jeton));
            jeu.borderColor = couleurJeton('surface');
            graphique.update('none');
        };

        return {
            async init() {
                if (series.length === 0) {
                    return;
                }
                const { Chart } = await import('./graphiques-chart.js');
                const reduit = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

                graphique = new Chart(this.$refs.canvas, {
                    type: 'doughnut',
                    data: {
                        labels: series.map((s) => s.libelle),
                        datasets: [{ data: series.map((s) => s.valeur), borderWidth: 2, hoverOffset: 6 }],
                    },
                    options: {
                        cutout: '68%',
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: reduit ? false : { duration: 200 },
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: { label: (contexte) => ` ${contexte.label} : ${ariary.format(contexte.raw)} ${unite}` } },
                        },
                    },
                });
                colorer();
                observateur = observerTheme(colorer);
            },

            destroy() {
                observateur?.disconnect();
                graphique?.destroy();
            },

            /** Couleurs appliquées aux segments (tests et vérifications). */
            couleurs() {
                return graphique ? [...graphique.data.datasets[0].backgroundColor] : [];
            },
        };
    });
}
