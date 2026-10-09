/**
 * Protection contre les doubles clics : à l'envoi d'un formulaire portant x-chargement-envoi,
 * ses boutons de soumission (dans le formulaire, ou reliés par l'attribut form="id")
 * passent en état « chargement » (spinner + désactivés).
 */
export default function (Alpine) {
    Alpine.directive('chargement-envoi', (formulaire) => {
        formulaire.addEventListener('submit', (evenement) => {
            if (evenement.defaultPrevented) {
                return;
            }
            const boutons = [
                ...formulaire.querySelectorAll('button[type="submit"]'),
                ...(formulaire.id ? document.querySelectorAll(`button[type="submit"][form="${formulaire.id}"]`) : []),
            ];
            boutons.forEach((bouton) => {
                // Après l'envoi, pour que la valeur du bouton cliqué parte avec le formulaire
                setTimeout(() => {
                    bouton.disabled = true;
                    bouton.setAttribute('aria-busy', 'true');
                    bouton.dataset.chargement = 'true';
                });
            });
        });
    });
}
