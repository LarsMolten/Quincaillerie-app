<?php

namespace Tests;

use App\Models\Parametre;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Le cache des paramètres ne doit pas passer d'un test à l'autre
        Parametre::viderCache();
    }
}
