<?php

namespace App\Enums;

/**
 * Statut d'un retour.
 */
enum StatutRetour: string
{
    case Valide = 'valide';
    case Annule = 'annule';

    /** Texte affiché dans l'interface. */
    public function libelle(): string
    {
        return match ($this) {
            self::Valide => 'Validé',
            self::Annule => 'Annulé',
        };
    }

    /** Jeton de couleur sémantique du badge (succes, alerte, danger, info, neutre). */
    public function couleur(): string
    {
        return match ($this) {
            self::Valide => 'succes',
            self::Annule => 'danger',
        };
    }
}
