<?php

namespace App\Http\Controllers;

use App\Models\JournalActivite;
use App\Models\Utilisateur;
use App\Rapports\Periode;
use App\Support\LibelleJournal;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Administration > Journal d'activité (droit journal.voir) : chronologie filtrable, en lecture seule
 * (aucune route de modification ; le modèle refuse toute modification ou suppression).
 */
class JournalController extends Controller
{
    public function index(Request $requete): View
    {
        $periode = Periode::depuis($requete);
        $filtres = [
            'utilisateur' => Utilisateur::withTrashed()->whereKey($requete->integer('utilisateur'))->value('id'),
            'module' => array_key_exists((string) $requete->query('module'), LibelleJournal::MODULES) ? (string) $requete->query('module') : null,
            'type' => array_key_exists((string) $requete->query('type'), LibelleJournal::TYPES) ? (string) $requete->query('type') : null,
            'recherche' => trim((string) $requete->query('recherche')),
        ];

        $entrees = JournalActivite::query()
            ->with('utilisateur.role')
            ->whereBetween('created_at', [$periode->debut->copy()->startOfDay(), $periode->fin->copy()->endOfDay()])
            ->when($filtres['utilisateur'], fn (Builder $q, int $id) => $q->where('utilisateur_id', $id))
            ->when($filtres['module'], fn (Builder $q, string $module) => $q->where('action', 'like', $module.'.%'))
            ->when($filtres['type'], fn (Builder $q, string $type) => LibelleJournal::filtrerType($q, $type))
            ->when($filtres['recherche'] !== '', fn (Builder $q) => $q->where(fn (Builder $s) => $s
                ->where('action', 'like', '%'.$filtres['recherche'].'%')
                ->orWhere('details', 'like', '%'.$filtres['recherche'].'%')
                ->orWhere('adresse_ip', 'like', $filtres['recherche'].'%')))
            ->latest('created_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $donnees = compact('entrees', 'periode', 'filtres');

        if ($requete->header('X-Fragment') === 'liste') {
            return view('journal._liste', $donnees);
        }

        return view('journal.index', [
            ...$donnees,
            'utilisateurs' => Utilisateur::withTrashed()->orderBy('nom')->pluck('nom', 'id'),
        ]);
    }
}
