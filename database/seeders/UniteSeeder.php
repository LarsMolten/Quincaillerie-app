<?php

namespace Database\Seeders;

use App\Models\Unite;
use Illuminate\Database\Seeder;

class UniteSeeder extends Seeder
{
    public const UNITES = [
        'pièce' => 'pce',
        'kilogramme' => 'kg',
        'mètre' => 'm',
        'mètre carré' => 'm²',
        'litre' => 'L',
        'sac' => 'sac',
        'pot' => 'pot',
        'carton' => 'ctn',
        'boîte' => 'bte',
        'paquet' => 'pqt',
        'rouleau' => 'rlx',
        'barre' => 'barre',
        'feuille' => 'flle',
    ];

    public function run(): void
    {
        foreach (self::UNITES as $nom => $abreviation) {
            Unite::firstOrCreate(['nom' => $nom], ['abreviation' => $abreviation]);
        }
    }
}
