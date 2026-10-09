<?php

namespace App\Support;

/**
 * Initiales et couleur d'avatar déduites d'un nom (utilisateurs, clients, fournisseurs).
 */
class Initiales
{
    /** Couleurs possibles : jetons sémantiques (le danger est exclu, il suggérerait un problème). */
    public const COULEURS = ['info', 'succes', 'alerte', 'neutre', 'primaire'];

    /** « Rakotonirina Andry » → « RA », « Administrateur » → « A ». */
    public static function de(string $nom): string
    {
        return collect(preg_split('/\s+/', trim($nom)))
            ->filter(fn (string $mot) => preg_match('/^\pL/u', $mot))
            ->take(2)
            ->map(fn (string $mot) => mb_strtoupper(mb_substr($mot, 0, 1)))
            ->implode('');
    }

    /** Couleur stable : le même nom donne toujours la même couleur. */
    public static function couleur(string $nom): string
    {
        return self::COULEURS[crc32(mb_strtolower(trim($nom))) % count(self::COULEURS)];
    }
}
