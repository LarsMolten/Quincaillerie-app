<?php

namespace Database\Seeders;

use App\Enums\CategorieDepense;
use App\Enums\ModePaiement;
use App\Enums\StatutAchat;
use App\Enums\StatutFacture;
use App\Enums\StatutVente;
use App\Enums\TypeMouvementStock;
use App\Models\Achat;
use App\Models\Categorie;
use App\Models\Client;
use App\Models\Depense;
use App\Models\Fournisseur;
use App\Models\Produit;
use App\Models\Role;
use App\Models\Unite;
use App\Models\Utilisateur;
use App\Models\Vente;
use App\Services\MouvementStockService;
use App\Support\Ean13;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Données de démonstration réalistes (environnement local uniquement) :
 * ~65 produits, 10 fournisseurs, 30 clients, achats, ventes et dépenses des 30 derniers jours.
 *
 * Le stock évolue uniquement via MouvementStockService, à la date de chaque document.
 * Lancement : php artisan db:seed --class=DemoSeeder (après les données de base).
 */
class DemoSeeder extends Seeder
{
    private const JOURS = 30;

    /** [nom, catégorie, abréviation unité, prix d'achat, prix de vente, stock minimum, stock initial] */
    private const PRODUITS = [
        ['Ciment CEM II 42,5 – sac 50 kg', 'Matériaux de construction', 'sac', 33000, 37000, 40, 220],
        ['Chaux vive – sac 25 kg', 'Matériaux de construction', 'sac', 14000, 17000, 20, 60],
        ['Fer à béton HA 8 mm – barre 12 m', 'Matériaux de construction', 'barre', 17500, 21000, 50, 300],
        ['Fer à béton HA 10 mm – barre 12 m', 'Matériaux de construction', 'barre', 27000, 32000, 40, 200],
        ['Fer à béton HA 12 mm – barre 12 m', 'Matériaux de construction', 'barre', 39000, 45000, 30, 120],
        ['Fil d\'attache recuit', 'Matériaux de construction', 'kg', 7000, 9000, 10, 50],
        ['Agglo creux 15 cm', 'Matériaux de construction', 'pce', 1800, 2300, 200, 900],
        ['Tôle ondulée galvanisée 3 m', 'Toiture et tôlerie', 'flle', 28000, 33000, 50, 180],
        ['Tôle ondulée galvanisée 2 m', 'Toiture et tôlerie', 'flle', 19000, 23000, 40, 150],
        ['Faîtière galvanisée', 'Toiture et tôlerie', 'pce', 9000, 12000, 20, 60],
        ['Pointe de tôle 100 mm', 'Toiture et tôlerie', 'kg', 9500, 12500, 10, 40],
        ['Gouttière PVC 4 m', 'Toiture et tôlerie', 'pce', 22000, 28000, 10, 25],
        ['Tuyau PVC pression Ø 20 mm – 4 m', 'Plomberie', 'pce', 6000, 8000, 30, 120],
        ['Tuyau PVC pression Ø 32 mm – 4 m', 'Plomberie', 'pce', 9500, 12500, 20, 80],
        ['Tuyau PVC évacuation Ø 100 mm – 4 m', 'Plomberie', 'pce', 26000, 32000, 10, 35],
        ['Coude PVC 90° Ø 20 mm', 'Plomberie', 'pce', 500, 800, 50, 300],
        ['Té PVC Ø 20 mm', 'Plomberie', 'pce', 600, 1000, 50, 250],
        ['Colle PVC 250 ml', 'Plomberie', 'pot', 9000, 12000, 10, 40],
        ['Robinet de puisage laiton 1/2"', 'Plomberie', 'pce', 14000, 19000, 10, 40],
        ['Robinet mélangeur d\'évier', 'Plomberie', 'pce', 65000, 85000, 3, 6],
        ['Vanne d\'arrêt 3/4"', 'Plomberie', 'pce', 18000, 24000, 5, 25],
        ['Ruban téflon', 'Plomberie', 'pce', 500, 1000, 50, 300],
        ['WC complet céramique', 'Sanitaire', 'pce', 240000, 310000, 2, 4],
        ['Lavabo céramique avec colonne', 'Sanitaire', 'pce', 145000, 190000, 2, 5],
        ['Pommeau de douche', 'Sanitaire', 'pce', 12000, 18000, 5, 20],
        ['Siphon de lavabo PVC', 'Sanitaire', 'pce', 6000, 9000, 10, 30],
        ['Câble électrique 1,5 mm² – rouleau 100 m', 'Électricité', 'rlx', 95000, 120000, 5, 18],
        ['Câble électrique 2,5 mm²', 'Électricité', 'm', 1300, 1800, 100, 500],
        ['Interrupteur simple encastré', 'Électricité', 'pce', 3500, 5000, 20, 80],
        ['Prise de courant 2P+T', 'Électricité', 'pce', 4500, 6500, 20, 80],
        ['Disjoncteur 16 A', 'Électricité', 'pce', 18000, 25000, 5, 25],
        ['Ampoule LED 9 W E27', 'Électricité', 'pce', 4000, 6000, 30, 150],
        ['Gaine ICTA Ø 20 mm – rouleau 50 m', 'Électricité', 'rlx', 32000, 42000, 5, 20],
        ['Ruban isolant', 'Électricité', 'pce', 1200, 2000, 30, 150],
        ['Peinture à eau blanche 20 L', 'Peinture et revêtements', 'pot', 120000, 150000, 5, 20],
        ['Peinture glycéro 4 L', 'Peinture et revêtements', 'pot', 70000, 88000, 5, 15],
        ['Peinture antirouille 1 L', 'Peinture et revêtements', 'pot', 22000, 28000, 5, 25],
        ['Vernis bois 1 L', 'Peinture et revêtements', 'pot', 25000, 32000, 5, 6],
        ['Enduit de lissage 5 kg', 'Peinture et revêtements', 'sac', 18000, 24000, 5, 20],
        ['Pinceau plat 50 mm', 'Peinture et revêtements', 'pce', 3500, 5000, 20, 80],
        ['Rouleau à peinture 180 mm', 'Peinture et revêtements', 'pce', 9000, 13000, 10, 40],
        ['Diluant synthétique', 'Peinture et revêtements', 'L', 9000, 12000, 10, 40],
        ['Marteau de coffreur 600 g', 'Outillage', 'pce', 18000, 25000, 5, 20],
        ['Truelle de maçon', 'Outillage', 'pce', 8000, 12000, 10, 30],
        ['Niveau à bulle 60 cm', 'Outillage', 'pce', 15000, 22000, 5, 15],
        ['Mètre ruban 5 m', 'Outillage', 'pce', 6000, 9000, 10, 40],
        ['Scie à métaux', 'Outillage', 'pce', 14000, 20000, 5, 15],
        ['Jeu de 6 tournevis', 'Outillage', 'pce', 15000, 22000, 5, 15],
        ['Perceuse à percussion 750 W', 'Outillage', 'pce', 165000, 210000, 2, 3],
        ['Disque à tronçonner métal 230 mm', 'Outillage', 'pce', 5500, 8000, 20, 80],
        ['Clous 70 mm', 'Visserie et clouterie', 'kg', 7500, 10000, 20, 100],
        ['Vis à bois 4 × 40 – boîte de 200', 'Visserie et clouterie', 'bte', 7000, 10000, 10, 40],
        ['Chevilles nylon 8 mm – paquet de 100', 'Visserie et clouterie', 'pqt', 4000, 6000, 10, 40],
        ['Boulon tête hexagonale M10 × 60', 'Visserie et clouterie', 'pce', 800, 1200, 50, 300],
        ['Tire-fond 8 × 80', 'Visserie et clouterie', 'pce', 400, 700, 50, 400],
        ['Cadenas laiton 40 mm', 'Serrurerie', 'pce', 9000, 13000, 10, 40],
        ['Cadenas acier 60 mm', 'Serrurerie', 'pce', 18000, 25000, 5, 20],
        ['Serrure de porte à larder', 'Serrurerie', 'pce', 35000, 48000, 5, 15],
        ['Charnière acier 100 mm – paire', 'Serrurerie', 'pce', 3000, 4500, 20, 80],
        ['Verrou à targette', 'Serrurerie', 'pce', 5000, 7500, 10, 30],
        ['Pelle ronde emmanchée', 'Jardinage et agriculture', 'pce', 16000, 22000, 5, 20],
        ['Bêche', 'Jardinage et agriculture', 'pce', 18000, 25000, 5, 15],
        ['Angady', 'Jardinage et agriculture', 'pce', 15000, 20000, 10, 40],
        ['Brouette 90 L', 'Jardinage et agriculture', 'pce', 145000, 185000, 2, 5],
        ['Arrosoir 10 L', 'Jardinage et agriculture', 'pce', 12000, 17000, 5, 15],
        ['Tuyau d\'arrosage 25 m', 'Jardinage et agriculture', 'rlx', 45000, 60000, 3, 10],
    ];

