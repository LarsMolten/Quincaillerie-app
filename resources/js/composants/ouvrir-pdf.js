/**
 * x-ouvrir-pdf : ouvre un PDF (ticket, bon d'achat, étiquettes) dans un nouvel onglet SANS naviguer
 * vers l'URL du PDF. Sur un lien <a href> ou un formulaire GET.
 * Valeur facultative : expression Alpine donnant l'URL, évaluée au clic (ex. x-ouvrir-pdf="reussite.url_ticket") ;
 * sans valeur, l'URL est le href du lien ou l'action du formulaire (avec ses champs).
 *
 * Pourquoi : les gestionnaires de téléchargement intégrés au navigateur (Internet Download Manager…)
 * interceptent toute réponse application/pdf, navigation ou fetch(), et la remplacent par un
 * « 204 No Content » : l'onglet ouvert se refermait et l'utilisateur revenait sur la caisse sans ticket.
 * Ici, l'onglet est ouvert vide au clic (geste utilisateur : pas de blocage des fenêtres surgissantes),
 * le PDF est demandé en JSON (base64, voir App\Support\ReponsePdf), reconstruit dans le navigateur,
 * puis affiché depuis une URL blob:, sans trafic réseau à intercepter. La page de départ ne change pas.
 *
 * Clic du milieu, Ctrl/Maj/⌘ + clic : comportement natif du navigateur conservé.
 */
const DUREE_URL_BLOB = 5 * 60 * 1000; // l'onglet peut recharger le PDF pendant 5 minutes

/** Décode le PDF reçu en base64. */
export function versBlobPdf(base64) {
    const binaire = atob(base64);
    const octets = new Uint8Array(binaire.length);
    for (let i = 0; i < binaire.length; i++) {
        octets[i] = binaire.charCodeAt(i);
    }

    return new Blob([octets], { type: 'application/pdf' });
}

export async function ouvrirPdf(url, onglet) {
    try {
        const reponse = await fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (!reponse.ok) {
            throw new Error(reponse.status === 403 ? 'droit' : String(reponse.status));
        }

        const { pdf } = await reponse.json();
        if (!pdf) {
            throw new Error('vide');
        }

        const adresse = URL.createObjectURL(versBlobPdf(pdf));
        onglet.location.replace(adresse);
        setTimeout(() => URL.revokeObjectURL(adresse), DUREE_URL_BLOB);
    } catch (erreur) {
        onglet.close();
        window.toast?.('erreur', erreur.message === 'droit'
            ? 'Vous n\'avez pas le droit d\'ouvrir ce document.'
            : 'Le document PDF n\'a pas pu être généré. Réessayez.');
    }
}

/** Onglet d'attente affiché pendant la génération du PDF. */
function ouvrirOngletAttente() {
    const onglet = window.open('', '_blank');
    if (onglet) {
        onglet.document.title = 'Préparation du document…';
        onglet.document.body.textContent = 'Préparation du document…';
        onglet.document.body.style.font = '16px system-ui, sans-serif';
        onglet.document.body.style.padding = '2rem';
    }
    return onglet;
}

export default function (Alpine) {
    Alpine.directive('ouvrir-pdf', (element, { expression }, { evaluateLater, cleanup }) => {
        const estFormulaire = element.tagName === 'FORM';
        const lireUrl = expression ? evaluateLater(expression) : null;

        const urlCible = () => {
            if (lireUrl) {
                let valeur = null;
                lireUrl((resultat) => { valeur = resultat; });
                return valeur ? new URL(valeur, window.location.href).href : null;
            }

            return estFormulaire
                ? `${element.action}?${new URLSearchParams(new FormData(element))}`
                : element.href;
        };

        const intercepter = (evenement) => {
            if (!estFormulaire && (evenement.button !== 0 || evenement.ctrlKey || evenement.shiftKey || evenement.metaKey || evenement.altKey)) {
                return;
            }

            const url = urlCible();

            if (!url || url.endsWith('#')) {
                return;
            }

            const onglet = ouvrirOngletAttente();
            if (!onglet) {
                // Fenêtres surgissantes bloquées : navigation native en dernier recours
                return;
            }

            evenement.preventDefault();
            ouvrirPdf(url, onglet);
        };

        const evenement = estFormulaire ? 'submit' : 'click';
        element.addEventListener(evenement, intercepter);
        cleanup(() => element.removeEventListener(evenement, intercepter));
    });
}
