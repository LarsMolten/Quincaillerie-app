/**
 * Graphique des ventes du tableau de bord (Chart.js chargé à la demande) : courbe en aire des ventes nettes
 * sur 7, 30 ou 90 jours, données JSON chargées à chaque changement de période (squelette pendant la requête).
 * Couleurs lues dans les variables CSS du thème, mises à jour au changement de mode clair / sombre.
 */
import { couleurJeton, observerTheme } from './couleurs-theme.js';

const ariary = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });
const compact = new Intl.NumberFormat('fr-FR', { notation: 'compact', maximumFractionDigits: 1 });

export default function (Alpine) {
    Alpine.data('graphiqueVentes', ({ url, jours = 30 }) => {
        // Hors de l'état réactif d'Alpine (voir graphique-anneau.js)
        let graphique = null;
        let observateur = null;
        let Chart = null;

        const colorer = () => {
            if (!graphique) {
                return;
            }
            const jeu = graphique.data.datasets[0];
            jeu.borderColor = couleurJeton('primaire');
            jeu.backgroundColor = couleurJeton('primaire', 0.15);
            jeu.pointBackgroundColor = couleurJeton('primaire');
            for (const axe of [graphique.options.scales.x, graphique.options.scales.y]) {
                axe.ticks.color = couleurJeton('texte-doux');
                axe.grid.color = couleurJeton('bordure');
            }
            graphique.update('none');
        };

        return {
            jours,
            chargement: true,
            erreur: false,
            donnees: null,

            async init() {
                observateur = observerTheme(colorer);
                await this.charger(this.jours);
            },

            async charger(nombre) {
                this.jours = nombre;
                this.chargement = true;
                this.erreur = false;
                try {
                    const [reponse, module] = await Promise.all([
                        fetch(`${url}?jours=${nombre}`, { headers: { Accept: 'application/json' } }),
                        Chart ? Promise.resolve({ Chart }) : import('./graphiques-chart.js'),
                    ]);
                    Chart = module.Chart;
                    if (!reponse.ok) {
                        throw new Error(String(reponse.status));
                    }
                    this.donnees = await reponse.json();
                    this.chargement = false;
                    await this.$nextTick();
                    this.dessiner();
                } catch {
                    this.chargement = false;
                    this.erreur = true;
                }
            },

            dessiner() {
                const { libelles, valeurs } = this.donnees;
                if (graphique) {
                    graphique.data.labels = libelles;
                    graphique.data.datasets[0].data = valeurs;
                    graphique.update();
                    return;
                }
                const reduit = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                graphique = new Chart(this.$refs.canvas, {
                    type: 'line',
                    data: {
                        labels: libelles,
                        // Interpolation monotone : la courbe ne dépasse jamais les valeurs réelles (pas de creux sous zéro)
                        datasets: [{ data: valeurs, fill: true, cubicInterpolationMode: 'monotone', borderWidth: 2, pointRadius: 0, pointHoverRadius: 4 }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: reduit ? false : { duration: 200 },
                        interaction: { mode: 'index', intersect: false },
                        scales: {
                            x: { grid: { display: false }, ticks: { maxTicksLimit: 8, maxRotation: 0 } },
                            y: { beginAtZero: true, border: { display: false }, ticks: { maxTicksLimit: 5, callback: (v) => compact.format(v) } },
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: { label: (c) => ` ${ariary.format(c.raw)} Ar` } },
                        },
                    },
                });
                colorer();
            },

            ar(montant) {
                return `${ariary.format(Math.round(montant ?? 0))} Ar`;
            },

            destroy() {
                observateur?.disconnect();
                graphique?.destroy();
            },

            /** Couleur de la courbe (tests et vérifications). */
            couleurCourbe() {
                return graphique?.data.datasets[0].borderColor ?? null;
            },
        };
    });
}
