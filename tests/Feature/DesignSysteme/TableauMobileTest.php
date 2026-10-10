<?php

namespace Tests\Feature\DesignSysteme;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * x-tableau sous 768 px : chaque ligne devient une carte dont la cellule « principale » est toujours le titre,
 * même quand elle n'est pas la première colonne (ex. Dépenses, Mouvements, Produits avec case à cocher).
 */
class TableauMobileTest extends TestCase
{
    private function rendre(): string
    {
        return Blade::render(<<<'BLADE'
            <x-tableau :colonnes="['date' => 'Date', 'libelle' => 'Libellé', 'stock' => 'Stock']">
                <x-tableau.ligne>
                    <x-tableau.cellule libelle="Date">10 oct.</x-tableau.cellule>
                    <x-tableau.cellule libelle="Libellé" principale>Facture JIRAMA<span class="block">Réf. 4521</span></x-tableau.cellule>
                    <x-tableau.cellule libelle="Stock">8 → <strong>10</strong></x-tableau.cellule>
                </x-tableau.ligne>
            </x-tableau>
            BLADE);
    }

    public function test_la_carte_mobile_est_une_colonne_flex_titree_par_la_cellule_principale(): void
    {
        $html = $this->rendre();

        $this->assertMatchesRegularExpression('/<tr[^>]*max-md:flex max-md:flex-col/', $html, 'Carte mobile en colonne flex (ordre maîtrisé).');
        $this->assertMatchesRegularExpression('/<td data-libelle="Libellé"[^>]*max-md:order-first[^>]*max-md:flex-col/', $html, 'Cellule principale en tête de carte, contenu empilé.');
        $this->assertDoesNotMatchRegularExpression('/<td data-libelle="Date"[^>]*max-md:order-first/', $html);
    }

    public function test_le_contenu_d_une_cellule_reste_groupe_sur_mobile_sans_changer_le_tableau(): void
    {
        $html = $this->rendre();

        // « 8 → 10 » forme un seul bloc à droite du libellé (et « contents » sur ordinateur)
        $this->assertMatchesRegularExpression('/<td data-libelle="Stock"[^>]*>\s*<div class="contents max-md:block max-md:min-w-0 max-md:text-right">8 → <strong>10<\/strong><\/div>/', $html);
        $this->assertMatchesRegularExpression('/<td data-libelle="Libellé"[^>]*>\s*<div class="contents">Facture JIRAMA/', $html);
    }
}
