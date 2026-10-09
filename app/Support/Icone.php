<?php

namespace App\Support;

use Illuminate\View\ComponentAttributeBag;
use InvalidArgumentException;

/**
 * Lecture des icônes Lucide générées dans resources/icones (npm run icones)
 * et rendu en SVG en ligne, accessible.
 */
class Icone
{
    /** @var array<string, string> Contenu des fichiers déjà lus pendant la requête */
    private static array $cache = [];

    public static function svg(string $nom, ComponentAttributeBag $attributs, ?string $titre = null): string
    {
        $svg = self::contenu($nom);

        if ($svg === null) {
            return '';
        }

        // Icône décorative par défaut ; avec un titre, elle est annoncée aux lecteurs d'écran
        $accessibilite = $titre === null
            ? 'aria-hidden="true" focusable="false"'
            : 'role="img" aria-label="'.e($titre).'"';

        $ouverture = '<svg '.trim($attributs->toHtml().' '.$accessibilite).' ';
        $svg = preg_replace('/^<svg /', $ouverture, $svg, 1);

        if ($titre !== null) {
            $svg = preg_replace('/^(<svg[^>]*>)/', '$1<title>'.e($titre).'</title>', $svg, 1);
        }

        return $svg;
    }

    /** Liste des icônes disponibles (utilisée par la page /design-systeme). */
    public static function disponibles(): array
    {
        return collect(glob(resource_path('icones/*.svg')))
            ->map(fn (string $chemin) => basename($chemin, '.svg'))
            ->sort()
            ->values()
            ->all();
    }

    private static function contenu(string $nom): ?string
    {
        if (array_key_exists($nom, self::$cache)) {
            return self::$cache[$nom];
        }

        $chemin = resource_path("icones/{$nom}.svg");

        if (! preg_match('/^[a-z0-9-]+$/', $nom) || ! is_file($chemin)) {
            // En production, une icône manquante ne doit pas casser la page
            if (app()->environment('local', 'testing')) {
                throw new InvalidArgumentException(
                    "Icône inconnue « {$nom} » : ajoutez-la à scripts/icones.mjs puis lancez « npm run icones »."
                );
            }

            return self::$cache[$nom] = null;
        }

        return self::$cache[$nom] = trim(file_get_contents($chemin));
    }
}
