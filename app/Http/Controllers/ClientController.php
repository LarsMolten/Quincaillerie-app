<?php

namespace App\Http\Controllers;

use App\Enums\StatutVente;
use App\Http\Requests\ClientRequest;
use App\Models\Client;
use App\Models\Paiement;
use App\Models\Vente;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Clients (droit clients.gerer) : liste, panneau de saisie, fiche avec créance et plafond.
 * Les débiteurs ont leur page « Créances » (CreanceController).
 * Le « Client comptoir » (ventes anonymes) ne peut être ni supprimé, ni désactivé, ni renommé,
 * et n'a jamais de crédit (CLAUDE.md §5.8).
 */
class ClientController extends Controller
{
    public function index(Request $requete): View
    {
        $recherche = trim((string) $requete->query('recherche'));
        $statut = in_array($requete->query('statut'), ['inactifs', 'tous'], true) ? $requete->query('statut') : 'actifs';
        $tri = $requete->query('tri') === 'creance' ? 'creance' : 'nom';
        $ordre = $requete->query('ordre') === 'desc' ? 'desc' : 'asc';

        $clients = Client::query()
            ->withSum(['ventes as creance' => fn ($q) => $q->where('statut', StatutVente::Validee)], 'reste_a_payer')
            ->withCount('ventes') // un client sans vente peut être supprimé
            ->when($recherche !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('nom', 'like', "%{$recherche}%")
                ->orWhere('telephone', 'like', "%{$recherche}%")
                ->orWhere('email', 'like', "%{$recherche}%")))
            ->when($statut === 'actifs', fn ($q) => $q->where('actif', true))
            ->when($statut === 'inactifs', fn ($q) => $q->where('actif', false))
            // Le Client comptoir est épinglé en tête
            ->orderByRaw('CASE WHEN nom = ? THEN 0 ELSE 1 END', [Client::COMPTOIR])
            ->orderBy($tri, $ordre)
            ->orderBy('nom')
            ->paginate(15)
            ->withQueryString();

        $donnees = compact('clients', 'recherche', 'statut');

        if ($requete->header('X-Fragment') === 'liste') {
            return view('clients._liste', $donnees);
        }

        return view('clients.index', [
            ...$donnees,
            'synthese' => [
                'actifs' => Client::where('actif', true)->count(),
                'creance' => (float) Vente::validees()->sum('reste_a_payer'),
                'debiteurs' => Vente::validees()->where('reste_a_payer', '>', 0)->distinct()->count('client_id'),
            ],
        ]);
    }

    public function show(Client $client): View
    {
        $creance = $client->creance_totale;
        $plafond = $client->plafond_credit !== null && ! $client->estComptoir() ? (float) $client->plafond_credit : null;
        $ventesValidees = $client->ventes()->validees();

        return view('clients.show', [
            'client' => $client,
            'creance' => $creance,
            'plafond' => $plafond,
            'utilisation' => $plafond ? $creance / $plafond * 100 : null,
            'ventesImpayees' => (clone $ventesValidees)->where('reste_a_payer', '>', 0)->count(),
            'totalAchats' => (float) (clone $ventesValidees)->sum('total'),
            'nombreVentes' => (clone $ventesValidees)->count(),
            'ventes' => $client->ventes()->with('facture')->latest('date_vente')->latest('id')->paginate(10, ['*'], 'page_ventes'),
            'paiements' => Paiement::whereHasMorph('payable', [Vente::class], fn ($q) => $q->where('client_id', $client->id))
                ->with('payable', 'utilisateur')
                ->latest('date_paiement')
                ->limit(15)
                ->get(),
        ]);
    }

    public function store(ClientRequest $requete): RedirectResponse
    {
        $client = Client::create($requete->validated());

        return to_route('clients.index')->with('succes', "Client « {$client->nom} » créé.");
    }

    public function update(ClientRequest $requete, Client $client): RedirectResponse
    {
        $donnees = $requete->validated();

        // Le comptoir garde son nom et n'a jamais de crédit
        if ($client->estComptoir()) {
            if ($donnees['nom'] !== Client::COMPTOIR || $donnees['plafond_credit'] !== null) {
                return back()->withInput()->with('erreur', 'Le « Client comptoir » ne peut être ni renommé ni recevoir de plafond de crédit.');
            }
        }

        $client->update($donnees);

        return back()->with('succes', "Client « {$client->nom} » modifié.");
    }

    public function statut(Client $client): RedirectResponse
    {
        if ($client->estComptoir()) {
            return back()->with('erreur', 'Le « Client comptoir » est toujours actif : il sert aux ventes anonymes.');
        }

        $client->update(['actif' => ! $client->actif]);

        return back()->with('succes', $client->actif
            ? "Client « {$client->nom} » réactivé."
            : "Client « {$client->nom} » désactivé : il n'est plus proposé en caisse.");
    }

    public function destroy(Client $client): RedirectResponse
    {
        if ($client->estComptoir()) {
            return back()->with('erreur', 'Le « Client comptoir » ne peut pas être supprimé : il sert aux ventes anonymes.');
        }

        if ($client->ventes()->exists()) {
            return back()->with('erreur', "Le client « {$client->nom} » a des ventes enregistrées : il ne peut pas être supprimé. Désactivez-le plutôt.");
        }

        $client->delete();

        return to_route('clients.index')->with('succes', "Client « {$client->nom} » supprimé.");
    }
}
