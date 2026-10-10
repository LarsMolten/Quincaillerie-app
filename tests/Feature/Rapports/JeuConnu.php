<?php

namespace Tests\Feature\Rapports;

use App\Enums\CategorieDepense;
use App\Enums\ModePaiement;
use App\Models\Achat;
use App\Models\Categorie;
use App\Models\Client;
use App\Models\Depense;
use App\Models\Fournisseur;
use App\Models\Produit;
use App\Models\Role;
use App\Models\Utilisateur;
use App\Models\Vente;
use App\Services\AchatService;
use App\Services\PaiementService;
use App\Services\RetourService;
use App\Services\VenteService;
use Database\Seeders\ClientComptoirSeeder;
use Database\Seeders\DroitSeeder;
use Database\Seeders\ParametreSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Jeu de données connu des rapports, créé par les vrais services à des dates fixes (aujourd'hui : 12/10/2026).
 *
 * Achats : 02/10 Holcim 50 ciments × 30 000 = 1 500 000 (1 000 000 payés en espèces, reste 500 000) ;
 *          03/10 Visserie 40 clous × 6 000 + 100 vis × 100 = 250 000 (payé par virement) ;
 *          20/09 Holcim 10 ciments = 300 000 (mois précédent).
 * Ventes : 05/10 Responsable 3 ciments + 2 clous = 140 000 (espèces, coût 102 000) ;
 *          06/10 Vendeur 1 ciment à crédit Rakoto BTP = 40 000 (coût 30 000) ;
 *          10/10 Responsable 5 clous = 50 000 (Mobile Money, coût 30 000) ;
 *          25/09 Responsable 2 ciments = 80 000 (mois précédent, coût 60 000).
 * Retours : 08/10 client, 1 ciment de la vente du 05/10 = 40 000 remboursés en espèces (coût 30 000) ;
 *           09/10 fournisseur, 5 clous à Visserie = 30 000 remboursés (achat soldé).
 * Dépenses : 07/10 transport 20 000 ; 11/10 loyer 100 000 ; une dépense supprimée (99 000) ignorée.
 * Paiement : 11/10 Rakoto BTP règle 15 000 (Mobile Money) : créance restante 25 000.
 * Stock final : ciment 55 (min 60 : faible), clous 28, vis 100 (jamais vendues), tuyau 0 (rupture, jamais vendu).
 */
trait JeuConnu
{
    protected Utilisateur $responsable;

    protected Utilisateur $vendeur;

    protected Produit $ciment;

    protected Produit $clous;

    protected Produit $vis;

    protected Produit $tuyau;

    protected Fournisseur $holcim;

    protected Fournisseur $visserie;

    protected Categorie $categorieCiments;

    protected Client $rakoto;

    protected function creerJeuConnu(): void
    {
        $this->seed([RoleSeeder::class, DroitSeeder::class, ParametreSeeder::class, ClientComptoirSeeder::class]);
        $this->responsable = Utilisateur::factory()->create(['nom' => 'Rasoa Responsable', 'role_id' => Role::where('nom', RoleSeeder::RESPONSABLE)->firstOrFail()->id]);
        $this->vendeur = Utilisateur::factory()->create(['nom' => 'Hery Vendeur', 'role_id' => Role::where('nom', RoleSeeder::VENDEUR)->firstOrFail()->id]);

        $this->categorieCiments = Categorie::factory()->create(['nom' => 'Ciments']);
        $quincaillerie = Categorie::factory()->create(['nom' => 'Quincaillerie']);
        $produit = fn (string $nom, Categorie $c, float $achat, float $vente, float $minimum) => Produit::factory()->create([
            'nom' => $nom, 'categorie_id' => $c->id, 'stock_actuel' => 0, 'prix_achat' => $achat, 'prix_vente' => $vente, 'stock_minimum' => $minimum, 'actif' => true,
        ]);
        $this->ciment = $produit('Ciment 50 kg', $this->categorieCiments, 30000, 40000, 60);
        $this->clous = $produit('Clous 70 mm', $quincaillerie, 6000, 10000, 5);
        $this->vis = $produit('Vis 4 × 40', $quincaillerie, 100, 200, 10);
        $this->tuyau = $produit('Tuyau PVC', $quincaillerie, 5000, 8000, 2);

        $this->holcim = Fournisseur::factory()->create(['nom' => 'Holcim Madagascar', 'actif' => true]);
        $this->visserie = Fournisseur::factory()->create(['nom' => 'Visserie Tana', 'actif' => true]);
        $this->rakoto = Client::factory()->create(['nom' => 'Rakoto BTP', 'plafond_credit' => 1000000, 'actif' => true]);
        $comptoir = Client::where('nom', Client::COMPTOIR)->firstOrFail();

        $le = function (string $quand, Utilisateur $qui, callable $action) {
            Carbon::setTestNow($quand);
            Auth::setUser($qui);

            return $action();
        };
        $achats = app(AchatService::class);
        $ventes = app(VenteService::class);

        $le('2026-09-20 09:00', $this->responsable, fn () => $achats->creer($this->holcim, [['produit_id' => $this->ciment->id, 'quantite' => 10, 'prix_achat' => 30000]], 300000, ModePaiement::Especes, null, today()));
        $le('2026-09-25 10:00', $this->responsable, fn () => $ventes->creer($comptoir, [['produit_id' => $this->ciment->id, 'quantite' => 2]], 0, ModePaiement::Especes, 80000));
        $le('2026-10-02 09:00', $this->responsable, fn () => $achats->creer($this->holcim, [['produit_id' => $this->ciment->id, 'quantite' => 50, 'prix_achat' => 30000]], 1000000, ModePaiement::Especes, null, today()));
        /** @var Achat $achatVisserie */
        $achatVisserie = $le('2026-10-03 09:00', $this->responsable, fn () => $achats->creer($this->visserie, [
            ['produit_id' => $this->clous->id, 'quantite' => 40, 'prix_achat' => 6000],
            ['produit_id' => $this->vis->id, 'quantite' => 100, 'prix_achat' => 100],
        ], 250000, ModePaiement::Virement, null, today()));
        /** @var Vente $venteDu5 */
        $venteDu5 = $le('2026-10-05 10:00', $this->responsable, fn () => $ventes->creer($comptoir, [['produit_id' => $this->ciment->id, 'quantite' => 3], ['produit_id' => $this->clous->id, 'quantite' => 2]], 0, ModePaiement::Especes, 140000));
        /** @var Vente $venteCredit */
        $venteCredit = $le('2026-10-06 11:00', $this->vendeur, fn () => $ventes->creer($this->rakoto, [['produit_id' => $this->ciment->id, 'quantite' => 1]], 0, ModePaiement::Credit, 0));
        $le('2026-10-07 08:00', $this->responsable, fn () => Depense::factory()->create(['categorie' => CategorieDepense::Transport, 'libelle' => 'Taxi-brousse', 'montant' => 20000, 'date_depense' => today(), 'utilisateur_id' => $this->responsable->id]));
        $le('2026-10-08 14:00', $this->responsable, fn () => app(RetourService::class)->creerClient($venteDu5, [$this->ciment->id => 1], 'Produit défectueux', ModePaiement::Especes));
        $le('2026-10-09 14:00', $this->responsable, fn () => app(RetourService::class)->creerFournisseur($achatVisserie, [$this->clous->id => 5], 'Produit défectueux', ModePaiement::Especes));
        $le('2026-10-10 16:00', $this->responsable, fn () => $ventes->creer($comptoir, [['produit_id' => $this->clous->id, 'quantite' => 5]], 0, ModePaiement::MobileMoney, 50000));
        $le('2026-10-11 09:00', $this->responsable, function () use ($venteCredit) {
            Depense::factory()->create(['categorie' => CategorieDepense::Loyer, 'libelle' => 'Loyer octobre', 'montant' => 100000, 'date_depense' => today(), 'utilisateur_id' => $this->responsable->id]);
            Depense::factory()->create(['montant' => 99000, 'date_depense' => today(), 'utilisateur_id' => $this->responsable->id])->delete();
            app(PaiementService::class)->enregistrer($venteCredit, 15000, ModePaiement::MobileMoney);
        });

        Carbon::setTestNow('2026-10-12 15:00:00');
        Auth::setUser($this->responsable);
    }
}
