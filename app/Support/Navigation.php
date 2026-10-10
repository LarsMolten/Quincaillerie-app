<?php

namespace App\Support;

use App\Models\Utilisateur;
use Illuminate\Support\Facades\Route;

/**
 * Source unique du menu de l'application (cahier des charges) :
 * - le menu latéral et la barre mobile n'affichent que les entrées autorisées ;
 * - les entrées « bientot » sont enregistrées comme routes « Bientôt disponible »
 *   (routes/web.php) jusqu'à l'implémentation du module : il suffit alors de retirer
 *   le drapeau et de déclarer les vraies routes, avec le même nom.
 */
class Navigation
{
    /**
     * @return list<array{titre: ?string, entrees: list<array{libelle: string, icone: string, route: string, url: string, droit: string|list<string>|null, bientot: bool, aussi: list<string>}>}>
     */
    public static function sections(): array
    {
        // aussi : autres routes qui rendent l'entrée active (ex. les unités, gérées depuis « Catégories »)
        // droit : un code, une liste de codes (tous requis) ou null (tout utilisateur connecté)
        $e = fn (string $libelle, string $icone, string $route, string $url, string|array|null $droit, bool $bientot = true, array $aussi = []) => compact('libelle', 'icone', 'route', 'url', 'droit', 'bientot', 'aussi');

        return [
            ['titre' => null, 'entrees' => [
                $e('Tableau de bord', 'layout-dashboard', 'accueil', '/', null, false),
            ]],
            ['titre' => 'Stock', 'entrees' => [
                $e('Produits', 'package', 'produits.index', '/produits', 'produits.voir', false),
                $e('Catégories', 'tags', 'categories.index', '/categories', 'categories.gerer', false, ['unites.*']),
                $e('Entrées', 'download', 'stock.entrees', '/stock/entrees', 'stock.voir', false),
                $e('Sorties', 'upload', 'stock.sorties', '/stock/sorties', 'stock.voir', false),
                $e('Mouvements', 'history', 'stock.mouvements', '/stock/mouvements', 'stock.voir', false),
                $e('Inventaire', 'clipboard-list', 'inventaires.index', '/inventaires', 'inventaires.gerer', false),
            ]],
            ['titre' => 'Ventes', 'entrees' => [
                $e('Nouvelle vente', 'shopping-cart', 'ventes.create', '/ventes/nouvelle', 'ventes.creer', false),
                $e('Liste des ventes', 'receipt', 'ventes.index', '/ventes', 'ventes.voir', false),
                $e('Factures', 'file-text', 'factures.index', '/factures', 'factures.voir', false),
                $e('Retours clients', 'undo-2', 'retours.clients', '/retours/clients', 'retours.gerer', false),
            ]],
            ['titre' => 'Achats', 'entrees' => [
                $e('Nouvel achat', 'circle-plus', 'achats.create', '/achats/nouveau', 'achats.creer', false),
                $e('Liste des achats', 'truck', 'achats.index', '/achats', 'achats.voir', false),
                $e('Fournisseurs', 'store', 'fournisseurs.index', '/fournisseurs', 'fournisseurs.gerer', false),
                $e('Retours fournisseurs', 'undo-2', 'retours.fournisseurs', '/retours/fournisseurs', 'retours.gerer', false),
            ]],
            ['titre' => 'Clients', 'entrees' => [
                $e('Clients', 'users', 'clients.index', '/clients', 'clients.gerer', false),
                $e('Historique', 'history', 'clients.historique', '/clients/historique', 'clients.gerer'),
            ]],
            ['titre' => 'Finances', 'entrees' => [
                $e('Paiements', 'credit-card', 'paiements.index', '/paiements', 'paiements.gerer', false),
                $e('Créances', 'hand-coins', 'creances.index', '/creances', 'paiements.gerer', false),
                $e('Dettes fournisseurs', 'banknote', 'dettes.index', '/dettes', ['paiements.gerer', 'achats.voir'], false),
                $e('Dépenses', 'wallet', 'depenses.index', '/depenses', 'depenses.gerer', false),
                $e('Résultats', 'chart-line', 'resultats.index', '/resultats', 'finances.voir'),
            ]],
            ['titre' => 'Rapports', 'entrees' => [
                $e('Ventes', 'chart-column', 'rapports.ventes', '/rapports/ventes', 'rapports.voir'),
                $e('Achats', 'chart-column', 'rapports.achats', '/rapports/achats', 'rapports.voir'),
                $e('Stock', 'boxes', 'rapports.stock', '/rapports/stock', 'rapports.voir'),
                $e('Finances', 'chart-line', 'rapports.finances', '/rapports/finances', 'finances.voir'),
            ]],
            ['titre' => 'Administration', 'entrees' => [
                $e('Utilisateurs', 'users', 'utilisateurs.index', '/utilisateurs', 'utilisateurs.gerer'),
                $e('Rôles et droits', 'shield', 'roles.index', '/roles', 'roles.gerer'),
                $e('Paramètres', 'settings', 'parametres.index', '/parametres', 'parametres.gerer'),
                $e('Journal d\'activité', 'scroll-text', 'journal.index', '/journal', 'journal.voir'),
            ]],
        ];
    }

