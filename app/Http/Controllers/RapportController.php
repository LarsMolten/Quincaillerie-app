<?php

namespace App\Http\Controllers;

use App\Exports\Rapports\RapportExcel;
use App\Models\Categorie;
use App\Models\Fournisseur;
use App\Models\Utilisateur;
use App\Rapports\Periode;
use App\Rapports\RapportAchats;
use App\Rapports\RapportFinances;
use App\Rapports\RapportStock;
use App\Rapports\RapportVentes;
use App\Support\Entreprise;
use App\Support\ReponsePdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rapports (droit rapports.voir ; Finances : finances.voir) : affichage écran avec graphiques, export PDF et Excel.
 * Les calculs sont dans app/Rapports (une classe par rapport) ; ce contrôleur ne fait que lire les filtres et afficher.
 */
class RapportController extends Controller
{
    public const RAPPORTS = [
        'ventes' => ['titre' => 'Rapport des ventes', 'droit' => 'rapports.voir', 'classe' => RapportVentes::class],
        'achats' => ['titre' => 'Rapport des achats', 'droit' => 'rapports.voir', 'classe' => RapportAchats::class],
        'stock' => ['titre' => 'Rapport de stock', 'droit' => 'rapports.voir', 'classe' => RapportStock::class],
        'finances' => ['titre' => 'Rapport financier', 'droit' => 'finances.voir', 'classe' => RapportFinances::class],
    ];

    public function afficher(Request $requete, string $rapport): View
    {
        [$periode, $filtres, $donnees] = $this->preparer($requete, $rapport);

        return view("rapports.{$rapport}", [
            'rapport' => $rapport,
            'titre' => self::RAPPORTS[$rapport]['titre'],
            'periode' => $periode,
            'filtres' => $filtres,
            'donnees' => $donnees,
            'listes' => $this->listes($rapport),
            'exports' => [
                'pdf' => route('rapports.export', [$rapport, 'pdf', ...$periode->parametres(), ...array_filter($filtres)]),
                'excel' => route('rapports.export', [$rapport, 'excel', ...$periode->parametres(), ...array_filter($filtres)]),
            ],
        ]);
    }

    public function export(Request $requete, string $rapport, string $format): Response
    {
        [$periode, $filtres, $donnees] = $this->preparer($requete, $rapport);
        $nom = "rapport-{$rapport}-{$periode->suffixeFichier()}";

        if ($format === 'excel') {
            return Excel::download(new RapportExcel($rapport, $donnees, $periode), "{$nom}.xlsx");
        }

        $pdf = Pdf::loadView("rapports.pdf.{$rapport}", [
            'titre' => self::RAPPORTS[$rapport]['titre'],
            'periode' => $periode,
            'filtres' => $this->libellesFiltres($rapport, $filtres),
            'donnees' => $donnees,
            'entreprise' => Entreprise::donnees(),
        ])->setPaper('a4', in_array($rapport, ['ventes', 'stock'], true) ? 'landscape' : 'portrait');

        return ReponsePdf::depuis($requete, $pdf, "{$nom}.pdf");
    }

    /** @return array{0: Periode, 1: array<string, ?int>, 2: array<string, mixed>} */
    private function preparer(Request $requete, string $rapport): array
    {
        abort_unless($requete->user()->can(self::RAPPORTS[$rapport]['droit']), 403, 'Vous n\'avez pas le droit d\'accéder à ce rapport.');

        $periode = Periode::depuis($requete);
        $entier = fn (string $cle) => $requete->integer($cle) ?: null;
        $filtres = match ($rapport) {
            'ventes' => ['vendeur' => $entier('vendeur'), 'categorie' => $entier('categorie')],
            'achats' => ['fournisseur' => $entier('fournisseur')],
            'stock' => ['categorie' => $entier('categorie'), 'jours' => $entier('jours')],
            default => [],
        };
        $classe = app(self::RAPPORTS[$rapport]['classe']);
        $donnees = $rapport === 'finances' ? $classe->donnees($periode) : $classe->donnees($periode, $filtres);

        return [$periode, $filtres, $donnees];
    }

    /** Listes des filtres propres à chaque rapport. */
    private function listes(string $rapport): array
    {
        return match ($rapport) {
            'ventes' => [
                'vendeurs' => Utilisateur::withTrashed()->whereHas('ventes')->orderBy('nom')->pluck('nom', 'id'),
                'categories' => Categorie::orderBy('nom')->pluck('nom', 'id'),
            ],
            'achats' => ['fournisseurs' => Fournisseur::withTrashed()->whereHas('achats')->orderBy('nom')->pluck('nom', 'id')],
            'stock' => ['categories' => Categorie::orderBy('nom')->pluck('nom', 'id')],
            default => [],
        };
    }

    /** Filtres appliqués, en clair (en-tête des PDF). */
    private function libellesFiltres(string $rapport, array $filtres): array
    {
        return array_filter([
            'Vendeur' => isset($filtres['vendeur']) ? Utilisateur::withTrashed()->find($filtres['vendeur'])?->nom : null,
            'Catégorie' => isset($filtres['categorie']) ? Categorie::withTrashed()->find($filtres['categorie'])?->nom : null,
            'Fournisseur' => isset($filtres['fournisseur']) ? Fournisseur::withTrashed()->find($filtres['fournisseur'])?->nom : null,
        ]);
    }
}
