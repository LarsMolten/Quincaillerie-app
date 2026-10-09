<?php

namespace App\Enums;

/**
 * Niveau de stock d'un produit, calculé à partir du stock actuel et du stock minimum.
 */
enum EtatStock: string
{
    case Normal = 'normal';
    case Faible = 'faible';
    case Rupture = 'rupture';

    /** Texte affiché dans l'interface. */
    public function libelle(): string
    {
        return match ($this) {
            self::Normal => 'En stock',
            self::Faible => 'Stock faible',
            self::Rupture => 'Rupture',
        };
    }

    /** Jeton de couleur sémantique du badge (succes, alerte, danger, info, neutre). */
    public function couleur(): string
    {
        return match ($this) {
            self::Normal => 'succes',
            self::Faible => 'alerte',
            self::Rupture => 'danger',
        };
    }
}
