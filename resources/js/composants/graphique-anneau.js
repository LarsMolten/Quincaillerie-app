/**
 * Graphique en anneau (Chart.js chargé à la demande). Couleurs lues dans les jetons du thème
 * (--graphique-N, --surface) : aucune couleur codée en dur ; redessiné au changement de thème.
 * Sans animation si l'utilisateur préfère réduire les mouvements.
 *
 * Utilisation : <div x-data="graphiqueAnneau({ series: [{ libelle, valeur, jeton }], unite: 'Ar' })"><canvas x-ref="canvas"></canvas></div>
 * La légende accessible (liste des valeurs) est rendue côté serveur à côté du canvas.
 */
const ariary = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });
const pixel = document.createElement('canvas').getContext('2d', { willReadFrequently: true });

/**
 * Couleur d'un jeton convertie en rgb() : Chart.js calcule lui-même les teintes de survol
 * et ne sait pas lire oklch(). Le navigateur peint la couleur sur un pixel, qu'on relit.
 */
const jeton = (nom) => {
    const valeur = getComputedStyle(document.documentElement).getPropertyValue(`--${nom}`).trim();
    if (!pixel || valeur === '') {
        return valeur;
    }
    pixel.clearRect(0, 0, 1, 1);
    pixel.fillStyle = valeur;
    pixel.fillRect(0, 0, 1, 1);
    const [r, g, b] = pixel.getImageData(0, 0, 1, 1).data;

    return `rgb(${r}, ${g}, ${b})`;
};

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
            jeu.backgroundColor = series.map((s) => jeton(s.jeton));
            jeu.borderColor = jeton('surface');
            graphique.update('none');
        };

        return {
            async init() {
                if (series.length === 0) {
                    return;
                }
                const { Chart } = await import('./anneau-chart.js');
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

                // Mode clair / sombre : la classe « dark » change sur <html>
                observateur = new MutationObserver(colorer);
                observateur.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
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
