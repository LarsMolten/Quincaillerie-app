<?php

namespace App\Enums;

/**
 * Statut d'un inventaire.
 */
enum StatutInventaire: string
{
    case EnCours = 'en_cours';
    case Valide = 'valide';

    /** Texte affiché dans l'interface. */
    public function libelle(): string
    {
        return match ($this) {
            self::EnCours => 'En cours',
            self::Valide => 'Validé',
        };
    }

    /** Jeton de couleur sémantique du badge (succes, alerte, danger, info, neutre). */
    public function couleur(): string
    {
        return match ($this) {
            self::EnCours => 'alerte',
            self::Valide => 'succes',
        };
    }
}
