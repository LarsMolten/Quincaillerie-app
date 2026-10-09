<?php

namespace App\Http\Controllers;

use App\Enums\EtatStock;
use App\Enums\StatutVente;
use App\Support\Icone;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Vitrine de tous les composants du design system, en clair et en sombre.
 * Réservée à l'environnement local (et aux tests) : 404 partout ailleurs.
 */
class DesignSystemeController extends Controller
{
    public function __invoke(Request $requete): View
    {
        abort_unless(app()->environment('local', 'testing'), 404);

        // Données fictives : la page ne dépend pas du contenu de la base
        $produits = collect([
            ['reference' => 'PRD-00001', 'nom' => 'Ciment CEM II 42,5 – sac 50 kg', 'prix' => 37000, 'stock' => 186, 'etat' => EtatStock::Normal],
            ['reference' => 'PRD-00013', 'nom' => 'Tuyau PVC pression Ø 20 mm – 4 m', 'prix' => 8000, 'stock' => 12, 'etat' => EtatStock::Faible],
            ['reference' => 'PRD-00023', 'nom' => 'WC complet céramique', 'prix' => 310000, 'stock' => 0, 'etat' => EtatStock::Rupture],
            ['reference' => 'PRD-00056', 'nom' => 'Cadenas laiton 40 mm', 'prix' => 13000, 'stock' => 34, 'etat' => EtatStock::Normal],
        ]);

        $page = new LengthAwarePaginator($produits, 42, 4, max(1, (int) $requete->query('page', 1)), [
            'path' => $requete->url(),
            'query' => $requete->query(),
        ]);

        return view('design-systeme.index', [
            'produits' => $page,
            'statuts' => [...StatutVente::cases(), ...EtatStock::cases()],
            'icones' => Icone::disponibles(),
            'serie' => [12, 18, 15, 22, 19, 27, 31, 26, 34, 38, 35, 42],
        ]);
    }
}
