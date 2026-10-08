<?php

namespace App\Enums;

/**
 * Origine d'un retour : client (vente) ou fournisseur (achat).
 */
enum TypeRetour: string
{
    case Client = 'client';
    case Fournisseur = 'fournisseur';

    /** Texte affiché dans l'interface. */
    public function libelle(): string
    {
        return match ($this) {
            self::Client => 'Retour client',
            self::Fournisseur => 'Retour fournisseur',
        };
    }

    /** Jeton de couleur sémantique du badge (succes, alerte, danger, info, neutre). */
    public function couleur(): string
    {
        return match ($this) {
            self::Client => 'info',
            self::Fournisseur => 'neutre',
        };
    }
}
