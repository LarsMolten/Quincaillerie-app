<?php

namespace Tests\Unit;

use App\Support\Ean13;
use PHPUnit\Framework\TestCase;

class Ean13EtQuantiteTest extends TestCase
{
    public function test_chiffre_de_controle_et_validite(): void
    {
        // Code EAN-13 réel connu
        $this->assertSame('4006381333931', Ean13::completer('400638133393'));
        $this->assertTrue(Ean13::estValide('4006381333931'));
        $this->assertFalse(Ean13::estValide('4006381333932'));
        $this->assertFalse(Ean13::estValide('400638133393'));
        $this->assertFalse(Ean13::estValide('40063813339AB'));
    }

    public function test_format_quantite(): void
    {
        $this->assertSame('12', format_quantite(12));
        $this->assertSame('12', format_quantite('12.000'));
        $this->assertSame("2,5\u{00A0}kg", format_quantite(2.5, 'kg'));
        $this->assertSame("1\u{00A0}250\u{00A0}pce", format_quantite(1250, 'pce'));
        $this->assertSame('0', format_quantite(0));
        $this->assertSame('-3', format_quantite(-3));
        $this->assertSame('0,125', format_quantite(0.125));
    }
}
