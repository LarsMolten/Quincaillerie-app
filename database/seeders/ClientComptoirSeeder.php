<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Seeder;

/**
 * Le « Client comptoir » (ventes sans client identifié) existe toujours.
 */
class ClientComptoirSeeder extends Seeder
{
    public function run(): void
    {
        Client::withTrashed()->firstOrCreate(
            ['nom' => Client::COMPTOIR],
            ['plafond_credit' => null, 'actif' => true],
        );
    }
}
