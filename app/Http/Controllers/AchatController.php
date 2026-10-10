<?php

namespace App\Http\Controllers;

use App\Enums\ModePaiement;
use App\Enums\StatutAchat;
use App\Exceptions\OperationRefuseeException;
use App\Exceptions\StockInsuffisantException;
use App\Http\Requests\AchatRequest;
use App\Http\Requests\AnnulationRequest;
use App\Http\Requests\PaiementRequest;
use App\Models\Achat;
use App\Models\Fournisseur;
use App\Models\JournalActivite;
use App\Models\Parametre;
use App\Models\Produit;
use App\Services\AchatService;
use App\Services\PaiementService;
use App\Support\ReponsePdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Achats fournisseurs : saisie, liste filtrée, détail, bon PDF, annulation et paiements ultérieurs.
 */
class AchatController extends Controller
{
    public const PERIODES = ['aujourdhui' => 'Aujourd\'hui', '7j' => '7 jours', '30j' => '30 jours', 'mois' => 'Ce mois', '' => 'Tout'];

    public function __construct(
        private readonly AchatService $achats,
        private readonly PaiementService $paiements,
    ) {}

    public function index(Request $requete): View
    {
        $filtres = [
            'periode' => array_key_exists((string) $requete->query('periode'), self::PERIODES) ? (string) $requete->query('periode') : '',
            'fournisseur' => $requete->integer('fournisseur') ?: null,
            'paiement' => in_array($requete->query('paiement'), ['paye', 'partiel', 'credit', 'annules'], true) ? $requete->query('paiement') : null,
            'recherche' => trim((string) $requete->query('recherche')),
        ];

        $base = Achat::query()
            ->when($this->debutPeriode($filtres['periode']), fn (Builder $q, Carbon $debut) => $q->where('date_achat', '>=', $debut))
            ->when($filtres['fournisseur'], fn (Builder $q, int $id) => $q->where('fournisseur_id', $id))
            ->when($filtres['recherche'] !== '', fn (Builder $q) => $q->where('numero', 'like', '%'.$filtres['recherche'].'%'));

        $achats = (clone $base)
            ->with('fournisseur')
            ->when($filtres['paiement'] === 'annules', fn (Builder $q) => $q->where('statut', StatutAchat::Annule))
            ->when(in_array($filtres['paiement'], ['paye', 'partiel', 'credit'], true), fn (Builder $q) => $q->valides())
            ->when($filtres['paiement'] === 'paye', fn (Builder $q) => $q->where('reste_a_payer', '<=', 0))
            ->when($filtres['paiement'] === 'partiel', fn (Builder $q) => $q->where('reste_a_payer', '>', 0)->where('montant_paye', '>', 0))
            ->when($filtres['paiement'] === 'credit', fn (Builder $q) => $q->where('reste_a_payer', '>', 0)->where('montant_paye', '<=', 0))
            ->latest('date_achat')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        if ($requete->header('X-Fragment') === 'liste') {
            return view('achats._liste', compact('achats', 'filtres'));
        }

        $valides = (clone $base)->valides();

        return view('achats.index', [
            'achats' => $achats,
            'filtres' => $filtres,
            'fournisseurs' => Fournisseur::withTrashed()->orderBy('nom')->get(['id', 'nom']),
            'synthese' => [
                'total' => (float) (clone $valides)->sum('total'),
                'nombre' => (clone $valides)->count(),
                'dette' => (float) Achat::valides()->sum('reste_a_payer'),
            ],
        ]);
    }

    public function create(Request $requete): View
    {
        // Lignes à reconstruire après une erreur de validation
        $anciennes = collect(old('lignes', []));
        $produits = Produit::with('unite')->whereIn('id', $anciennes->pluck('produit_id')->filter())->get()->keyBy('id');
        $lignes = $anciennes
            ->filter(fn ($ligne) => isset($produits[$ligne['produit_id'] ?? 0]))
            ->map(fn ($ligne) => [
                ...$this->pourSaisie($produits[$ligne['produit_id']]),
                'quantite' => (string) ($ligne['quantite'] ?? '1'),
                'prix_achat' => (string) ($ligne['prix_achat'] ?? ''),
            ])
            ->values();

        return view('achats.create', [
            'fournisseurs' => Fournisseur::where('actif', true)->orderBy('nom')->get(['id', 'nom']),
            'fournisseurChoisi' => old('fournisseur_id', $requete->integer('fournisseur') ?: ''),
            'lignes' => $lignes,
            'modes' => ModePaiement::encaissements(),
        ]);
    }

