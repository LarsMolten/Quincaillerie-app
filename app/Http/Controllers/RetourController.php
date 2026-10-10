<?php

namespace App\Http\Controllers;

use App\Enums\ModePaiement;
use App\Enums\StatutAchat;
use App\Enums\StatutRetour;
use App\Enums\StatutVente;
use App\Enums\TypeRetour;
use App\Exceptions\OperationRefuseeException;
use App\Exceptions\StockInsuffisantException;
use App\Http\Requests\AnnulationRequest;
use App\Http\Requests\RetourRequest;
use App\Models\Achat;
use App\Models\JournalActivite;
use App\Models\Retour;
use App\Models\Vente;
use App\Services\RetourService;
use App\Support\ReponsePdf;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Retours clients et fournisseurs (droit retours.gerer) : listes, assistant en 3 étapes,
 * détail, bon PDF et annulation avec motif.
 */
class RetourController extends Controller
{
    public const PERIODES = ['aujourdhui' => 'Aujourd\'hui', '7j' => '7 jours', '30j' => '30 jours', 'mois' => 'Ce mois', 'tout' => 'Tout'];

    public function __construct(private readonly RetourService $retours) {}

    public function clients(Request $requete): View
    {
        return $this->liste($requete, TypeRetour::Client);
    }

    public function fournisseurs(Request $requete): View
    {
        return $this->liste($requete, TypeRetour::Fournisseur);
    }

    /** Assistant : 1. document d'origine, 2. produits, 3. confirmation. */
    public function create(Request $requete): View
    {
        $type = $requete->query('type') === 'fournisseur' || $requete->filled('achat') ? TypeRetour::Fournisseur : TypeRetour::Client;
        $document = match (true) {
            $requete->filled('vente') => Vente::find($requete->integer('vente')),
            $requete->filled('achat') => Achat::find($requete->integer('achat')),
            default => null,
        };

        return view('retours.create', [
            'type' => $type,
            'config' => [
                'type' => $type->value,
                'motifs' => RetourService::MOTIFS[$type->value],
                'modes' => collect(ModePaiement::encaissements())->map(fn (ModePaiement $m) => ['valeur' => $m->value, 'libelle' => $m->libelle()])->all(),
                'document' => $document && $this->retournable($document) ? $this->detail($document) : null,
                'urls' => [
                    'recherche' => route('retours.documents'),
                    'enregistrer' => route('retours.store'),
                ],
            ],
        ]);
    }

    /** Étape 1 : ventes ou achats validés correspondant à la recherche (n° de document, de facture ou tiers). */
    public function documents(Request $requete): JsonResponse
    {
        $terme = trim((string) $requete->query('q'));
        $fournisseur = $requete->query('type') === 'fournisseur';

        if ($requete->filled('id')) {
            $document = ($fournisseur ? Achat::query() : Vente::query())->findOrFail($requete->integer('id'));
            abort_unless($this->retournable($document), 422, 'Ce document est annulé.');

            return response()->json($this->detail($document));
        }

        $like = '%'.$terme.'%';
        $documents = $fournisseur
            ? Achat::query()->with('fournisseur')->where('statut', StatutAchat::Valide)
                ->when($terme !== '', fn (Builder $q) => $q->where(fn (Builder $s) => $s->where('numero', 'like', $like)
                    ->orWhereHas('fournisseur', fn (Builder $f) => $f->where('nom', 'like', $like))))
                ->latest('date_achat')->latest('id')->limit(12)->get()
            : Vente::query()->with(['client', 'facture'])->where('statut', StatutVente::Validee)
                ->when($terme !== '', fn (Builder $q) => $q->where(fn (Builder $s) => $s->where('numero', 'like', $like)
                    ->orWhereHas('facture', fn (Builder $f) => $f->where('numero', 'like', $like))
                    ->orWhereHas('client', fn (Builder $c) => $c->where('nom', 'like', $like))))
                ->latest('date_vente')->latest('id')->limit(12)->get();

        return response()->json($documents->map(fn (Vente|Achat $document) => $this->resume($document))->values());
    }

    public function store(RetourRequest $requete): JsonResponse
    {
        $mode = $requete->filled('mode_remboursement') ? ModePaiement::from($requete->validated('mode_remboursement')) : null;

        try {
            $retour = $requete->validated('type') === 'fournisseur'
                ? $this->retours->creerFournisseur(Achat::findOrFail($requete->validated('achat_id')), $requete->quantites(), $requete->motifComplet(), $mode)
                : $this->retours->creerClient(Vente::findOrFail($requete->validated('vente_id')), $requete->quantites(), $requete->motifComplet(), $mode);
        } catch (OperationRefuseeException|StockInsuffisantException $erreur) {
            return response()->json(['message' => $erreur->getMessage(), 'errors' => ['lignes' => [$erreur->getMessage()]]], 422);
        }

        return response()->json([
            'message' => "Retour {$retour->numero} enregistré : stock mis à jour.",
            'numero' => $retour->numero,
            'total' => (float) $retour->total,
            'avoir' => (float) $retour->montant_avoir,
            'rembourse' => (float) $retour->montant_rembourse,
            'url' => route('retours.show', $retour),
            'url_bon' => route('retours.bon', $retour),
        ], 201);
    }