    /** [nom, contact, téléphone, catégories fournies] */
    private const FOURNISSEURS = [
        ['Ravinala Matériaux', 'Rakotomalala Hery', '020 22 214 56', ['Matériaux de construction']],
        ['Betsiboka Fers et Aciers', 'Randrianarisoa Fara', '020 22 331 08', ['Matériaux de construction', 'Visserie et clouterie']],
        ['Fianarantsoa Toiture', 'Rasoanaivo Lova', '034 11 452 77', ['Toiture et tôlerie']],
        ['Imerina Plomberie Distribution', 'Andrianjafy Tiana', '033 12 908 41', ['Plomberie']],
        ['Ankorondrano Sanitaire', 'Razafindrakoto Mamy', '034 20 118 63', ['Sanitaire', 'Plomberie']],
        ['Océan Indien Électricité', 'Rabemananjara Nirina', '032 05 774 19', ['Électricité']],
        ['Couleurs de l\'Île', 'Rakotoarisoa Faly', '034 07 663 25', ['Peinture et revêtements']],
        ['Toamasina Import Outillage', 'Ravelojaona Soa', '020 53 321 90', ['Outillage']],
        ['Analamanga Serrurerie', 'Randriamanana Toky', '033 15 240 82', ['Serrurerie', 'Visserie et clouterie']],
        ['Vakinankaratra Agri-Outils', 'Rasolofo Haja', '034 18 905 37', ['Jardinage et agriculture']],
    ];

