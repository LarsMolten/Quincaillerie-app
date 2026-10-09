<?php

namespace Tests\Unit\DesignSysteme;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Vérifie les contrastes WCAG 2.2 AA des jetons de resources/css/app.css, en clair et en sombre :
 * 4,5:1 pour le texte, 3:1 pour les bordures de contrôles et l'anneau de focus.
 * Conversion OKLCH → sRGB (Björn Ottosson) puis luminance relative WCAG.
 */
class ContrastesTest extends TestCase
{
    private const TEXTE = 4.5;

    private const COMPOSANT = 3.0;

    /** @var array<string, array<string, array{float, float, float}>> */
    private static array $jetons = [];

    public static function modes(): array
    {
        return ['clair' => ['clair'], 'sombre' => ['sombre']];
    }

    #[DataProvider('modes')]
    public function test_textes_sur_les_surfaces(string $mode): void
    {
        foreach (['fond', 'surface', 'surface-elevee'] as $fond) {
            foreach (['texte', 'texte-doux', 'lien'] as $texte) {
                $this->assertContraste($mode, $texte, $fond, self::TEXTE);
            }
        }
    }

    #[DataProvider('modes')]
    public function test_textes_sur_les_couleurs_d_accent(string $mode): void
    {
        $this->assertContraste($mode, 'primaire-texte', 'primaire', self::TEXTE);
        $this->assertContraste($mode, 'primaire-texte', 'primaire-survol', self::TEXTE);
        $this->assertContraste($mode, 'sur-danger', 'danger', self::TEXTE);
        $this->assertContraste($mode, 'sur-danger', 'danger-survol', self::TEXTE);
        $this->assertContraste($mode, 'lien', 'primaire-doux', self::TEXTE);
        $this->assertContraste($mode, 'texte-doux', 'neutre-doux', self::TEXTE);
    }

    #[DataProvider('modes')]
    public function test_badges_et_messages(string $mode): void
    {
        foreach (['succes', 'alerte', 'danger', 'info', 'neutre'] as $couleur) {
            $this->assertContraste($mode, "{$couleur}-texte", "{$couleur}-doux", self::TEXTE);
        }

        // Messages d'erreur des champs, écrits sur la surface
        $this->assertContraste($mode, 'danger-texte', 'surface', self::TEXTE);
        $this->assertContraste($mode, 'succes-texte', 'surface', self::TEXTE);
    }

    #[DataProvider('modes')]
    public function test_bordures_de_controles_et_anneau_de_focus(string $mode): void
    {
        foreach (['fond', 'surface', 'surface-elevee'] as $fond) {
            $this->assertContraste($mode, 'bordure-forte', $fond, self::COMPOSANT);
            $this->assertContraste($mode, 'anneau', $fond, self::COMPOSANT);
        }

        // Icônes des toasts et pastilles des badges
        foreach (['succes', 'danger', 'info'] as $couleur) {
            $this->assertContraste($mode, $couleur, 'surface', self::COMPOSANT);
        }
    }

    public function test_les_deux_modes_definissent_les_memes_jetons(): void
    {
        $this->assertEqualsCanonicalizing(array_keys(self::jetons('clair')), array_keys(self::jetons('sombre')));
        $this->assertGreaterThanOrEqual(30, count(self::jetons('clair')));
    }

    private function assertContraste(string $mode, string $avant, string $arriere, float $minimum): void
    {
        $jetons = self::jetons($mode);
        $this->assertArrayHasKey($avant, $jetons, "Jeton --{$avant} absent en mode {$mode}.");
        $this->assertArrayHasKey($arriere, $jetons, "Jeton --{$arriere} absent en mode {$mode}.");

        $ratio = self::contraste($jetons[$avant], $jetons[$arriere]);

        $this->assertGreaterThanOrEqual(
            $minimum,
            round($ratio, 2),
            sprintf('Mode %s : --%s sur --%s = %.2f:1 (minimum %.1f:1).', $mode, $avant, $arriere, $ratio, $minimum),
        );
    }

    /** @return array<string, array{float, float, float}> */
    private static function jetons(string $mode): array
    {
        if (! self::$jetons) {
            $css = file_get_contents(dirname(__DIR__, 3).'/resources/css/app.css');
            preg_match('/:root,\s*\.clair\s*\{(.*?)\n    \}/s', $css, $clair);
            preg_match('/\n    \.dark\s*\{(.*?)\n    \}/s', $css, $sombre);

            foreach (['clair' => $clair[1] ?? '', 'sombre' => $sombre[1] ?? ''] as $nom => $bloc) {
                preg_match_all('/--([a-z-]+):\s*oklch\(([\d.]+)\s+([\d.]+)\s+([\d.]+)\)/', $bloc, $correspondances, PREG_SET_ORDER);
                foreach ($correspondances as [, $jeton, $l, $c, $h]) {
                    self::$jetons[$nom][$jeton] = [(float) $l, (float) $c, (float) $h];
                }
            }
        }

        return self::$jetons[$mode] ?? [];
    }

    /** @param array{float, float, float} $a @param array{float, float, float} $b */
    private static function contraste(array $a, array $b): float
    {
        [$clair, $fonce] = [max(self::luminance($a), self::luminance($b)), min(self::luminance($a), self::luminance($b))];

        return ($clair + 0.05) / ($fonce + 0.05);
    }

    /** Luminance relative WCAG d'une couleur OKLCH (ramenée dans le gamut sRGB). */
    private static function luminance(array $oklch): float
    {
        [$l, $c, $h] = $oklch;
        $a = $c * cos(deg2rad($h));
        $b = $c * sin(deg2rad($h));

        $lp = ($l + 0.3963377774 * $a + 0.2158037573 * $b) ** 3;
        $mp = ($l - 0.1055613458 * $a - 0.0638541728 * $b) ** 3;
        $sp = ($l - 0.0894841775 * $a - 1.2914855480 * $b) ** 3;

        $rouge = 4.0767416621 * $lp - 3.3077115913 * $mp + 0.2309699292 * $sp;
        $vert = -1.2684380046 * $lp + 2.6097574011 * $mp - 0.3413193965 * $sp;
        $bleu = -0.0041960863 * $lp - 0.7034186147 * $mp + 1.7076147010 * $sp;

        [$rouge, $vert, $bleu] = array_map(fn (float $v) => max(0.0, min(1.0, $v)), [$rouge, $vert, $bleu]);

        return 0.2126 * $rouge + 0.7152 * $vert + 0.0722 * $bleu;
    }
}
