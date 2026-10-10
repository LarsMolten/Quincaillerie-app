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

    /** Icône Lucide de la catégorie. */
    public function icone(): string
    {
        return match ($this) {
            self::Salaires => 'users',
            self::Transport => 'truck',
            self::Electricite => 'zap',
            self::Eau => 'droplet',
            self::Loyer => 'building-2',
            self::Entretien => 'wrench',
            self::Fournitures => 'package',
            self::Autres => 'receipt',
        };
    }

    /** Jeton de couleur des graphiques (--graphique-N) : segment de l'anneau, icône et pastille de légende. */
    public function jeton(): string
    {
        return 'graphique-'.match ($this) {
            self::Loyer => 1,
            self::Salaires => 2,
            self::Fournitures => 3,
            self::Transport => 4,
            self::Electricite => 5,
            self::Entretien => 6,
            self::Eau => 7,
            self::Autres => 8,
        };
    }

    /**
     * Classes Tailwind de la pastille d'icône (teinte du jeton), écrites en entier
     * pour être détectées par Tailwind (jamais construites dynamiquement).
     */
    public function classesIcone(): string
    {
        return match ($this) {
            self::Loyer => 'bg-graphique-1/15 text-graphique-1',
            self::Salaires => 'bg-graphique-2/15 text-graphique-2',
            self::Fournitures => 'bg-graphique-3/15 text-graphique-3',
            self::Transport => 'bg-graphique-4/15 text-graphique-4',
            self::Electricite => 'bg-graphique-5/15 text-graphique-5',
            self::Entretien => 'bg-graphique-6/15 text-graphique-6',
            self::Eau => 'bg-graphique-7/15 text-graphique-7',
            self::Autres => 'bg-graphique-8/15 text-graphique-8',
        };
    }

    /** Classe de la pastille de légende de l'anneau. */
    public function classePastille(): string
    {
        return match ($this) {
            self::Loyer => 'bg-graphique-1',
            self::Salaires => 'bg-graphique-2',
            self::Fournitures => 'bg-graphique-3',
            self::Transport => 'bg-graphique-4',
            self::Electricite => 'bg-graphique-5',
            self::Entretien => 'bg-graphique-6',
            self::Eau => 'bg-graphique-7',
            self::Autres => 'bg-graphique-8',
        };
    }
}
