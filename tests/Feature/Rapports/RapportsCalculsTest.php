<?php

namespace Tests\Feature\Rapports;

use App\Enums\TypeMouvementStock;
use App\Rapports\Periode;
use App\Rapports\RapportAchats;
use App\Rapports\RapportFinances;
use App\Rapports\RapportStock;
use App\Rapports\RapportVentes;
use App\Rapports\TableauDeBord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Chiffres exacts des 4 rapports sur le jeu de données connu (voir JeuConnu), période « ce mois » (1er au 12/10/2026).
 */
class RapportsCalculsTest extends TestCase
{
    use JeuConnu, RefreshDatabase;

    private Periode $mois;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->creerJeuConnu();
        $this->mois = Periode::raccourci('mois');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_rapport_des_ventes(): void
    {
        $r = app(RapportVentes::class)->donnees($this->mois);

        $this->assertEquals(230000, $r['synthese']['brut']);
        $this->assertEquals(40000, $r['synthese']['retours']);
        $this->assertEquals(190000, $r['synthese']['net']);
        $this->assertSame(3, $r['synthese']['nombre']);
        $this->assertEquals(76666.67, $r['synthese']['panier']);
        $this->assertEquals(0, $r['synthese']['remises']);
        $this->assertEquals(132000, $r['synthese']['cout'], '102 000 + 30 000 + 30 000 − 30 000 (ciment retourné).');
        $this->assertEquals(58000, $r['synthese']['marge']);
        $this->assertEquals(30.5, $r['synthese']['taux_marge']);

        // Série par jour : 12 jours, retours datés du retour
        $this->assertCount(12, $r['serie']);
        $parJour = collect($r['serie'])->keyBy('cle');
        $this->assertEquals(140000, $parJour['2026-10-05']['net']);
        $this->assertEquals(-40000, $parJour['2026-10-08']['net']);
        $this->assertEquals(0, $parJour['2026-10-01']['net']);

        // Par vendeur
        $vendeurs = $r['parVendeur']->keyBy('nom');
        $this->assertEquals([2, 190000, 40000, 150000], [$vendeurs['Rasoa Responsable']->nombre, $vendeurs['Rasoa Responsable']->brut, $vendeurs['Rasoa Responsable']->retours, $vendeurs['Rasoa Responsable']->net]);
        $this->assertEquals([1, 40000], [$vendeurs['Hery Vendeur']->nombre, $vendeurs['Hery Vendeur']->net]);

        // Par produit, retours déduits
        $produits = $r['parProduit']->keyBy('nom');
        $this->assertSame(['Ciment 50 kg', 'Clous 70 mm'], $r['parProduit']->pluck('nom')->all());
        $this->assertEquals([3, 1, 120000, 90000, 30000], [$produits['Ciment 50 kg']->quantite, $produits['Ciment 50 kg']->retournes, $produits['Ciment 50 kg']->chiffre, $produits['Ciment 50 kg']->cout, $produits['Ciment 50 kg']->marge]);
        $this->assertEquals([7, 70000, 28000], [$produits['Clous 70 mm']->quantite, $produits['Clous 70 mm']->chiffre, $produits['Clous 70 mm']->marge]);

        // Filtres
        $vendeur = app(RapportVentes::class)->donnees($this->mois, ['vendeur' => $this->vendeur->id]);
        $this->assertEquals([40000, 0, 40000], [$vendeur['synthese']['brut'], $vendeur['synthese']['retours'], $vendeur['synthese']['net']]);
        $ciments = app(RapportVentes::class)->donnees($this->mois, ['categorie' => $this->categorieCiments->id]);
        $this->assertSame(['Ciment 50 kg'], $ciments['parProduit']->pluck('nom')->all());

        $septembre = app(RapportVentes::class)->donnees(Periode::raccourci('mois_dernier'));
        $this->assertEquals(80000, $septembre['synthese']['net']);
    }

    public function test_rapport_des_achats(): void
    {
        $r = app(RapportAchats::class)->donnees($this->mois);

        $this->assertEquals(1750000, $r['synthese']['total']);
        $this->assertSame(2, $r['synthese']['nombre']);
        $this->assertEquals(1250000, $r['synthese']['paye']);
        $this->assertEquals(500000, $r['synthese']['reste']);
        $this->assertEquals(30000, $r['synthese']['retours']);
        $this->assertEquals(1720000, $r['synthese']['net']);

        $fournisseurs = $r['parFournisseur']->keyBy('nom');
        $this->assertEquals([1, 1500000, 1000000, 500000, 0], [$fournisseurs['Holcim Madagascar']->nombre, $fournisseurs['Holcim Madagascar']->total, $fournisseurs['Holcim Madagascar']->paye, $fournisseurs['Holcim Madagascar']->reste, $fournisseurs['Holcim Madagascar']->retours]);
        $this->assertEquals([250000, 30000], [$fournisseurs['Visserie Tana']->total, $fournisseurs['Visserie Tana']->retours]);

        $produits = $r['produits']->keyBy('nom');
        $this->assertEquals([50, 1500000, 30000], [$produits['Ciment 50 kg']->quantite, $produits['Ciment 50 kg']->montant, $produits['Ciment 50 kg']->prix_moyen]);
        $this->assertEquals([40, 240000, 5], [$produits['Clous 70 mm']->quantite, $produits['Clous 70 mm']->montant, $produits['Clous 70 mm']->retournes]);
        $this->assertEquals(10000, $produits['Vis 4 × 40']->montant);

        $this->assertEquals(1500000, collect($r['serie'])->firstWhere('cle', '2026-10-02')['total']);
        $this->assertEquals(250000, app(RapportAchats::class)->donnees($this->mois, ['fournisseur' => $this->visserie->id])['synthese']['total']);
    }

