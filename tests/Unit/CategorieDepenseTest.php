<?php

namespace Tests\Unit;

use App\Enums\CategorieDepense;
use PHPUnit\Framework\TestCase;

class CategorieDepenseTest extends TestCase
{
    public function test_chaque_categorie_a_un_libelle_une_icone_existante_et_un_jeton_distinct(): void
    {
        $jetons = [];

        foreach (CategorieDepense::cases() as $categorie) {
            $this->assertNotSame('', $categorie->libelle());
            $this->assertFileExists(dirname(__DIR__, 2)."/resources/icones/{$categorie->icone()}.svg", "Icône manquante pour {$categorie->libelle()}.");
            $this->assertMatchesRegularExpression('/^graphique-[1-8]$/', $categorie->jeton());
            $this->assertStringContainsString("text-{$categorie->jeton()}", $categorie->classesIcone());
            $this->assertSame("bg-{$categorie->jeton()}", $categorie->classePastille());
            $jetons[] = $categorie->jeton();
        }

        $this->assertCount(8, array_unique($jetons), 'Une couleur par catégorie.');
    }

    public function test_les_jetons_graphique_sont_definis_en_clair_et_en_sombre(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2).'/resources/css/app.css');

        foreach (range(1, 8) as $n) {
            $this->assertSame(2, preg_match_all("/--graphique-{$n}: oklch\\(/", $css), "Jeton --graphique-{$n} attendu en clair et en sombre.");
            $this->assertStringContainsString("--color-graphique-{$n}: var(--graphique-{$n});", $css);
        }
    }
}
