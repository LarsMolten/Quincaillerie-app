/**
 * Graphique d'un rapport (Chart.js chargé à la demande) : courbe, barres (verticales ou horizontales) ou anneau.
 * Données fournies par le serveur ; couleurs lues dans les jetons du thème et recolorées en mode sombre ;
 * instance hors de l'état réactif d'Alpine. Un tableau de données accompagne chaque graphique (accessibilité).
 *
 * Utilisation :
 *   <div x-data="graphiqueRapport({ type: 'line', libelles: [...], series: [{ libelle, valeurs, jeton: 'primaire' }] })">
 *       <canvas x-ref="canvas"></canvas>
 *   </div>
 */
import { couleurJeton, observerTheme } from './couleurs-theme.js';

const ariary = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });
const compact = new Intl.NumberFormat('fr-FR', { notation: 'compact', maximumFractionDigits: 1 });

export default function (Alpine) {
    Alpine.data('graphiqueRapport', ({ type = 'line', libelles = [], series = [], horizontal = false, unite = 'Ar' }) => {
        let graphique = null;
        let observateur = null;
        const anneau = type === 'doughnut';
        const formater = (valeur) => (unite === 'Ar' ? `${ariary.format(valeur)} Ar` : `${ariary.format(valeur)} ${unite}`);

        /** Couleurs d'une série lues dans les jetons du thème courant. */
        const couleursSerie = (serie) => (anneau
            ? { backgroundColor: serie.jetons.map((j) => couleurJeton(j)), borderColor: couleurJeton('surface') }
            : { borderColor: couleurJeton(serie.jeton), backgroundColor: couleurJeton(serie.jeton, type === 'line' ? 0.12 : 0.85) });

        // Changement de thème : nouvelles couleurs puis mise à jour complète (pas « none » : Chart.js garderait
        // en cache les options déjà résolues des barres et ne les repeindrait pas)
        const colorer = () => {
            if (!graphique) {
                return;
            }
            graphique.data.datasets.forEach((jeu, i) => Object.assign(jeu, couleursSerie(series[i])));
            for (const axe of Object.values(graphique.options.scales ?? {})) {
                axe.ticks.color = couleurJeton('texte-doux');
                axe.grid.color = couleurJeton('bordure');
            }
            graphique.update();
        };

        return {
            async init() {
                if (series.length === 0 || libelles.length === 0) {
                    return;
                }
                const { Chart } = await import('./graphiques-chart.js');
                const reduit = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                const axeValeurs = { beginAtZero: true, border: { display: false }, ticks: { maxTicksLimit: 5, callback: (v) => compact.format(v) } };
                const axeLibelles = { grid: { display: false }, ticks: { maxTicksLimit: 10, maxRotation: 0, autoSkip: true } };

                graphique = new Chart(this.$refs.canvas, {
                    type,
                    data: {
                        labels: libelles,
                        datasets: series.map((s) => ({
                            ...couleursSerie(s),
                            label: s.libelle,
                            data: s.valeurs,
                            fill: type === 'line' && series.length === 1,
                            cubicInterpolationMode: 'monotone',
                            borderWidth: anneau ? 2 : 2,
                            pointRadius: 0,
                            pointHoverRadius: 4,
                            borderRadius: type === 'bar' ? 6 : 0,
                            maxBarThickness: 40,
                        })),
                    },
                    options: {
                        indexAxis: horizontal ? 'y' : 'x',
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: reduit ? false : { duration: 200 },
                        interaction: anneau ? undefined : { mode: 'index', intersect: false },
                        cutout: anneau ? '68%' : undefined,
                        scales: anneau ? {} : (horizontal ? { x: axeValeurs, y: axeLibelles } : { x: axeLibelles, y: axeValeurs }),
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: { label: (c) => ` ${series.length > 1 ? `${c.dataset.label} : ` : (anneau ? `${c.label} : ` : '')}${formater(c.raw)}` } },
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

            /** Couleur de remplissage effectivement peinte pour le premier élément (tests et vérifications). */
            couleur() {
                return graphique?.getDatasetMeta(0).data[0]?.options.backgroundColor ?? null;
            },
        };
    });
}