    public function test_rapport_de_stock(): void
    {
        $r = app(RapportStock::class)->donnees($this->mois);

        $etat = $r['etat']->keyBy('nom');
        $this->assertEquals([55, 28, 100, 0], [$etat['Ciment 50 kg']->stock_actuel, $etat['Clous 70 mm']->stock_actuel, $etat['Vis 4 × 40']->stock_actuel, $etat['Tuyau PVC']->stock_actuel]);
        $this->assertEquals(1650000, $etat['Ciment 50 kg']->valeur);
        $this->assertEquals(1828000, $r['synthese']['valeur'], '55 × 30 000 + 28 × 6 000 + 100 × 100.');
        $this->assertSame(4, $r['synthese']['produits']);
        $this->assertEquals(1650000, $r['parCategorie']->firstWhere('categorie', 'Ciments')->valeur);

        $this->assertSame(['Ciment 50 kg'], $r['faibles']->pluck('nom')->all());
        $this->assertSame(['Tuyau PVC'], $r['ruptures']->pluck('nom')->all());
        $this->assertEqualsCanonicalizing(['Vis 4 × 40', 'Tuyau PVC'], $r['sansVente']->pluck('nom')->all(), 'Jamais vendus.');
        $this->assertEquals(10000, $r['sansVente']->firstWhere('nom', 'Vis 4 × 40')->valeur);

        // Mouvements d'octobre : 3 achats, 4 ventes, 1 retour client, 1 retour fournisseur
        $this->assertSame(9, $r['nombreMouvements']);
        $parType = $r['mouvementsParType']->keyBy(fn ($l) => $l->type->value);
        $this->assertEquals([3, 190], [$parType[TypeMouvementStock::Achat->value]->nombre, $parType[TypeMouvementStock::Achat->value]->quantite]);
        $this->assertEquals([4, 11], [$parType[TypeMouvementStock::Vente->value]->nombre, $parType[TypeMouvementStock::Vente->value]->quantite]);
        $this->assertEquals(5, $parType[TypeMouvementStock::RetourFournisseur->value]->quantite);

        $ciments = app(RapportStock::class)->donnees($this->mois, ['categorie' => $this->categorieCiments->id]);
        $this->assertSame(['Ciment 50 kg'], $ciments['etat']->pluck('nom')->all());

        // 90 jours sans vente : le ciment et les clous ont été vendus, toujours les 2 mêmes
        $this->assertCount(2, app(RapportStock::class)->donnees($this->mois, ['jours' => 90])['sansVente']);
    }

    public function test_rapport_financier(): void
    {
        $r = app(RapportFinances::class)->donnees($this->mois);
        $s = $r['synthese'];

        $this->assertEquals(190000, $s['ventes']['valeur']);
        $this->assertEquals(132000, $s['cout']);
        $this->assertEquals(120000, $s['depenses']['valeur'], 'La dépense supprimée est ignorée.');
        $this->assertEquals(-62000, $s['benefice']['valeur'], '190 000 − 132 000 − 120 000.');
        $this->assertEquals(1750000, $s['achats']['valeur']);
        $this->assertEquals(-32.6, $s['taux_marge']);

        // Période précédente (19 au 30/09) : vente du 25/09 = 80 000 ; achat du 20/09 = 300 000
        $this->assertEquals(80000, $s['ventes']['precedent']);
        $this->assertEquals(137.5, $s['ventes']['tendance']);
        $this->assertEquals(300000, $s['achats']['precedent']);

        $this->assertEquals([100000, 20000], $r['depensesParCategorie']->pluck('total')->all());

        $encaisse = $r['encaissements']->keyBy(fn ($l) => $l->mode->value);
        $this->assertEquals(140000, $encaisse['especes']->total);
        $this->assertEquals(65000, $encaisse['mobile_money']->total, '50 000 à la caisse + 15 000 de Rakoto BTP.');
        $decaisse = $r['decaissements']->keyBy(fn ($l) => $l->mode->value);
        $this->assertEquals([1000000, 250000], [$decaisse['especes']->total, $decaisse['virement']->total]);
        $this->assertEquals(['clients' => 40000.0, 'fournisseurs' => 30000.0], $r['remboursements']);

        $this->assertEquals(25000, $r['creances']['total']);
        $this->assertEquals(500000, $r['dettes']['total']);
        $this->assertSame('Holcim Madagascar', $r['dettes']['principaux']->first()->nom);
    }

    public function test_le_benefice_est_le_meme_que_celui_du_tableau_de_bord(): void
    {
        foreach (['2026-10-05', '2026-10-08', '2026-10-11'] as $jour) {
            $date = Carbon::parse($jour);
            $rapport = app(RapportFinances::class)->donnees(Periode::entre($date, $date))['synthese'];
            $tableau = app(TableauDeBord::class)->chiffresDuJour($date);

            $this->assertEquals($tableau['benefice']['valeur'], $rapport['benefice']['valeur'], "Bénéfice du {$jour}.");
            $this->assertEquals($tableau['ventes']['valeur'], $rapport['ventes']['valeur'], "Ventes du {$jour}.");
        }
    }
}
