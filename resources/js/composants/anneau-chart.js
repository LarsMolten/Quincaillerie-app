/**
 * Chart.js limité au graphique en anneau (contrôleur, arcs, infobulles), dans un module séparé
 * chargé à la demande par graphique-anneau.js : le reste de l'application ne paie pas son poids.
 */
import { ArcElement, Chart, DoughnutController, Tooltip } from 'chart.js';

Chart.register(DoughnutController, ArcElement, Tooltip);

export { Chart };