    private const PRENOMS = [
        'Hery', 'Fara', 'Lova', 'Tiana', 'Mamy', 'Nirina', 'Faly', 'Soa', 'Toky', 'Haja', 'Voahangy', 'Rivo',
        'Fanja', 'Andry', 'Miora', 'Tahina', 'Njaka', 'Holy', 'Mialy', 'Zo', 'Onja', 'Fenosoa', 'Rado', 'Sitraka',
    ];

    private const NOMS = [
        'Rakoto', 'Rasoa', 'Randria', 'Razafy', 'Rabe', 'Rakotondrabe', 'Andriamasy', 'Ranaivo', 'Rajaonarison',
        'Ramanantsoa', 'Ratsimba', 'Rakotovao', 'Raharison', 'Andrianaivo', 'Rasolonjatovo',
    ];

    private const ENTREPRISES = [
        'Entreprise BTP Fanantenana', 'Chantier Ambohimanarina', 'Société Mandrosoa Construction',
        'Atelier Menuiserie Tsara', 'Hôtel Ravintsara', 'Coopérative Tantely', 'École privée Ny Fanilo',
        'Garage Mahamasina',
    ];

    private MouvementStockService $stock;

    /** Heure réelle du lancement (l'horloge est ensuite figée document par document). */
    private Carbon $maintenant;

