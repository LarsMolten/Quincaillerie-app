<?php

namespace App\Enums;

/**
 * Statut d'une vente.
 */
enum StatutVente: string
{
    case Validee = 'validee';
    case Annulee = 'annulee';

    /** Texte affiché dans l'interface. */
    public function libelle(): string
    {
        return match ($this) {
            self::Validee => 'Validée',
            self::Annulee => 'Annulée',
        };
    }

    /** Jeton de couleur sémantique du badge (succes, alerte, danger, info, neutre). */
    public function couleur(): string
    {
        return match ($this) {
            self::Validee => 'succes',
            self::Annulee => 'danger',
        };
    }
}