    public function show(Retour $retour): View
    {
        $retour->load(['lignes.produit.unite', 'utilisateur', 'vente.client', 'vente.facture', 'achat.fournisseur', 'mouvementsStock']);

        return view('retours.show', [
            'retour' => $retour,
            'annulation' => $retour->statut === StatutRetour::Annule
                ? JournalActivite::with('utilisateur')->where('action', 'retour.annule')->where('modele', 'retour')->where('modele_id', $retour->id)->latest('id')->first()
                : null,
        ]);
    }

    /** Bon de retour PDF (A4) ; ?telecharger=1 pour un téléchargement. */
    public function bon(Request $requete, Retour $retour): Response
    {
        return ReponsePdf::depuis($requete, $this->retours->pdf($retour), $this->retours->nomFichier($retour));
    }

    public function annuler(AnnulationRequest $requete, Retour $retour): RedirectResponse
    {
        try {
            $this->retours->annuler($retour, $requete->validated('motif'));
        } catch (OperationRefuseeException|StockInsuffisantException $erreur) {
            return back()->with('erreur', $erreur->getMessage());
        }

        return back()->with('succes', "Retour {$retour->numero} annulé : stock et reste à payer rétablis.");
    }

    private function liste(Request $requete, TypeRetour $type): View
    {
        $filtres = [
            'periode' => array_key_exists((string) $requete->query('periode', 'tout'), self::PERIODES) ? (string) $requete->query('periode', 'tout') : 'tout',
            'statut' => in_array($requete->query('statut'), ['valides', 'annules'], true) ? $requete->query('statut') : null,
            'recherche' => trim((string) $requete->query('recherche')),
        ];
        $client = $type === TypeRetour::Client;

        $base = Retour::query()
            ->where('type', $type)
            ->when($this->debutPeriode($filtres['periode']), fn (Builder $q, Carbon $debut) => $q->where('date_retour', '>=', $debut))
            ->when($filtres['recherche'] !== '', function (Builder $q) use ($filtres, $client) {
                $terme = '%'.$filtres['recherche'].'%';
                $q->where(fn (Builder $s) => $s->where('numero', 'like', $terme)->orWhere('motif', 'like', $terme)
                    ->when($client, fn (Builder $s) => $s->orWhereHas('vente', fn (Builder $v) => $v->where('numero', 'like', $terme)
                        ->orWhereHas('client', fn (Builder $c) => $c->where('nom', 'like', $terme))
                        ->orWhereHas('facture', fn (Builder $f) => $f->where('numero', 'like', $terme))))
                    ->when(! $client, fn (Builder $s) => $s->orWhereHas('achat', fn (Builder $a) => $a->where('numero', 'like', $terme)
                        ->orWhereHas('fournisseur', fn (Builder $f) => $f->where('nom', 'like', $terme)))));
            });

        $retours = (clone $base)
            ->with($client ? ['vente.client', 'vente.facture'] : ['achat.fournisseur'])
            ->when($filtres['statut'] === 'valides', fn (Builder $q) => $q->where('statut', StatutRetour::Valide))
            ->when($filtres['statut'] === 'annules', fn (Builder $q) => $q->where('statut', StatutRetour::Annule))
            ->latest('date_retour')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $donnees = compact('retours', 'filtres', 'type');

        if ($requete->header('X-Fragment') === 'liste') {
            return view('retours._liste', $donnees);
        }

        $valides = (clone $base)->where('statut', StatutRetour::Valide);

        return view('retours.index', [...$donnees, 'synthese' => [
            'nombre' => (clone $valides)->count(),
            'total' => (float) (clone $valides)->sum('total'),
            'avoir' => (float) (clone $valides)->sum('montant_avoir'),
            'rembourse' => (float) (clone $valides)->sum('montant_rembourse'),
        ]]);
    }

    private function retournable(Vente|Achat $document): bool
    {
        return ! in_array($document->statut, [StatutVente::Annulee, StatutAchat::Annule], true);
    }

    /** Résumé d'un document pour les cartes de l'étape 1. */
    private function resume(Vente|Achat $document): array
    {
        $estVente = $document instanceof Vente;

        return [
            'id' => $document->id,
            'numero' => $document->numero,
            'facture' => $estVente ? $document->facture?->numero : null,
            'date' => ($estVente ? $document->date_vente : $document->date_achat)->translatedFormat('j M Y'),
            'tiers' => $estVente ? $document->client?->nom : $document->fournisseur?->nom,
            'total' => (float) $document->total,
            'reste' => (float) $document->reste_a_payer,
        ];
    }

    /** Document choisi : résumé et lignes retournables (étape 2). */
    private function detail(Vente|Achat $document): array
    {
        $document->loadMissing($document instanceof Vente ? ['client', 'facture'] : ['fournisseur']);

        return [
            ...$this->resume($document),
            'lignes' => $this->retours->retournables($document)->map(fn (array $ligne) => [
                'produit_id' => $ligne['produit']->id,
                'nom' => $ligne['produit']->nom,
                'reference' => $ligne['produit']->reference,
                'unite' => $ligne['produit']->unite?->abreviation,
                'quantite' => $ligne['quantite'],
                'retourne' => $ligne['retourne'],
                'retournable' => $ligne['retournable'],
                'prix_unitaire' => $ligne['prix_unitaire'],
            ])->values()->all(),
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
