<?php

namespace App\Services;

use App\Models\Parametre;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Numérotation séquentielle sans trou des documents : PREFIXE-AAAA-NNNNN, remise à zéro chaque année
 * (ACH-2026-00001, VTE-2026-00001, FAC-2026-00001…). CLAUDE.md §5.6.
 *
 * À appeler DANS la transaction qui crée le document : le dernier numéro de l'année est lu avec
 * FOR UPDATE ; sous InnoDB, le verrou porte aussi sur l'intervalle suivant de l'index unique « numero »,
 * ce qui bloque une création concurrente jusqu'au commit. Un document annulé garde son numéro ;
 * une transaction annulée ne consomme aucun numéro.
 *
 * Quand l'intervalle est vide (premier numéro de l'année), deux transactions peuvent poser le même
 * verrou d'intervalle puis s'interbloquer à l'insertion : MySQL en annule une. Le service appelant
 * doit donc rejouer sa transaction (DB::transaction(..., tentatives)), comme AchatService.
 */
class NumerotationService
{
    /**
     * Préfixes paramétrables (écran Paramètres > Facturation) : document => [clé du paramètre, valeur par défaut, libellé].
     * Changer un préfixe en cours d'année fait repartir la séquence à 00001 pour le nouveau préfixe.
     */
    public const PREFIXES = [
        'facture' => ['prefixe_facture', 'FAC', 'Factures'],
        'vente' => ['prefixe_vente', 'VTE', 'Ventes'],
        'achat' => ['prefixe_achat', 'ACH', 'Achats'],
        'paiement' => ['prefixe_recu', 'REC', 'Reçus de paiement'],
        'retour' => ['prefixe_retour', 'RET', 'Retours'],
        'inventaire' => ['prefixe_inventaire', 'INV', 'Inventaires'],
    ];

    /** Préfixe en vigueur pour un type de document (ex. « vente » → « VTE »). */
    public function prefixe(string $document): string
    {
        [$cle, $defaut] = self::PREFIXES[$document];

        return strtoupper(trim((string) Parametre::valeur($cle, $defaut))) ?: $defaut;
    }

    /**
     * @param  class-string<Model>  $modele  ex. Achat::class
     */
    public function suivant(string $modele, string $prefixe, Carbon $date): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('La numérotation doit être appelée dans la transaction qui crée le document.');
        }

        $racine = sprintf('%s-%d-', $prefixe, $date->year);

        $dernier = $modele::query()
            ->where('numero', 'like', $racine.'%')
            ->orderByDesc('numero')
            ->lockForUpdate()
            ->value('numero');

        $sequence = $dernier ? (int) substr($dernier, strlen($racine)) + 1 : 1;

        return $racine.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}
