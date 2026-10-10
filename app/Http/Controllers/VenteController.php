<?php

namespace App\Http\Controllers;

use App\Enums\ModePaiement;
use App\Enums\StatutVente;
use App\Exceptions\OperationRefuseeException;
use App\Exceptions\StockInsuffisantException;
use App\Http\Requests\AnnulationRequest;
use App\Http\Requests\VenteRequest;
use App\Models\Categorie;
use App\Models\Client;
use App\Models\JournalActivite;
use App\Models\Parametre;
use App\Models\Produit;
use App\Models\Utilisateur;
use App\Models\Vente;
use App\Services\VenteService;
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
 * Ventes : écran de caisse (catalogue, ticket, paiement), liste filtrée, détail, ticket PDF et annulation.
 */
class VenteController extends Controller
{
    public const PERIODES = ['aujourdhui' => 'Aujourd\'hui', '7j' => '7 jours', '30j' => '30 jours', 'mois' => 'Ce mois', 'tout' => 'Tout'];

    public const STATUTS = ['validees' => 'Validées', 'credit' => 'Avec reste à payer', 'annulees' => 'Annulées'];

    /** Conversion pour le format du papier DomPDF (1 mm = 72 / 25,4 points). */
    private const POINTS_PAR_MM = 72 / 25.4;

    public function __construct(private readonly VenteService $ventes) {}

    public function index(Request $requete): View
    {
        $filtres = [
            'periode' => array_key_exists((string) $requete->query('periode', 'aujourdhui'), self::PERIODES) ? (string) $requete->query('periode', 'aujourdhui') : 'aujourdhui',
            'client' => $requete->integer('client') ?: null,
            'vendeur' => $requete->integer('vendeur') ?: null,
            'statut' => array_key_exists((string) $requete->query('statut'), self::STATUTS) ? (string) $requete->query('statut') : null,
            'recherche' => trim((string) $requete->query('recherche')),
        ];

        $base = Vente::query()
            ->when($this->debutPeriode($filtres['periode']), fn (Builder $q, Carbon $debut) => $q->where('date_vente', '>=', $debut))
            ->when($filtres['client'], fn (Builder $q, int $id) => $q->where('client_id', $id))
            ->when($filtres['vendeur'], fn (Builder $q, int $id) => $q->where('utilisateur_id', $id))
            ->when($filtres['recherche'] !== '', fn (Builder $q) => $q->where('numero', 'like', '%'.$filtres['recherche'].'%'));

        $ventes = (clone $base)
            ->with(['client', 'utilisateur'])
            ->when($filtres['statut'] === 'validees', fn (Builder $q) => $q->validees())
            ->when($filtres['statut'] === 'credit', fn (Builder $q) => $q->validees()->where('reste_a_payer', '>', 0))
            ->when($filtres['statut'] === 'annulees', fn (Builder $q) => $q->where('statut', StatutVente::Annulee))
            ->latest('date_vente')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        if ($requete->header('X-Fragment') === 'liste') {
            return view('ventes._liste', compact('ventes', 'filtres'));
        }

        $validees = (clone $base)->validees();
        $nombre = (clone $validees)->count();
        $chiffre = (float) (clone $validees)->sum('total');

        return view('ventes.index', [
            'ventes' => $ventes,
            'filtres' => $filtres,
            'clients' => Client::withTrashed()->whereHas('ventes')->orderBy('nom')->get(['id', 'nom']),
            'vendeurs' => Utilisateur::withTrashed()->whereHas('ventes')->orderBy('nom')->get(['id', 'nom']),
            'synthese' => [
                'chiffre' => $chiffre,
                'nombre' => $nombre,
                'panier' => $nombre > 0 ? $chiffre / $nombre : 0,
            ],
        ]);
    }

    /** Écran de caisse. */
    public function create(): View
    {
        $comptoir = Client::where('nom', Client::COMPTOIR)->first();

        return view('ventes.create', [
            'categories' => Categorie::orderBy('nom')->whereHas('produits', fn (Builder $q) => $q->where('actif', true))->get(['id', 'nom']),
            'comptoir' => $comptoir ? $this->pourCaisse($comptoir) : null,
            'remise' => [
                'autorisee' => (bool) auth()->user()->can('ventes.remise'),
                'plafond' => (float) Parametre::valeur('remise_max_pourcentage', 0),
            ],
            'stockNegatif' => Parametre::actif('stock_negatif_autorise'),
        ]);
    }

    /** Enregistre la vente et répond en JSON : la caisse affiche l'écran de réussite sans recharger. */
    public function store(VenteRequest $requete): JsonResponse
    {
        try {
            $vente = $this->ventes->creer(
                Client::findOrFail($requete->integer('client_id')),
                $requete->validated('lignes'),
                (float) $requete->validated('remise', 0),
                ModePaiement::from($requete->validated('mode_paiement')),
                (float) $requete->validated('montant_recu', 0),
                $requete->validated('notes'),
            );
        } catch (OperationRefuseeException|StockInsuffisantException $erreur) {
            return response()->json(['message' => $erreur->getMessage()], 422);
        }

        return response()->json([
            'numero' => $vente->numero,
            'total' => (float) $vente->total,
            'paye' => (float) $vente->montant_paye,
            'reste' => (float) $vente->reste_a_payer,
            'monnaie' => (float) $vente->monnaie_rendue,
            'facture' => $vente->facture?->numero,
            'url_ticket' => route('ventes.ticket', $vente),
            'url_vente' => route('ventes.show', $vente),
        ], 201);
    }

