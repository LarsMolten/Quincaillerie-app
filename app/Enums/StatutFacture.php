<?php

namespace App\Enums;

/**
 * Statut d'une facture.
 */
enum StatutFacture: string
{
    case Emise = 'emise';
    case Annulee = 'annulee';

    /** Texte affiché dans l'interface. */
    public function libelle(): string
    {
        return match ($this) {
            self::Emise => 'Émise',
            self::Annulee => 'Annulée',
        };
    }

    /** Jeton de couleur sémantique du badge (succes, alerte, danger, info, neutre). */
    public function couleur(): string
    {
        return match ($this) {
            self::Emise => 'succes',
            self::Annulee => 'danger',
        };
    }
}
