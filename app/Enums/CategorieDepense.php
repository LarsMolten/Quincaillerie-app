<?php

namespace App\Enums;

/**
 * Catégorie d'une dépense de fonctionnement.
 */
enum CategorieDepense: string
{
    case Salaires = 'salaires';
    case Transport = 'transport';
    case Electricite = 'electricite';
    case Eau = 'eau';
    case Loyer = 'loyer';
    case Entretien = 'entretien';
    case Fournitures = 'fournitures';
    case Autres = 'autres';

    /** Texte affiché dans l'interface. */
    public function libelle(): string
    {
        return match ($this) {
            self::Salaires => 'Salaires',
            self::Transport => 'Transport',
            self::Electricite => 'Électricité',
            self::Eau => 'Eau',
            self::Loyer => 'Loyer',
            self::Entretien => 'Entretien',
            self::Fournitures => 'Fournitures',
            self::Autres => 'Autres',
        };
    }

    /** Jeton de couleur sémantique du badge (succes, alerte, danger, info, neutre). */
    public function couleur(): string
    {
        return match ($this) {
            self::Salaires, self::Loyer => 'info',
            self::Electricite, self::Eau, self::Transport => 'alerte',
            self::Entretien, self::Fournitures, self::Autres => 'neutre',
        };
    }
}
