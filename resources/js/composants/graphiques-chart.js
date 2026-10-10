/**
 * Chart.js limité aux graphiques de l'application (anneau, courbe et barres), dans un module séparé
 * chargé à la demande : le reste de l'application ne paie pas son poids.
 */
import {
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    DoughnutController,
    Filler,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js';

Chart.register(DoughnutController, ArcElement, LineController, LineElement, PointElement, BarController, BarElement, CategoryScale, LinearScale, Filler, Tooltip);

export { Chart };
