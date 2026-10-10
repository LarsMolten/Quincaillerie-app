<?php

namespace Tests\Unit\Rapports;

use App\Rapports\Periode;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PeriodeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-12 15:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function periode(array $parametres): Periode
    {
        return Periode::depuis(Request::create('/rapports/ventes', 'GET', $parametres));
    }

    private function bornes(Periode $p): array
    {
        return [$p->debut->toDateString(), $p->fin->toDateString()];
    }

    public function test_raccourcis(): void
    {
        $this->assertSame(['2026-10-12', '2026-10-12'], $this->bornes($this->periode(['periode' => 'aujourdhui'])));
        $this->assertSame(['2026-10-06', '2026-10-12'], $this->bornes($this->periode(['periode' => '7j'])));
        $this->assertSame(['2026-10-01', '2026-10-12'], $this->bornes($this->periode(['periode' => 'mois'])));
        $this->assertSame(['2026-09-01', '2026-09-30'], $this->bornes($this->periode(['periode' => 'mois_dernier'])));
        $this->assertSame(['2026-10-01', '2026-10-12'], $this->bornes($this->periode([])), 'Défaut : ce mois.');
        $this->assertSame(['2026-10-01', '2026-10-12'], $this->bornes($this->periode(['periode' => 'inconnue'])));
    }

    public function test_personnalise_valide_inverse_et_plafonne(): void
    {
        $p = $this->periode(['periode' => 'personnalise', 'du' => '2026-08-15', 'au' => '2026-09-10']);
        $this->assertSame(['2026-08-15', '2026-09-10'], $this->bornes($p));
        $this->assertSame(27, $p->jours());
        $this->assertSame(['periode' => 'personnalise', 'du' => '2026-08-15', 'au' => '2026-09-10'], $p->parametres());

        $this->assertSame(['2026-08-15', '2026-09-10'], $this->bornes($this->periode(['periode' => 'personnalise', 'du' => '2026-09-10', 'au' => '2026-08-15'])), 'Dates inversées remises dans l\'ordre.');
        $this->assertSame(['2026-10-01', '2026-10-12'], $this->bornes($this->periode(['periode' => 'personnalise', 'du' => '2026-02-30', 'au' => '2026-03-10'])), 'Date invalide : ce mois.');
        $this->assertSame(['2026-10-01', '2026-10-12'], $this->bornes($this->periode(['periode' => 'personnalise', 'du' => '2026-09-01'])));
        $this->assertSame('2026-10-12', $this->periode(['periode' => 'personnalise', 'du' => '2026-10-01', 'au' => '2027-01-01'])->fin->toDateString(), 'Pas de date future.');

        $longue = $this->periode(['periode' => 'personnalise', 'du' => '2024-01-01', 'au' => '2026-10-12']);
        $this->assertSame(366, $longue->jours(), '366 jours au plus.');
    }

    public function test_precedente_granularite_et_libelles(): void
    {
        $mois = $this->periode(['periode' => 'mois']);
        $this->assertSame(['2026-09-19', '2026-09-30'], $this->bornes($mois->precedente()));
        $this->assertSame('jour', $mois->granularite());
        $this->assertSame('du 1er au 12 octobre 2026', $mois->libelle());
        $this->assertSame('12 octobre 2026', $this->periode(['periode' => 'aujourdhui'])->libelle());

        $trimestre = $this->periode(['periode' => 'personnalise', 'du' => '2026-07-01', 'au' => '2026-09-30']);
        $this->assertSame('semaine', $trimestre->granularite());
        $this->assertSame('du 1er juillet au 30 septembre 2026', $trimestre->libelle());
        $this->assertSame('2026-09-28', $trimestre->groupe(Carbon::parse('2026-09-30')));

        $annee = $this->periode(['periode' => 'personnalise', 'du' => '2025-11-01', 'au' => '2026-10-12']);
        $this->assertSame('mois', $annee->granularite());
        $this->assertSame('Novembre 2025', $annee->libelleGroupe($annee->groupe(Carbon::parse('2025-11-17'))));
        $this->assertSame('2025-11-01_2026-10-12', $annee->suffixeFichier());
    }
}
