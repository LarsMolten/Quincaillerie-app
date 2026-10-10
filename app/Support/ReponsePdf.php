<?php

namespace App\Support;

use Barryvdh\DomPDF\PDF;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Réponse d'un document PDF (ticket, bon d'achat, étiquettes).
 *
 * - Navigation classique : le PDF, affiché dans le navigateur (inline).
 * - Demande JSON (directive x-ouvrir-pdf) : le PDF encodé en base64. Les gestionnaires de téléchargement
 *   intégrés au navigateur (Internet Download Manager…) interceptent toute réponse application/pdf,
 *   y compris un fetch(), et la remplacent par un « 204 No Content » : le ticket ne s'affichait jamais.
 *   Le navigateur reconstruit ici le PDF localement et l'ouvre depuis une URL blob:.
 */
class ReponsePdf
{
    public static function depuis(Request $requete, PDF $pdf, string $nomFichier): Response|JsonResponse
    {
        if ($requete->expectsJson()) {
            return response()->json([
                'nom' => $nomFichier,
                'pdf' => base64_encode($pdf->output()),
            ]);
        }

        return $pdf->stream($nomFichier);
    }
}
