<?php

namespace App\Support;

use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;

/**
 * Outils communs aux composants de formulaire (identifiant, erreur, ancienne valeur).
 */
class Champ
{
    /** « lignes[0][prix] » devient « lignes.0.prix » (clé des erreurs et de old()). */
    public static function cle(string $nom): string
    {
        return trim(str_replace(['[]', '[', ']'], ['', '.', ''], $nom), '.');
    }

    /** Identifiant HTML stable déduit du nom du champ. */
    public static function id(string $nom): string
    {
        return 'champ-'.Str::slug(str_replace('.', '-', self::cle($nom)));
    }

    public static function erreur(?ViewErrorBag $erreurs, string $nom): ?string
    {
        return $erreurs?->first(self::cle($nom)) ?: null;
    }

    /** Valeurs pour aria-describedby (aide puis erreur), ou null. */
    public static function decritPar(string $id, ?string $aide, ?string $erreur): ?string
    {
        $ids = array_filter([$aide ? "{$id}-aide" : null, $erreur ? "{$id}-erreur" : null]);

        return $ids ? implode(' ', $ids) : null;
    }
}
