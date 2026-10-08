<?php

namespace App\Enums;

/**
 * Sens d'un mouvement de stock.
 */
enum SensMouvement: string
{
    case Entree = 'entree';
    case Sortie = 'sortie';

    /** Texte affiché dans l'interface. */
    public function libelle(): string
    {
        return match ($this) {
            self::Entree => 'Entrée',
            self::Sortie => 'Sortie',
        };
    }

    /** Jeton de couleur sémantique du badge (succes, alerte, danger, info, neutre). */
    public function couleur(): string
    {
        return match ($this) {
            self::Entree => 'succes',
            self::Sortie => 'danger',
        };
    }
}
