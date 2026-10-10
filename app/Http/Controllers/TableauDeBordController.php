<?php

namespace App\Http\Controllers;

use App\Rapports\TableauDeBord;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tableau de bord (page d'accueil) : statistiques du jour rendues avec la page, cartes lourdes
 * chargées ensuite (fragments). Chaque carte n'est visible et chargeable qu'avec son droit.
 */
class TableauDeBordController extends Controller
{
    /** Droit requis par carte chargée à la demande. */
    public const CARTES = [
        'ventes' => 'ventes.voir',
        'top' => 'ventes.voir',
        'dernieres' => 'ventes.voir',
        'stock' => 'produits.voir',
        'creances' => 'paiements.gerer',
    ];

    public function __construct(private readonly TableauDeBord $tableau) {}

    public function index(Request $requete): View
    {
        $utilisateur = $requete->user();
        $peut = fn (string $droit) => (bool) $utilisateur->can($droit);

        return view('tableau-de-bord.index', [
            'jour' => $peut('ventes.voir') || $peut('finances.voir') ? $this->tableau->chiffresDuJour() : null,
            'compteurs' => $this->tableau->compteurs(),
            'voir' => [
                'ventes' => $peut('ventes.voir'),
                'finances' => $peut('finances.voir'),
                'produits' => $peut('produits.voir'),
                'clients' => $peut('clients.gerer'),
                'fournisseurs' => $peut('fournisseurs.gerer'),
                'creances' => $peut('paiements.gerer'),
            ],
        ]);
    }

    /** Carte chargée après la page : fragment HTML, ou série JSON pour le graphique des ventes. */
    public function carte(Request $requete, string $carte): View|JsonResponse
    {
        abort_unless($requete->user()->can(self::CARTES[$carte]), 403, 'Vous n\'avez pas le droit d\'accéder à cette carte.');

        return match ($carte) {
            'ventes' => response()->json($this->tableau->serieVentes($requete->integer('jours', 30))),
            'stock' => view('tableau-de-bord._stock', ['produits' => $this->tableau->stockFaible()]),
            'top' => view('tableau-de-bord._top', ['produits' => $this->tableau->topProduits()]),
            'creances' => view('tableau-de-bord._creances', ['creances' => $this->tableau->creances()]),
            'dernieres' => view('tableau-de-bord._dernieres', ['ventes' => $this->tableau->dernieresVentes()]),
        };
    }
}
