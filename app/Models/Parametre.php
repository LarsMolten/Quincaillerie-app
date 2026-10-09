<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Paramètre de l'application, stocké sous forme clé / valeur.
 * Lecture : Parametre::valeur('taux_tva', '0') ou Parametre::actif('stock_negatif_autorise').
 */
class Parametre extends Model
{
    use HasFactory;

    protected $table = 'parametres';

    protected $fillable = [
        'cle',
        'valeur',
    ];

    /** @var array<string, string|null> Valeurs déjà lues pendant la requête */
    private static array $cache = [];

    protected static function booted(): void
    {
        // Toute modification invalide le cache de la requête
        static::saved(fn () => self::$cache = []);
        static::deleted(fn () => self::$cache = []);
    }

    public static function valeur(string $cle, mixed $defaut = null): mixed
    {
        if (! array_key_exists($cle, self::$cache)) {
            self::$cache[$cle] = static::query()->where('cle', $cle)->value('valeur');
        }

        return self::$cache[$cle] ?? $defaut;
    }

    /** Paramètre booléen : « 1 », « true » ou « oui » valent vrai. */
    public static function actif(string $cle): bool
    {
        return in_array(mb_strtolower(trim((string) self::valeur($cle, '0'))), ['1', 'true', 'oui'], true);
    }

    /** Vide le cache (utile entre deux tests ou après une modification en masse). */
    public static function viderCache(): void
    {
        self::$cache = [];
    }
}
