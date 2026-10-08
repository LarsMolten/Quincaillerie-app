<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class FormatArTest extends TestCase
{
    public function test_formate_un_montant_avec_espaces_insecables(): void
    {
        $this->assertSame("35\u{00A0}000\u{00A0}Ar", format_ar(35000));
        $this->assertSame("1\u{00A0}250\u{00A0}000\u{00A0}Ar", format_ar(1250000));
    }

    public function test_arrondit_sans_decimales(): void
    {
        $this->assertSame("1\u{00A0}000\u{00A0}Ar", format_ar(999.6));
        $this->assertSame("500\u{00A0}Ar", format_ar('500.4'));
    }

    public function test_gere_zero_null_et_negatif(): void
    {
        $this->assertSame("0\u{00A0}Ar", format_ar(0));
        $this->assertSame("0\u{00A0}Ar", format_ar(null));
        $this->assertSame("-2\u{00A0}500\u{00A0}Ar", format_ar(-2500));
    }
}
