<?php

namespace App\Enums;

/**
 * Thème d'affichage choisi par l'utilisateur (auto = suit le système).
 */
enum PreferenceTheme: string
{
    case Clair = 'clair';
    case Sombre = 'sombre';
    case Auto = 'auto';

    /** Texte affiché dans l'interface. */
    public function libelle(): string
    {
        return match ($this) {
            self::Clair => 'Clair',
            self::Sombre => 'Sombre',
            self::Auto => 'Automatique',
        };
    }

    /** Icône Lucide associée (composant x-icone). */
    public function icone(): string
    {
        return match ($this) {
            self::Clair => 'sun',
            self::Sombre => 'moon',
            self::Auto => 'monitor',
        };
    }
}