    /** Compteurs de numérotation par préfixe et par année. */
    private array $compteurs = [];

    /** Catégories livrées par chaque fournisseur (id => noms de catégories). */
    private array $categoriesFournies = [];

    public function run(MouvementStockService $stock): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Les données de démonstration sont interdites en production.');
        }

        if (Produit::withTrashed()->exists()) {
            $this->command?->warn('Des produits existent déjà : données de démonstration ignorées (lancez migrate:fresh --seed).');

            return;
        }

        $this->stock = $stock;
        mt_srand(2026); // Graine fixe : tirages mt_rand reproductibles

        $this->maintenant = now();
        $debut = $this->maintenant->copy()->startOfDay()->subDays(self::JOURS)->setTime(7, 30);
        Carbon::setTestNow($debut);

        try {
            $utilisateurs = $this->creerUtilisateurs();
            Auth::setUser($utilisateurs['magasinier']);

            $produits = $this->creerProduits();
            $fournisseurs = $this->creerFournisseurs();
            $clients = $this->creerClients();

            for ($jour = 1; $jour <= self::JOURS; $jour++) {
                $date = $debut->copy()->addDays($jour)->startOfDay();

                if ($jour % 3 === 1) {
                    Auth::setUser($utilisateurs['responsable']);
                    $this->creerAchat($date->copy()->setTime(9, 0), $fournisseurs, $produits);
                }

                $this->creerVentesDuJour($date, $produits, $clients, [$utilisateurs['vendeur'], $utilisateurs['responsable']]);
                $this->creerDepensesDuJour($date, $jour, $utilisateurs['responsable']);
            }
        } finally {
            Carbon::setTestNow();
            Auth::forgetUser();
        }

        $this->command?->info(sprintf(
            'Démonstration : %d produits, %d ventes, %d achats.',
            $produits->count(),
            Vente::count(),
            Achat::count(),
        ));
    }

    /**
     * Un compte par rôle, avec le même mot de passe que l'administrateur.
     *
     * @return array<string, Utilisateur>
     */
    private function creerUtilisateurs(): array
    {
        $comptes = [
            'responsable' => ['Rasoanirina Voahangy', 'responsable@quincaillerie.test', RoleSeeder::RESPONSABLE],
            'vendeur' => ['Rakotonirina Andry', 'vendeur@quincaillerie.test', RoleSeeder::VENDEUR],
            'magasinier' => ['Randrianasolo Rivo', 'magasinier@quincaillerie.test', RoleSeeder::MAGASINIER],
        ];

        return collect($comptes)->map(fn (array $compte) => Utilisateur::firstOrCreate(
            ['email' => $compte[1]],
            [
                'nom' => $compte[0],
                'password' => config('quincaillerie.admin.mot_de_passe'),
                'role_id' => Role::where('nom', $compte[2])->firstOrFail()->id,
            ],
        ))->all();
    }

    /** Produits du catalogue, avec leur stock initial enregistré comme mouvement. */
    private function creerProduits(): Collection
    {
        $categories = Categorie::pluck('id', 'nom');
        $unites = Unite::pluck('id', 'abreviation');

        return collect(self::PRODUITS)->map(function (array $ligne, int $index) use ($categories, $unites) {
            [$nom, $categorie, $unite, $prixAchat, $prixVente, $stockMinimum, $stockInitial] = $ligne;

            $produit = Produit::create([
                'reference' => sprintf('PRD-%05d', $index + 1),
                'code_barres' => Ean13::completer(sprintf('%s0000%05d', Ean13::PREFIXE_INTERNE, $index + 1)),
                'nom' => $nom,
                'categorie_id' => $categories[$categorie],
                'unite_id' => $unites[$unite],
                'prix_achat' => $prixAchat,
                'prix_vente' => $prixVente,
                // Prix de gros pour les articles vendus en volume
                'prix_gros' => $stockMinimum >= 30 ? round($prixVente * 0.95, -2) : null,
                'stock_minimum' => $stockMinimum,
                'actif' => true,
            ]);

            $this->stock->enregistrer($produit, TypeMouvementStock::AjustementPositif, $stockInitial, null, 'Stock initial');

            return $produit;
        });
    }

    private function creerFournisseurs(): Collection
    {
        return collect(self::FOURNISSEURS)->map(function (array $ligne) {
            [$nom, $contact, $telephone, $categories] = $ligne;

            $fournisseur = Fournisseur::create([
                'nom' => $nom,
                'contact' => $contact,
                'telephone' => $telephone,
                'email' => null,
                'adresse' => 'Antananarivo',
                'actif' => true,
            ]);
            $this->categoriesFournies[$fournisseur->id] = $categories;

            return $fournisseur;
        });
    }

    private function creerClients(): Collection
    {
        $clients = collect(self::ENTREPRISES)->map(fn (string $nom) => Client::create([
            'nom' => $nom,
            'telephone' => $this->telephone(),
            'adresse' => 'Antananarivo',
            'plafond_credit' => 2000000,
            'actif' => true,
        ]));

        while ($clients->count() < 30) {
            $nom = self::NOMS[mt_rand(0, count(self::NOMS) - 1)].' '.self::PRENOMS[mt_rand(0, count(self::PRENOMS) - 1)];

            if ($clients->contains('nom', $nom)) {
                continue;
            }

            $clients->push(Client::create([
                'nom' => $nom,
                'telephone' => $this->telephone(),
                'adresse' => null,
                'plafond_credit' => mt_rand(0, 2) === 0 ? null : 500000,
                'actif' => true,
            ]));
        }

        return $clients;
    }

    /** Réapprovisionnement chez un fournisseur, en priorité des produits les plus bas. */
    private function creerAchat(Carbon $date, Collection $fournisseurs, Collection $produits): void
    {
        $fournisseur = $fournisseurs->random();
        $candidats = $produits
            ->filter(fn (Produit $produit) => in_array($produit->categorie->nom, $this->categoriesFournies[$fournisseur->id], true))
            ->sortBy(fn (Produit $produit) => (float) $produit->stock_actuel / max(1, (float) $produit->stock_minimum))
            ->take(mt_rand(3, 6));

        Carbon::setTestNow($date);

        DB::transaction(function () use ($date, $fournisseur, $candidats) {
            $lignes = $candidats->map(fn (Produit $produit) => [
                'produit' => $produit,
                'quantite' => (int) $produit->stock_minimum * mt_rand(2, 4),
                'prix_achat' => (float) $produit->prix_achat,
            ]);
            $total = $lignes->sum(fn (array $ligne) => $ligne['quantite'] * $ligne['prix_achat']);

            // Trois achats sur quatre sont réglés au comptant, les autres à moitié (dette fournisseur)
            $montantPaye = mt_rand(1, 4) === 1 ? round($total / 2, -3) : $total;

            $achat = Achat::create([
                'numero' => $this->numero('ACH', $date),
                'fournisseur_id' => $fournisseur->id,
                'utilisateur_id' => Auth::id(),
                'date_achat' => $date,
                'total' => $total,
                'montant_paye' => $montantPaye,
                'reste_a_payer' => $total - $montantPaye,
                'statut' => StatutAchat::Valide,
            ]);

            foreach ($lignes as $ligne) {
                $achat->lignes()->create([
                    'produit_id' => $ligne['produit']->id,
                    'quantite' => $ligne['quantite'],
                    'prix_achat' => $ligne['prix_achat'],
                    'total' => $ligne['quantite'] * $ligne['prix_achat'],
                ]);
                $this->stock->enregistrer($ligne['produit'], TypeMouvementStock::Achat, $ligne['quantite'], $achat);
            }

            $achat->paiements()->create([
                'montant' => $montantPaye,
                'mode' => ModePaiement::from($this->choisir([
                    ModePaiement::Virement->value => 2,
                    ModePaiement::Cheque->value => 1,
                    ModePaiement::Especes->value => 1,
                ])),
                'date_paiement' => $date,
                'utilisateur_id' => Auth::id(),
            ]);
        });
    }

    private function creerVentesDuJour(Carbon $date, Collection $produits, Collection $clients, array $vendeurs): void
    {
        $comptoir = Client::where('nom', Client::COMPTOIR)->firstOrFail();
        $nombre = $date->isSunday() ? mt_rand(1, 2) : mt_rand(3, 7);

        // Heures d'ouverture 8 h – 17 h 30, sans dépasser l'heure actuelle pour aujourd'hui
        $limite = $date->isSameDay($this->maintenant)
            ? min($this->maintenant->getTimestamp(), $date->copy()->setTime(17, 30)->getTimestamp())
            : $date->copy()->setTime(17, 30)->getTimestamp();
        $heures = collect(range(1, $nombre))
            ->map(fn () => mt_rand($date->copy()->setTime(8, 0)->getTimestamp(), max($limite, $date->copy()->setTime(8, 0)->getTimestamp())))
            ->filter(fn (int $horodatage) => $horodatage <= $limite)
            ->sort();

        foreach ($heures as $horodatage) {
            Auth::setUser($vendeurs[mt_rand(0, count($vendeurs) - 1)]);
            $client = mt_rand(1, 10) <= 6 ? $comptoir : $clients->random();
            $this->creerVente(Carbon::createFromTimestamp($horodatage, config('app.timezone')), $client, $produits);
        }
    }

    private function creerVente(Carbon $date, Client $client, Collection $produits): void
    {
        $disponibles = $produits->filter(fn (Produit $produit) => (float) $produit->stock_actuel >= 1);

        if ($disponibles->isEmpty()) {
            return;
        }

        Carbon::setTestNow($date);

        DB::transaction(function () use ($date, $client, $disponibles) {
            $lignes = $disponibles->random(min(mt_rand(1, 4), $disponibles->count()))->map(function (Produit $produit) {
                // Les gros volumes (ciment, fer, agglos) partent par lots plus importants
                $maximum = (float) $produit->stock_minimum >= 30 ? 12 : 4;
                $quantite = min(mt_rand(1, $maximum), (int) floor((float) $produit->stock_actuel));

                return ['produit' => $produit, 'quantite' => $quantite, 'prix' => (float) $produit->prix_vente];
            });
            $total = $lignes->sum(fn (array $ligne) => $ligne['quantite'] * $ligne['prix']);

            // Crédit possible seulement pour un client identifié, dans la limite de son plafond
            $aCredit = ! $client->estComptoir()
                && $client->plafond_credit !== null
                && mt_rand(1, 100) <= 25
                && $client->creance_totale + $total <= (float) $client->plafond_credit;

            $mode = $aCredit
                ? ModePaiement::Credit
                : ModePaiement::from($this->choisir([
                    ModePaiement::Especes->value => 6,
                    ModePaiement::MobileMoney->value => 3,
                    ModePaiement::Virement->value => 1,
                ]));
            $montantPaye = $aCredit ? round($total * mt_rand(0, 5) / 10, -3) : $total;

            $vente = Vente::create([
                'numero' => $this->numero('VTE', $date),
                'client_id' => $client->id,
                'utilisateur_id' => Auth::id(),
                'date_vente' => $date,
                'sous_total' => $total,
                'remise' => 0,
                'total' => $total,
                'montant_paye' => $montantPaye,
                'reste_a_payer' => $total - $montantPaye,
                'mode_paiement' => $mode,
                'statut' => StatutVente::Validee,
            ]);

            foreach ($lignes as $ligne) {
                $vente->lignes()->create([
                    'produit_id' => $ligne['produit']->id,
                    'quantite' => $ligne['quantite'],
                    'prix_unitaire' => $ligne['prix'],
                    'prix_achat_unitaire' => $ligne['produit']->prix_achat,
                    'remise' => 0,
                    'total' => $ligne['quantite'] * $ligne['prix'],
                ]);
                $this->stock->enregistrer($ligne['produit'], TypeMouvementStock::Vente, $ligne['quantite'], $vente);
            }

            if ($montantPaye > 0) {
                $vente->paiements()->create([
                    'montant' => $montantPaye,
                    // L'acompte d'une vente à crédit est versé en espèces
                    'mode' => $aCredit ? ModePaiement::Especes : $mode,
                    'date_paiement' => $date,
                    'utilisateur_id' => Auth::id(),
                ]);
            }

            $vente->facture()->create([
                'numero' => $this->numero('FAC', $date),
                'date_emission' => $date,
                'total' => $total,
                'statut' => StatutFacture::Emise,
            ]);
        });
    }

    private function creerDepensesDuJour(Carbon $date, int $jour, Utilisateur $utilisateur): void
    {
        $depenses = match (true) {
            $jour === 2 => [[CategorieDepense::Loyer, 'Loyer du magasin', 800000, ModePaiement::Virement]],
            $jour === 5 => [[CategorieDepense::Electricite, 'Facture JIRAMA électricité', 185000, ModePaiement::MobileMoney]],
            $jour === 6 => [[CategorieDepense::Eau, 'Facture JIRAMA eau', 42000, ModePaiement::MobileMoney]],
            $jour === 25 => [[CategorieDepense::Salaires, 'Salaires du personnel', 1350000, ModePaiement::Virement]],
            $jour % 7 === 3 => [[CategorieDepense::Transport, 'Transport de marchandises', mt_rand(3, 8) * 10000, ModePaiement::Especes]],
            $jour === 14 => [[CategorieDepense::Entretien, 'Réparation du rideau métallique', 65000, ModePaiement::Especes]],
            $jour === 18 => [[CategorieDepense::Fournitures, 'Rouleaux de papier pour la caisse', 24000, ModePaiement::Especes]],
            default => [],
        };

        foreach ($depenses as [$categorie, $libelle, $montant, $mode]) {
            Depense::create([
                'categorie' => $categorie,
                'libelle' => $libelle,
                'montant' => $montant,
                'date_depense' => $date,
                'mode_paiement' => $mode,
                'utilisateur_id' => $utilisateur->id,
            ]);
        }
    }

    /** Numéro séquentiel PREFIXE-AAAA-NNNNN, remis à zéro chaque année. */
    private function numero(string $prefixe, Carbon $date): string
    {
        $annee = $date->year;
        $this->compteurs[$prefixe][$annee] = ($this->compteurs[$prefixe][$annee] ?? 0) + 1;

        return sprintf('%s-%d-%05d', $prefixe, $annee, $this->compteurs[$prefixe][$annee]);
    }

    private function telephone(): string
    {
        return sprintf('03%d %02d %03d %02d', [2, 3, 4, 8][mt_rand(0, 3)], mt_rand(0, 99), mt_rand(0, 999), mt_rand(0, 99));
    }

    /**
     * Tirage pondéré : ['valeur' => poids, ...].
     *
     * @param  array<string, int>  $poids
     */
    private function choisir(array $poids): string
    {
        $tirage = mt_rand(1, array_sum($poids));

        foreach ($poids as $valeur => $poidsValeur) {
            if (($tirage -= $poidsValeur) <= 0) {
                return $valeur;
            }
        }

        return array_key_first($poids);
    }
}
