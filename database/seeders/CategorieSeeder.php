<?php

namespace Database\Seeders;

use App\Models\Categorie;
use Illuminate\Database\Seeder;

class CategorieSeeder extends Seeder
{
    public const CATEGORIES = [
        'Matériaux de construction' => 'Ciment, chaux, fer à béton, agglos, sable.',
        'Toiture et tôlerie' => 'Tôles, faîtières, pointes de toiture, gouttières.',
        'Plomberie' => 'Tuyaux, raccords, robinets, vannes.',
        'Sanitaire' => 'WC, lavabos, douches, accessoires de salle de bain.',
        'Électricité' => 'Câbles, interrupteurs, prises, disjoncteurs, éclairage.',
        'Peinture et revêtements' => 'Peintures, vernis, enduits, pinceaux, rouleaux.',
        'Outillage' => 'Outils à main et électroportatifs.',
        'Visserie et clouterie' => 'Vis, clous, boulons, chevilles, rondelles.',
        'Serrurerie' => 'Cadenas, serrures, charnières, verrous.',
        'Jardinage et agriculture' => 'Pelles, bêches, arrosoirs, brouettes, tuyaux d\'arrosage.',
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $nom => $description) {
            Categorie::firstOrCreate(['nom' => $nom], ['description' => $description, 'actif' => true]);
        }
    }
}
