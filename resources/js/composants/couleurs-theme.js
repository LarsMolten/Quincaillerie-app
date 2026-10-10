/**
 * Couleurs des jetons du thème pour les graphiques (Chart.js) :
 * - couleurJeton('primaire') lit la variable CSS --primaire et la convertit en rgb() : Chart.js calcule
 *   lui-même les teintes de survol et ne sait pas lire oklch(). Le navigateur peint la couleur sur un pixel ;
 * - observerTheme(rappel) appelle le rappel quand le mode clair / sombre change (classe « dark » de <html>).
 */
const pixel = document.createElement('canvas').getContext('2d', { willReadFrequently: true });

export function couleurJeton(nom, opacite = 1) {
    const valeur = getComputedStyle(document.documentElement).getPropertyValue(`--${nom}`).trim();
    if (!pixel || valeur === '') {
        return valeur;
    }
    pixel.clearRect(0, 0, 1, 1);
    pixel.fillStyle = valeur;
    pixel.fillRect(0, 0, 1, 1);
    const [r, g, b] = pixel.getImageData(0, 0, 1, 1).data;

    return opacite < 1 ? `rgba(${r}, ${g}, ${b}, ${opacite})` : `rgb(${r}, ${g}, ${b})`;
}

export function observerTheme(rappel) {
    const observateur = new MutationObserver(rappel);
    observateur.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

    return observateur;
}
