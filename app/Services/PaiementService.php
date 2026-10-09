<?php

namespace App\Services;

use App\Enums\ModePaiement;
use App\Enums\StatutAchat;
use App\Enums\StatutVente;
use App\Exceptions\OperationRefuseeException;
use App\Models\Achat;
use App\Models\Paiement;
use App\Models\Vente;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Enregistre les règlements d'un document (achat : décaissement, vente : encaissement)
 * et tient à jour son montant payé et son reste à payer.
 */
class PaiementService
{
    public function enregistrer(
        Achat|Vente $document,
        float $montant,
        ModePaiement $mode,
        ?string $reference = null,
        ?Carbon $date = null,
    ): Paiement {
        $montant = round($montant, 2);

        if ($montant <= 0) {
            throw new OperationRefuseeException('Le montant du paiement doit être supérieur à 0.');
        }

        if ($mode === ModePaiement::Credit) {
            throw new OperationRefuseeException('Le crédit n\'est pas un mode de paiement : laissez un reste à payer.');
        }

        return DB::transaction(function () use ($document, $montant, $mode, $reference, $date) {
            // Verrou sur le document : deux paiements simultanés ne dépassent jamais le reste
            /** @var Achat|Vente $verrouille */
            $verrouille = $document::query()->whereKey($document->getKey())->lockForUpdate()->firstOrFail();

            if (in_array($verrouille->statut, [StatutAchat::Annule, StatutVente::Annulee], true)) {
                throw new OperationRefuseeException("Le document {$verrouille->numero} est annulé : aucun paiement possible.");
            }

            $reste = (float) $verrouille->reste_a_payer;

            if ($montant > $reste + 0.001) {
                throw new OperationRefuseeException(sprintf(
                    'Le paiement (%s) dépasse le reste à payer de %s (%s).',
                    format_ar($montant), $verrouille->numero, format_ar($reste),
                ));
            }

            $paiement = $verrouille->paiements()->create([
                'montant' => $montant,
                'mode' => $mode,
                'reference' => $reference,
                'date_paiement' => $date ?? now(),
                'utilisateur_id' => Auth::id(),
            ]);

            $verrouille->update([
                'montant_paye' => round((float) $verrouille->montant_paye + $montant, 2),
                'reste_a_payer' => round($reste - $montant, 2),
            ]);

            // L'instance reçue reflète les nouveaux montants
            $document->setRawAttributes($verrouille->getAttributes(), true);

            return $paiement;
        });
    }
}
