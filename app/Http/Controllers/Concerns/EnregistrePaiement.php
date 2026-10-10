<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\ModePaiement;
use App\Exceptions\OperationRefuseeException;
use App\Http\Requests\PaiementRequest;
use App\Models\Achat;
use App\Models\Vente;
use App\Services\PaiementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

/**
 * Paiement ultérieur d'une vente ou d'un achat depuis l'interface : réponse JSON (modale des pages
 * Créances et Dettes) ou retour à la page (fiches), avec le lien du reçu PDF.
 *
 * @property-read PaiementService $paiements
 */
trait EnregistrePaiement
{
    protected function enregistrerPaiement(PaiementRequest $requete, Achat|Vente $document): JsonResponse|RedirectResponse
    {
        try {
            $paiement = $this->paiements->enregistrerUlterieur(
                $document,
                (float) $requete->validated('montant'),
                ModePaiement::from($requete->validated('mode')),
                $requete->validated('reference'),
                Carbon::parse($requete->validated('date_paiement'))->setTimeFrom(now()),
            );
        } catch (OperationRefuseeException $erreur) {
            return $requete->expectsJson()
                ? response()->json(['message' => $erreur->getMessage(), 'errors' => ['montant' => [$erreur->getMessage()]]], 422)
                : back()->withInput()->with('erreur', $erreur->getMessage());
        }

        $document->refresh();
        $message = (float) $document->reste_a_payer <= 0
            ? "Paiement {$paiement->numero} enregistré : {$document->numero} est soldé."
            : "Paiement {$paiement->numero} enregistré. Reste à payer : ".format_ar($document->reste_a_payer).'.';
        $recu = route('paiements.recu', $paiement);

        if ($requete->expectsJson()) {
            return response()->json([
                'message' => $message,
                'numero' => $paiement->numero,
                'reste' => (float) $document->reste_a_payer,
                'url_recu' => $recu,
            ]);
        }

        return back()->with('succes', $message)
            ->with('toast_lien', ['libelle' => 'Imprimer le reçu', 'url' => $recu, 'nouvelOnglet' => true, 'pdf' => true]);
    }
}
