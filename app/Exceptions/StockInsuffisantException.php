<?php

namespace App\Exceptions;

use App\Models\Produit;
use RuntimeException;

/**
 * Levée quand une sortie ferait passer le stock d'un produit sous zéro.
 */
class StockInsuffisantException extends RuntimeException
{
    public function __construct(Produit $produit, float $disponible, float $demande)
    {
        parent::__construct(sprintf(
            'Stock insuffisant pour « %s » : %s disponible(s), %s demandé(s).',
            $produit->nom,
            self::quantite($disponible),
            self::quantite($demande),
        ));
    }

    /** Affiche une quantité sans décimales inutiles (ex. 2,5 ou 10). */
    private static function quantite(float $valeur): string
    {
        return rtrim(rtrim(number_format($valeur, 3, ',', "\u{00A0}"), '0'), ',');
    }
}