    /**
     * Sections visibles par l'utilisateur, avec l'entrée active repérée (sections vides retirées).
     *
     * @return list<array{titre: ?string, entrees: list<array<string, mixed>>}>
     */
    public static function pour(?Utilisateur $utilisateur): array
    {
        return collect(self::sections())
            ->map(fn (array $section) => [
                'titre' => $section['titre'],
                'entrees' => collect($section['entrees'])
                    ->filter(fn (array $entree) => self::autorisee($utilisateur, $entree))
                    ->map(fn (array $entree) => [...$entree, 'active' => self::estActive($entree)])
                    ->values()
                    ->all(),
            ])
            ->filter(fn (array $section) => $section['entrees'] !== [])
            ->values()
            ->all();
    }

    /**
     * Les 4 actions de la barre inférieure mobile (« Menu » ouvre le tiroir).
     *
     * @return list<array<string, mixed>>
     */
    public static function mobile(?Utilisateur $utilisateur): array
    {
        $entrees = collect(self::sections())->pluck('entrees')->flatten(1)->keyBy('route');

        return collect([
            [...$entrees['accueil'], 'libelle' => 'Accueil', 'icone' => 'house'],
            [...$entrees['ventes.create'], 'libelle' => 'Vente'],
            $entrees['produits.index'],
        ])
            ->filter(fn (array $entree) => self::autorisee($utilisateur, $entree))
            ->map(fn (array $entree) => [...$entree, 'active' => self::estActive($entree)])
            ->values()
            ->all();
    }

    /** Entrée correspondant à la route courante (titre et fil d'Ariane des pages). */
    public static function courante(): ?array
    {
        foreach (self::sections() as $section) {
            foreach ($section['entrees'] as $entree) {
                if (Route::currentRouteName() === $entree['route']) {
                    return [...$entree, 'section' => $section['titre']];
                }
            }
        }

        return null;
    }

    /** @return list<array<string, mixed>> */
    public static function aVenir(): array
    {
        return collect(self::sections())->pluck('entrees')->flatten(1)->where('bientot', true)->values()->all();
    }

    private static function autorisee(?Utilisateur $utilisateur, array $entree): bool
    {
        if ($entree['droit'] === null) {
            return $utilisateur !== null;
        }

        return $utilisateur !== null && collect((array) $entree['droit'])->every(fn (string $code) => $utilisateur->can($code));
    }

    /**
     * Active sur sa route, et sur les sous-pages de son module (ex. produits.edit pour produits.index)
     * tant que la page courante n'est pas elle-même une autre entrée du menu (ventes.create ≠ ventes.index).
     */
    private static function estActive(array $entree): bool
    {
        $route = $entree['route'];
        $courante = Route::currentRouteName();

        if ($courante === $route || ($entree['aussi'] && request()->routeIs(...$entree['aussi']))) {
            return true;
        }

        $routesDuMenu = collect(self::sections())->pluck('entrees')->flatten(1)->pluck('route');

        return str_ends_with($route, '.index')
            && request()->routeIs(substr($route, 0, -6).'.*')
            && ! $routesDuMenu->contains($courante);
    }
}
