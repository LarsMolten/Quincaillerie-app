# CLAUDE.md — Application de gestion de quincaillerie

Ce fichier est lu automatiquement par Claude Code au début de chaque session. Il décrit le projet, les conventions et les règles à respecter. En cas de doute, ces règles priment sur les habitudes par défaut.

## 1. Présentation du projet

Application web de gestion d'une quincaillerie : produits, catégories, fournisseurs, clients, achats, ventes (caisse), stock, retours, paiements, factures, dépenses, utilisateurs, rapports.

- **Langue de l'interface** : français uniquement.
- **Monnaie** : Ariary (Ar), sans décimales à l'affichage.
- **Fuseau horaire** : Indian/Antananarivo.
- **Utilisateurs** : Administrateur, Responsable, Vendeur/Caissier, Magasinier.

## 2. Stack technique

- Laravel 13, PHP 8.3+
- MySQL (utf8mb4)
- Blade avec composants (`resources/views/components`)
- Tailwind CSS v4 (configuration CSS-first avec `@theme`), Vite
- Alpine.js (pas de framework JS lourd)
- Chart.js (graphiques), Lucide (icônes SVG en ligne)
- barryvdh/laravel-dompdf (PDF), maatwebsite/excel (Excel), picqer/php-barcode-generator (codes-barres)
- Tests : Pest ou PHPUnit

## 3. Commandes utiles

```
composer install && npm install        # installation
cp .env.example .env && php artisan key:generate
php artisan migrate:fresh --seed       # base propre avec données de base
php artisan db:seed --class=DemoSeeder # données de démonstration
php artisan serve                      # serveur local
npm run dev                            # front en développement
npm run build                          # build de production
php artisan test                       # tests
npm run test:js                        # tests des composants JS (happy-dom)
php artisan stock:verifier             # contrôle de cohérence du stock
```

## 4. Règles de nommage (OBLIGATOIRES)

### Base de données
Tables **en français**, minuscules, **sans accents**, au pluriel, avec underscores.

Liste officielle : `utilisateurs`, `roles`, `droits`, `role_droit`, `categories`, `unites`, `produits`, `fournisseurs`, `clients`, `achats`, `lignes_achat`, `ventes`, `lignes_vente`, `factures`, `paiements`, `mouvements_stock`, `inventaires`, `lignes_inventaire`, `depenses`, `retours`, `lignes_retour`, `parametres`, `journal_activites`.

Ne jamais créer de table en anglais. Toute nouvelle table suit la même convention (lignes de détail préfixées par `lignes_`).

### Modèles Eloquent
Français, au singulier : `Utilisateur`, `Role`, `Droit`, `Categorie`, `Unite`, `Produit`, `Fournisseur`, `Client`, `Achat`, `LigneAchat`, `Vente`, `LigneVente`, `Facture`, `Paiement`, `MouvementStock`, `Inventaire`, `LigneInventaire`, `Depense`, `Retour`, `LigneRetour`, `Parametre`, `JournalActivite`.

- Chaque modèle déclare explicitement `protected $table`.
- Les clés étrangères sont nommées explicitement : `produit_id`, `client_id`, `fournisseur_id`, `utilisateur_id`, `categorie_id`, `unite_id`, `role_id`, `achat_id`, `vente_id`, etc.

### Code
Colonnes, variables, méthodes, routes, contrôleurs et vues en français (ex. `ProduitController`, route `produits.index`, vue `produits/index.blade.php`). Seuls les mots techniques du framework restent en anglais.

## 5. Règles métier critiques

1. **Le stock n'est JAMAIS modifié directement.** Seul `App\Services\MouvementStockService` peut changer `produits.stock_actuel`, en créant un enregistrement dans `mouvements_stock`. Interdit : `$produit->stock_actuel = ...`, `increment()`, `decrement()` ou SQL direct sur ce champ ailleurs que dans ce service.
2. **Toute opération multi-tables** (achat, vente, retour, inventaire, paiement) s'exécute dans `DB::transaction` et passe par une classe de service dans `app/Services`.
3. **Verrouillage** : le service de stock utilise `lockForUpdate()` pour éviter les ventes simultanées sur le même stock.
4. **Stock négatif interdit**, sauf paramètre explicite. Message d'erreur en français.
5. **Pas de suppression définitive** des données métier : `SoftDeletes` ou champ `actif`.
6. **Documents** : une facture validée n'est jamais modifiable ; correction par annulation ou retour. Numérotation séquentielle sans trou (`FAC-AAAA-NNNNN`, `VTE-AAAA-NNNNN`, `ACH-AAAA-NNNNN`), remise à zéro chaque année, générée dans la transaction avec verrouillage.
7. **Bénéfice** = ventes − coût d'achat des produits vendus (`lignes_vente.prix_achat_unitaire`) − dépenses de la période.
8. **Crédit** : interdit pour le « Client comptoir » ; refusé si le plafond de crédit du client est dépassé.
9. **Remises** : seulement avec le droit `ventes.remise`, dans la limite du plafond paramétré.
10. **Journalisation** : annulations, remises, ajustements de stock, suppressions et connexions sont enregistrés dans `journal_activites`.

## 6. Droits et sécurité

