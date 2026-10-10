<?php

namespace App\Http\Controllers;

use App\Enums\StatutFacture;
use App\Http\Requests\EnvoiFactureRequest;
use App\Mail\FactureMail;
use App\Models\Facture;
use App\Models\Parametre;
use App\Models\Vente;
use App\Services\FactureService;
use App\Services\JournalService;
use App\Support\ReponsePdf;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Factures : liste filtrée, aperçu, PDF (A4 et ticket 80 mm), envoi par email et lien de partage.
 * Aucune modification possible : une correction passe par l'annulation de la vente ou un retour.
 */
class FactureController extends Controller
{
    public const PERIODES = ['aujourdhui' => 'Aujourd\'hui', '7j' => '7 jours', '30j' => '30 jours', 'mois' => 'Ce mois', 'tout' => 'Tout'];

    /** Durée de validité d'un lien de partage (route publique signée). */
    public const JOURS_PARTAGE = 7;

    public function __construct(
        private readonly FactureService $factures,
        private readonly JournalService $journal,
    ) {}

    public function index(Request $requete): View
    {
        $filtres = [
            'periode' => array_key_exists((string) $requete->query('periode', 'tout'), self::PERIODES) ? (string) $requete->query('periode', 'tout') : 'tout',
            'statut' => in_array($requete->query('statut'), ['emises', 'annulees'], true) ? $requete->query('statut') : null,
            'paiement' => in_array($requete->query('paiement'), ['paye', 'partiel', 'credit'], true) ? $requete->query('paiement') : null,
            'recherche' => trim((string) $requete->query('recherche')),
        ];

        $base = Facture::query()
            ->when($this->debutPeriode($filtres['periode']), fn (Builder $q, Carbon $debut) => $q->where('date_emission', '>=', $debut))
            ->when($filtres['recherche'] !== '', function (Builder $q) use ($filtres) {
                $terme = '%'.$filtres['recherche'].'%';
                $q->where(fn (Builder $s) => $s->where('numero', 'like', $terme)
                    ->orWhereHas('vente', fn (Builder $v) => $v->where('numero', 'like', $terme)
                        ->orWhereHas('client', fn (Builder $c) => $c->where('nom', 'like', $terme))));
            });

        $factures = (clone $base)
            ->with(['vente.client'])
            ->when($filtres['statut'] === 'emises', fn (Builder $q) => $q->where('statut', StatutFacture::Emise))
            ->when($filtres['statut'] === 'annulees', fn (Builder $q) => $q->where('statut', StatutFacture::Annulee))
            ->when($filtres['paiement'], function (Builder $q, string $paiement) {
                $q->where('statut', StatutFacture::Emise)->whereHas('vente', fn (Builder $v) => match ($paiement) {
                    'paye' => $v->where('reste_a_payer', '<=', 0),
                    'partiel' => $v->where('reste_a_payer', '>', 0)->where('montant_paye', '>', 0),
                    'credit' => $v->where('reste_a_payer', '>', 0)->where('montant_paye', '<=', 0),
                });
            })
            ->latest('date_emission')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        if ($requete->header('X-Fragment') === 'liste') {
            return view('factures._liste', compact('factures', 'filtres'));
        }

        $emises = (clone $base)->where('statut', StatutFacture::Emise);

        return view('factures.index', [
            'factures' => $factures,
            'filtres' => $filtres,
            'apercu' => $requete->integer('apercu') ?: null,
            'synthese' => [
                'montant' => (float) (clone $emises)->sum('total'),
                'nombre' => (clone $emises)->count(),
                'reste' => (float) Vente::whereIn('id', (clone $emises)->select('vente_id'))->sum('reste_a_payer'),
            ],
        ]);
    }

    /** Données de l'aperçu (modale) : métadonnées et adresses des actions. */
    public function show(Facture $facture): JsonResponse
    {
        $facture->load('vente.client');
        $client = $facture->vente->client;

        return response()->json([
            'id' => $facture->id,
            'numero' => $facture->numero,
            'annulee' => $facture->statut === StatutFacture::Annulee,
            'date' => $facture->date_emission->translatedFormat('j F Y à H:i'),
            'total' => format_ar($facture->total),
            'client' => $client?->nom,
            'email' => $client?->email,
            'telephone' => $client?->telephone,
            'urls' => [
                'a4' => route('factures.pdf', $facture),
                'ticket' => route('factures.pdf', [$facture, 'format' => FactureService::FORMAT_TICKET]),
                'telecharger' => route('factures.pdf', [$facture, 'telecharger' => 1]),
                'envoyer' => route('factures.envoyer', $facture),
                'partager' => route('factures.partager', $facture),
                'vente' => route('ventes.show', $facture->vente_id),
            ],
        ]);
    }

    /** PDF A4 (par défaut) ou ticket 80 mm ; ?telecharger=1 pour un téléchargement. */
    public function pdf(Request $requete, Facture $facture): Response
    {
        $format = $requete->query('format') === FactureService::FORMAT_TICKET ? FactureService::FORMAT_TICKET : FactureService::FORMAT_A4;

        return ReponsePdf::depuis($requete, $this->factures->pdf($facture, $format), $this->factures->nomFichier($facture, $format));
    }

    public function envoyer(EnvoiFactureRequest $requete, Facture $facture): JsonResponse
    {
        $adresse = $requete->validated('email');

        Mail::to($adresse)->send(new FactureMail($facture, $requete->validated('message'), $this->factures));

        $this->journal->enregistrer('facture.envoyee', $facture, ['numero' => $facture->numero, 'destinataire' => $adresse]);

        return response()->json(['message' => "Facture {$facture->numero} envoyée à {$adresse}."]);
    }

    /**
     * Lien de partage (WhatsApp) : URL signée, valable JOURS_PARTAGE jours, vers le PDF de cette seule facture.
     * Exception à la règle « authentification sur toutes les routes » validée pour ce besoin ; chaque lien est journalisé.
     */
    public function partager(Facture $facture): JsonResponse
    {
        $facture->load('vente.client');
        $expiration = now()->addDays(self::JOURS_PARTAGE);
        $url = URL::temporarySignedRoute('factures.publique', $expiration, ['facture' => $facture->id]);

        $texte = sprintf(
            "Bonjour,\nVoici votre facture %s de %s : %s.\n%s\n(lien valable jusqu'au %s)",
            $facture->numero,
            Parametre::valeur('nom_entreprise', config('app.name')),
            format_ar($facture->total),
            $url,
            $expiration->translatedFormat('j F Y'),
        );
        $telephone = $this->telephoneInternational($facture->vente->client?->telephone);

        $this->journal->enregistrer('facture.partagee', $facture, ['numero' => $facture->numero, 'expiration' => $expiration->toIso8601String()]);

        return response()->json([
            'url' => $url,
            'expiration' => $expiration->translatedFormat('j F Y'),
            'whatsapp' => 'https://wa.me/'.($telephone ?? '').'?text='.rawurlencode($texte),
        ]);
    }

    /** Route publique signée (middleware « signed ») : PDF A4, sans compte. */
    public function publique(Facture $facture): Response
    {
        return $this->factures->pdf($facture)->stream($this->factures->nomFichier($facture));
    }

    /** Numéro au format international sans « + » (wa.me) ; numéros malgaches à 10 chiffres complétés par 261. */
    private function telephoneInternational(?string $telephone): ?string
    {
        $chiffres = preg_replace('/\D/', '', (string) $telephone);

        return match (true) {
            $chiffres === '' => null,
            strlen($chiffres) === 10 && str_starts_with($chiffres, '0') => '261'.substr($chiffres, 1),
            default => $chiffres,
        };
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