    public function store(AchatRequest $requete): RedirectResponse
    {
        try {
            $achat = $this->achats->creer(
                Fournisseur::findOrFail($requete->integer('fournisseur_id')),
                $requete->validated('lignes'),
                (float) $requete->validated('montant_paye', 0),
                $requete->filled('mode_paiement') ? ModePaiement::from($requete->validated('mode_paiement')) : null,
                $requete->validated('notes'),
                Carbon::parse($requete->validated('date_achat')),
                $requete->boolean('maj_prix'),
            );
        } catch (OperationRefuseeException|StockInsuffisantException $erreur) {
            return back()->withInput()->with('erreur', $erreur->getMessage());
        }

        return to_route('achats.show', $achat)
            ->with('succes', "Achat {$achat->numero} enregistré : stock mis à jour.")
            ->with('toast_lien', ['libelle' => 'Voir le bon d\'achat', 'url' => route('achats.bon', $achat), 'nouvelOnglet' => true, 'pdf' => true]);
    }

    public function show(Achat $achat): View
    {
        $achat->load(['fournisseur', 'utilisateur', 'lignes.produit.unite', 'paiements.utilisateur', 'mouvementsStock']);

        return view('achats.show', [
            'achat' => $achat,
            'annulation' => $achat->statut === StatutAchat::Annule
                ? JournalActivite::with('utilisateur')->where('action', 'achat.annule')->where('modele', 'achat')->where('modele_id', $achat->id)->latest('id')->first()
                : null,
            'modes' => ModePaiement::encaissements(),
        ]);
    }

    /** Bon d'achat en PDF (DomPDF : tables et couleurs hexadécimales). */
    public function bon(Request $requete, Achat $achat): Response
    {
        $achat->load(['fournisseur', 'utilisateur', 'lignes.produit.unite', 'paiements']);

        $pdf = Pdf::loadView('achats.bon', [
            'achat' => $achat,
            'entreprise' => [
                'nom' => Parametre::valeur('nom_entreprise', config('app.name')),
                'adresse' => Parametre::valeur('adresse'),
                'telephone' => Parametre::valeur('telephone'),
                'email' => Parametre::valeur('email'),
                'nif_stat' => Parametre::valeur('nif_stat'),
            ],
        ])->setPaper('a4');

        return ReponsePdf::depuis($requete, $pdf, "bon-achat-{$achat->numero}.pdf");
    }

    /** Recherche de produits actifs pour la saisie (nom, référence, code-barres exact en premier). */
    public function produits(Request $requete): JsonResponse
    {
        $terme = trim((string) $requete->query('q'));

        if ($terme === '') {
            return response()->json([]);
        }

        $produits = Produit::actif()
            ->with('unite')
            ->recherche($terme)
            ->orderByRaw('CASE WHEN code_barres = ? THEN 0 ELSE 1 END', [$terme])
            ->orderBy('nom')
            ->limit(8)
            ->get();

        return response()->json($produits->map(fn (Produit $p) => $this->pourSaisie($p)));
    }

    public function paiement(PaiementRequest $requete, Achat $achat): RedirectResponse
    {
        try {
            $this->paiements->enregistrer(
                $achat,
                (float) $requete->validated('montant'),
                ModePaiement::from($requete->validated('mode')),
                $requete->validated('reference'),
                Carbon::parse($requete->validated('date_paiement'))->setTimeFrom(now()),
            );
        } catch (OperationRefuseeException $erreur) {
            return back()->withInput()->with('erreur', $erreur->getMessage());
        }

        $achat->refresh();

        return back()->with('succes', $achat->reste_a_payer <= 0
            ? "Paiement enregistré : l'achat {$achat->numero} est soldé."
            : 'Paiement enregistré. Reste à payer : '.format_ar($achat->reste_a_payer).'.');
    }

    public function annuler(AnnulationRequest $requete, Achat $achat): RedirectResponse
    {
        try {
            $this->achats->annuler($achat, $requete->validated('motif'));
        } catch (OperationRefuseeException|StockInsuffisantException $erreur) {
            return back()->with('erreur', $erreur->getMessage());
        }

        return back()->with('succes', "Achat {$achat->numero} annulé : le stock a été corrigé.");
    }

    /** Données d'un produit pour l'écran de saisie. */
    private function pourSaisie(Produit $produit): array
    {
        return [
            'produit_id' => $produit->id,
            'nom' => $produit->nom,
            'reference' => $produit->reference,
            'code_barres' => $produit->code_barres,
            'unite' => $produit->unite?->abreviation,
            'stock' => format_quantite($produit->stock_actuel, $produit->unite?->abreviation),
            'prix_achat' => (string) (float) $produit->prix_achat,
            'prix_actuel' => (float) $produit->prix_achat,
        ];
    }

    private function debutPeriode(string $periode): ?Carbon
    {
        return match ($periode) {
            'aujourdhui' => today(),
            '7j' => today()->subDays(6),
            '30j' => today()->subDays(29),
            'mois' => today()->startOfMonth(),
            default => null,
        };
    }
}