- Droits de la forme `module.action` (ex. `ventes.creer`, `stock.ajuster`, `finances.voir`).
- Middleware `droit:code`, directive Blade `@droit('code')`, `Gate::before` donnant tout à l'Administrateur.
- Validation par classes `FormRequest` en français.
- Toutes les routes sont protégées par authentification ET par droit.
- Mass-assignment contrôlé (`$fillable`), échappement Blade, protection CSRF, limitation des tentatives de connexion.
- Ne jamais committer `.env` ni de mot de passe réel.

## 7. Thème visuel « moderne 2026 »

À respecter dans TOUTES les vues :

- **Style** : SaaS moderne épuré, coins arrondis (`rounded-xl` à `rounded-2xl`), ombres douces, bordures fines, effet verre dépoli (`backdrop-blur`) uniquement sur l'en-tête collant, les modales et les menus.
- **Couleurs** : jetons sémantiques OKLCH définis dans `@theme` (`--color-fond`, `--color-surface`, `--color-texte`, `--color-texte-doux`, `--color-bordure`, `--color-primaire`, `--color-succes`, `--color-alerte`, `--color-danger`, `--color-info`). Accent **orange sécurité**. **Jamais de couleur codée en dur dans une vue** : uniquement les jetons.
- **Modes clair ET sombre** : automatique (système) + bascule mémorisée, sans flash au chargement. Tout composant est vérifié dans les deux modes.
- **Typographie** : Inter variable hébergée en local ; classe `.chiffres` (tabular-nums) sur tous les montants et quantités.
- **Icônes** : composant `<x-icone nom="..." />` (Lucide).
- **Mise en page** : tableau de bord en « bento grid », barre latérale repliable, tableaux à en-tête collant, badges de statut en pilule, actions dans un menu « ⋯ ».
- **Mouvement** : micro-animations de 150 à 200 ms, View Transitions API si disponible, tout désactivé avec `prefers-reduced-motion`.
- **Retours visuels** : toasts (jamais `alert()` ni `confirm()` natifs), squelettes de chargement, états vides avec icône et bouton d'action, boutons avec état « chargement » contre les doubles clics.
- **Navigation rapide** : palette de commandes (Ctrl+K), raccourcis clavier à la caisse (F2 recherche, F4 client, F8 remise, F9 paiement, Échap annuler, Ctrl+Entrée valider).
- **Accessibilité** : WCAG 2.2 AA (contraste ≥ 4,5:1), focus visible, labels associés, rôles ARIA, cibles tactiles ≥ 44 px.
- **Responsive** : mobile-first ; tableaux en cartes sous 768 px ; écran de caisse excellent sur tablette ; PWA installable.
- **Réseau limité** : aucun CDN, polices et scripts hébergés en local, images en lazy loading.
- **PDF (DomPDF)** : pas de flexbox, grid ni OKLCH → tables HTML et couleurs hexadécimales, avec la même identité (accent orange, typographie sobre).

Utiliser en priorité les composants existants du design system (`x-bouton`, `x-champ`, `x-carte-stat`, `x-badge`, `x-tableau`, `x-modal`, `x-toast`, `x-squelette`, `x-etat-vide`, `x-entete-page`…) plutôt que de recréer du HTML. La page `/design-systeme` (environnement local) les présente tous.

## 8. Structure du projet

```
app/
  Enums/          statuts, types de mouvement, modes de paiement (libelle() et couleur())
  Models/         modèles français
  Services/       MouvementStockService, AchatService, VenteService, PaiementService,
                  RetourService, InventaireService, FactureService
  Rapports/       une classe de requête par rapport
  Http/
    Controllers/  contrôleurs français
    Requests/     FormRequest en français
    Middleware/   droit
resources/
  css/app.css     @theme, jetons, mode sombre
  views/
    components/   design system
    layouts/      app.blade.php
    <module>/     une vue par module (produits, ventes, achats…)
database/
  migrations/  seeders/  factories/
tests/
  Unit/  Feature/
```

## 9. Méthode de travail

- Suivre l'ordre du plan de réalisation (prompts 0 à 25) : fondations → design system → accès → données de base → cœur métier → pilotage → administration → qualité → déploiement.
- Avant de coder une fonctionnalité qui touche la base de données : proposer d'abord les changements (noms français cohérents), attendre validation si c'est un ajout non prévu.
- Chaque fonctionnalité est livrée **avec ses tests**. Lancer `php artisan test` avant de déclarer une tâche terminée.
- Vérifier visuellement les pages en clair et sombre, sur mobile et PC.
- Faire un commit Git par fonctionnalité, message en français à l'impératif (ex. « Ajoute le module Produits »).
- Ne pas modifier une migration déjà exécutée en production : créer une nouvelle migration.
- Ne pas ajouter de dépendance sans la justifier.
- Code PSR-12, commentaires en français, pas de code mort ni de `dd()` / `dump()` oubliés.
- Si une demande contredit une règle de ce fichier (surtout §5), le signaler avant d'agir.

## 10. Données de démonstration

- Administrateur par défaut : `admin@quincaillerie.test` (mot de passe défini dans `.env`).
- `DemoSeeder` : ~60 produits réalistes en Ariary, 10 fournisseurs, 30 clients, ventes et achats des 30 derniers jours. Réservé à l'environnement local.
- Le client « Client comptoir » existe toujours et ne peut pas être supprimé.
