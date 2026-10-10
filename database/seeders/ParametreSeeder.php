<?php

namespace Database\Seeders;

use App\Models\Parametre;
use Illuminate\Database\Seeder;

/**
 * Paramètres par défaut. Une valeur déjà modifiée par l'utilisateur n'est jamais écrasée.
 */
class ParametreSeeder extends Seeder
{
    public const PARAMETRES = [
        'nom_entreprise' => 'Quincaillerie',
        'adresse' => 'Antananarivo, Madagascar',
        'telephone' => '',
        'email' => '',
        'nif_stat' => '',
        'taux_tva' => '0',
        'prefixe_facture' => 'FAC',
        // Logo de l'entreprise : chemin d'une image PNG ou JPEG sur le disque privé (écran Paramètres)
        'logo' => '',
        'pied_de_facture' => 'Merci de votre confiance. Les marchandises vendues ne sont ni reprises ni échangées sans facture.',
        'couleur_accent' => 'orange',
        // Règles métier §5 (stock négatif interdit, remise plafonnée)
        'stock_negatif_autorise' => '0',
        'remise_max_pourcentage' => '10',
    ];

    public function run(): void
    {
        foreach (self::PARAMETRES as $cle => $valeur) {
            Parametre::firstOrCreate(['cle' => $cle], ['valeur' => $valeur]);
        }
    }
}
