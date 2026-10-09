<?php

namespace Tests\Unit;

use App\Support\Initiales;
use PHPUnit\Framework\TestCase;

class InitialesTest extends TestCase
{
    public function test_initiales(): void
    {
        $this->assertSame('RM', Initiales::de('Ravinala Matériaux'));
        $this->assertSame('RA', Initiales::de('  rakotonirina   andry '));
        $this->assertSame('A', Initiales::de('Administrateur'));
        $this->assertSame('ÉT', Initiales::de('École Tsara Fanilo'));
        $this->assertSame('CD', Initiales::de("Couleurs de l'Île"));
        $this->assertSame('', Initiales::de('  '));
    }

    public function test_couleur_stable_et_parmi_les_jetons(): void
    {
        $this->assertSame(Initiales::couleur('Rakoto Hery'), Initiales::couleur('rakoto hery'));
        $this->assertContains(Initiales::couleur('Rakoto Hery'), Initiales::COULEURS);
        $this->assertNotContains('danger', Initiales::COULEURS);

        // Plusieurs couleurs sont effectivement utilisées
        $couleurs = collect(['Rakoto', 'Rasoa', 'Rabe', 'Andry', 'Fara', 'Lova', 'Tiana', 'Mamy', 'Hery', 'Soa'])->map(fn ($n) => Initiales::couleur($n))->unique();
        $this->assertGreaterThan(2, $couleurs->count());
    }
}
