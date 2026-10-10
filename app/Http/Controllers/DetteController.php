<?php

namespace App\Http\Controllers;

use App\Enums\StatutAchat;
use App\Models\Achat;
use App\Models\Fournisseur;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Dettes fournisseurs (droits paiements.gerer et achats.voir) : ce que nous devons à chaque fournisseur,
 * avec l'ancienneté du plus vieil achat impayé, le détail par achat et le règlement.
 */
class DetteController extends Controller
{
    public function index(Request $requete): View
    {
        $recherche = trim((string) $requete->query('recherche'));
        $anciennete = array_key_exists((string) $requete->query('anciennete'), CreanceController::TRANCHES) ? $requete->query('anciennete') : null;

        $impayes = fn (Builder $q) => $q->where('statut', StatutAchat::Valide)->where('reste_a_payer', '>', 0);

        $creanciers = Fournisseur::query()
            ->whereHas('achats', $impayes)
            ->withSum(['achats as du' => $impayes], 'reste_a_payer')
            ->withCount(['achats as documents' => $impayes])
            ->addSelect(['plus_ancienne' => Achat::selectRaw('MIN(date_achat)')
                ->whereColumn('fournisseur_id', 'fournisseurs.id')
                ->where('statut', StatutAchat::Valide)
                ->where('reste_a_payer', '>', 0)])
            ->when($recherche !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('nom', 'like', "%{$recherche}%")
                ->orWhere('telephone', 'like', "%{$recherche}%")))
            ->orderByDesc('du')
            ->orderBy('nom')
            ->get();

        return CreanceController::liste($requete, $creanciers, $recherche, $anciennete, 'dettes');
    }

    /** Détail (fragment) : achats impayés du fournisseur, du plus ancien au plus récent. */
    public function show(Fournisseur $fournisseur): View
    {
        $achats = $fournisseur->achats()
            ->where('statut', StatutAchat::Valide)
            ->where('reste_a_payer', '>', 0)
            ->oldest('date_achat')
            ->oldest('id')
            ->get();

        return view('dettes._detail', compact('fournisseur', 'achats'));
    }
}
