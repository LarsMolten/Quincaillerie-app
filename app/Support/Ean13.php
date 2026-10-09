<?php

namespace App\Support;

use App\Models\Produit;

/**
 * Codes-barres EAN-13 : chiffre de contrôle, validation et codes internes du magasin.
 * Le préfixe 200 (plage 200–299 réservée à l'usage interne) évite toute collision
 * avec les codes des fabricants.
 */
class Ean13
{
    public const PREFIXE_INTERNE = '200';

    /** Code complet (13 chiffres) à partir des 12 premiers. */
    public static function completer(string $douzeChiffres): string
    {
        return $douzeChiffres.self::controle($douzeChiffres);
    }

    /** Chiffre de contrôle : pondération 1-3-1-3… sur les 12 premiers chiffres. */
    public static function controle(string $douzeChiffres): string
    {
        $somme = 0;
        foreach (str_split($douzeChiffres) as $position => $chiffre) {
            $somme += (int) $chiffre * ($position % 2 === 0 ? 1 : 3);
        }

        return (string) ((10 - $somme % 10) % 10);
    }

    public static function estValide(string $code): bool
    {
        return preg_match('/^\d{13}$/', $code) === 1
            && self::controle(substr($code, 0, 12)) === $code[12];
    }

    /** Prochain code interne libre (200 + numéro sur 9 chiffres + contrôle), produits supprimés compris. */
    public static function suivantInterne(): string
    {
        $dernier = Produit::withTrashed()
            ->where('code_barres', 'like', self::PREFIXE_INTERNE.'%')
            ->whereRaw('LENGTH(code_barres) = 13')
            ->max('code_barres');

        $numero = $dernier ? (int) substr($dernier, 3, 9) + 1 : 1;

        return self::completer(self::PREFIXE_INTERNE.str_pad((string) $numero, 9, '0', STR_PAD_LEFT));
    }
}