    public function show(Vente $vente): View
    {
        $vente->load(['client', 'utilisateur', 'lignes.produit.unite', 'paiements.utilisateur', 'mouvementsStock', 'facture']);

        return view('ventes.show', [
            'vente' => $vente,
            'annulation' => $vente->statut === StatutVente::Annulee
                ? JournalActivite::with('utilisateur')->where('action', 'vente.annule')->where('modele', 'vente')->where('modele_id', $vente->id)->latest('id')->first()
                : null,
        ]);
    }

    /** Ticket de caisse 80 mm en PDF (DomPDF : tables et couleurs hexadécimales). */
    public function ticket(Request $requete, Vente $vente): Response
    {
        $vente->load(['client', 'utilisateur', 'lignes.produit.unite', 'facture']);

        // Rouleau de 80 mm de large ; hauteur (en mm) adaptée au nombre de lignes
        $hauteurMm = 85 + 11 * $vente->lignes->count() + ($vente->remise > 0 ? 6 : 0) + ($vente->reste_a_payer > 0 ? 6 : 0);

        $pdf = Pdf::loadView('ventes.ticket', [
            'vente' => $vente,
            'entreprise' => [
                'nom' => Parametre::valeur('nom_entreprise', config('app.name')),
                'adresse' => Parametre::valeur('adresse'),
                'telephone' => Parametre::valeur('telephone'),
                'nif_stat' => Parametre::valeur('nif_stat'),
                'pied' => Parametre::valeur('pied_de_facture'),
            ],
        ])->setPaper([0, 0, 80 * self::POINTS_PAR_MM, $hauteurMm * self::POINTS_PAR_MM]);

        return ReponsePdf::depuis($requete, $pdf, "ticket-{$vente->numero}.pdf");
    }

    /** Catalogue de la caisse : produits actifs filtrés (recherche, catégorie), code-barres exact en premier. */
    public function catalogue(Request $requete): JsonResponse
    {
        $terme = trim((string) $requete->query('q'));
        $categorie = $requete->integer('categorie') ?: null;

        $produits = Produit::actif()
            ->with('unite')
            ->when($terme !== '', fn (Builder $q) => $q->recherche($terme)->orderByRaw('CASE WHEN code_barres = ? THEN 0 ELSE 1 END', [$terme]))
            ->when($categorie, fn (Builder $q, int $id) => $q->where('categorie_id', $id))
            ->orderBy('nom')
            ->limit(60)
            ->get();

        return response()->json($produits->map(fn (Produit $p) => [
            'produit_id' => $p->id,
            'nom' => $p->nom,
            'reference' => $p->reference,
            'code_barres' => $p->code_barres,
            'unite' => $p->unite?->abreviation,
            'prix_vente' => (float) $p->prix_vente,
            'prix_gros' => $p->prix_gros === null ? null : (float) $p->prix_gros,
            'stock' => (float) $p->stock_actuel,
            'etat' => $p->etat_stock->value,
            'etat_libelle' => $p->etat_stock->libelle(),
            'photo' => $p->image ? route('produits.photo', [$p, 'v' => $p->updated_at?->timestamp]) : null,
        ]));
    }

    /** Recherche de clients actifs pour la caisse (F4). */
    public function clients(Request $requete): JsonResponse
    {
        $terme = trim((string) $requete->query('q'));

        $clients = Client::where('actif', true)
            ->when($terme !== '', fn (Builder $q) => $q->where(fn (Builder $s) => $s->where('nom', 'like', "%{$terme}%")->orWhere('telephone', 'like', "%{$terme}%")))
            ->orderByRaw('CASE WHEN nom = ? THEN 0 ELSE 1 END', [Client::COMPTOIR])
            ->orderBy('nom')
            ->limit(10)
            ->get();

        return response()->json($clients->map(fn (Client $c) => $this->pourCaisse($c)));
    }

    public function annuler(AnnulationRequest $requete, Vente $vente): RedirectResponse
    {
        try {
            $this->ventes->annuler($vente, $requete->validated('motif'));
        } catch (OperationRefuseeException|StockInsuffisantException $erreur) {
            return back()->with('erreur', $erreur->getMessage());
        }

        return back()->with('succes', "Vente {$vente->numero} annulée : stock remis et facture annulée.");
    }

    /** Données d'un client pour la caisse (crédit disponible calculé). */
    private function pourCaisse(Client $client): array
    {
        $comptoir = $client->estComptoir();
        $creance = $comptoir ? 0.0 : $client->creance_totale;
        $plafond = $comptoir || $client->plafond_credit === null ? null : (float) $client->plafond_credit;

        return [
            'id' => $client->id,
            'nom' => $client->nom,
            'telephone' => $client->telephone,
            'comptoir' => $comptoir,
            'creance' => $creance,
            'plafond' => $plafond,
            'disponible' => $plafond === null ? 0 : max(0, $plafond - $creance),
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
