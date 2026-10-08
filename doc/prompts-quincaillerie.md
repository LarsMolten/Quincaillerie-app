# Prompts pour réaliser l'application de gestion de quincaillerie, de A à Z (version thème moderne 2026)

**Stack** : Laravel 11, Blade (composants), Tailwind CSS v4, Alpine.js, MySQL. Interface en français, monnaie en Ariary (Ar).
**Outil conseillé** : Claude Code (ou tout assistant de code travaillant dans votre dossier de projet).

## Le thème 2026 en un coup d'œil

| Élément | Choix |
|---|---|
| Style général | Interface épurée « SaaS moderne » : grands espaces, coins très arrondis, ombres douces, bordures fines |
| Couleurs | Neutres ardoise + accent **orange sécurité** (clin d'œil à la quincaillerie), couleurs définies en OKLCH dans des variables CSS |
| Modes | Clair et sombre (suivi automatique du système + bouton pour changer) |
| Typographie | Inter variable, hébergée en local ; chiffres tabulaires pour les montants |
| Mise en page | Tableau de bord en « bento grid », barre latérale repliable, en-tête translucide |
| Navigation | Palette de commandes (Ctrl + K), raccourcis clavier à la caisse |
| Icônes | Lucide (SVG en ligne) |
| Retours visuels | Notifications « toast », squelettes de chargement, états vides illustrés, micro-animations discrètes |
| Accessibilité | WCAG 2.2 AA, focus visible, cibles tactiles de 44 px minimum, respect de « réduire les animations » |
| Mobile | Responsive et installable (PWA), pensé pour la tablette de caisse |
| Réseau | Aucun CDN : polices et scripts hébergés en local (connexion souvent limitée) |

## Mode d'emploi

1. Collez d'abord le **Prompt 0** (contexte global, thème inclus). Si l'assistant n'a pas de mémoire entre sessions, enregistrez-le dans un fichier `CLAUDE.md` à la racine du projet.
2. Exécutez ensuite les prompts **dans l'ordre**, un par un.
3. Après chaque prompt : lancez `php artisan migrate`, `php artisan test`, testez dans le navigateur (clair **et** sombre, PC **et** mobile), puis faites un `git commit`.
4. Ne passez au prompt suivant que si le précédent fonctionne.

Chaque prompt est dans un bloc à copier-coller.

---

## Phase 0 — Contexte

### Prompt 0 — Contexte global, conventions et thème

````
Tu es un développeur Laravel senior doublé d'un designer d'interface. Nous réalisons une application web de gestion de quincaillerie (produits, catégories, fournisseurs, clients, achats, ventes, stock, retours, paiements, factures, dépenses, utilisateurs, rapports).

STACK : Laravel 11, PHP 8.2+, Blade avec composants (resources/views/components), Tailwind CSS v4 (configuration CSS-first avec @theme), MySQL, Alpine.js pour l'interactivité légère. Pas de framework JS lourd.

CONVENTIONS TECHNIQUES OBLIGATOIRES POUR TOUT LE PROJET :
1. Interface 100 % en français (libellés, messages de validation, messages flash, emails). Locale = fr, fuseau horaire = Indian/Antananarivo.
2. Monnaie : Ariary (Ar), sans décimales à l'affichage, séparateur de milliers = espace insécable (ex : 35 000 Ar). Crée un helper global format_ar($montant).
3. Les TABLES de la base de données sont en FRANÇAIS, minuscules, sans accents, au pluriel, avec underscores. Liste officielle : utilisateurs, roles, droits, role_droit, categories, unites, produits, fournisseurs, clients, achats, lignes_achat, ventes, lignes_vente, factures, paiements, mouvements_stock, inventaires, lignes_inventaire, depenses, retours, lignes_retour, parametres, journal_activites.
4. Les modèles Eloquent sont en français au singulier (Utilisateur, Role, Droit, Categorie, Unite, Produit, Fournisseur, Client, Achat, LigneAchat, Vente, LigneVente, Facture, Paiement, MouvementStock, Inventaire, LigneInventaire, Depense, Retour, LigneRetour, Parametre, JournalActivite). Chaque modèle déclare explicitement protected $table. Les clés étrangères sont nommées explicitement (produit_id, client_id, fournisseur_id, utilisateur_id, categorie_id, unite_id, role_id, achat_id, vente_id, etc.).
5. Noms de colonnes, variables, méthodes, routes, contrôleurs et vues en français (ex : ProduitController, route produits.index, vue produits/index.blade.php). Les mots techniques du framework restent en anglais.
6. Toute opération qui touche plusieurs tables (achat, vente, retour, inventaire, paiement) s'exécute dans une transaction DB (DB::transaction) et passe par une classe de service dans app/Services.
7. Le stock n'est JAMAIS modifié directement : seul le service MouvementStockService peut le faire, en créant un enregistrement dans mouvements_stock.
8. Validation avec des classes FormRequest en français. Autorisation avec des Policies/Gates basées sur les droits de l'utilisateur.
9. Pas de suppression définitive des données métier : utiliser SoftDeletes ou un champ actif.
10. Code propre, commenté en français, respectant PSR-12. Chaque fonctionnalité livrée avec ses tests (Pest ou PHPUnit).

THÈME VISUEL « MODERNE 2026 » (À RESPECTER DANS TOUTES LES VUES) :
A. Style : interface épurée de type SaaS moderne ; beaucoup d'espace blanc ; coins arrondis (rounded-xl à rounded-2xl) ; ombres douces à plusieurs couches ; bordures fines à faible contraste ; effet verre dépoli (backdrop-blur) uniquement sur l'en-tête collant, les modales et les menus déroulants.
B. Couleurs : définies comme variables CSS en OKLCH dans @theme (jetons sémantiques : --color-fond, --color-surface, --color-texte, --color-texte-doux, --color-bordure, --color-primaire, --color-succes, --color-alerte, --color-danger, --color-info). Neutres ardoise ; accent primaire "orange sécurité" ; jamais de couleur codée en dur dans les vues, uniquement les jetons.
C. Modes clair ET sombre : suivent le système par défaut, avec un bouton de bascule (clair / sombre / auto) mémorisé dans localStorage ; aucun flash blanc au chargement. Chaque composant doit être vérifié dans les deux modes.
D. Typographie : Inter variable hébergée en local (paquet @fontsource-variable/inter), échelle typographique cohérente ; font-variant-numeric: tabular-nums sur tous les montants et quantités.
E. Icônes : Lucide, en SVG en ligne via un composant <x-icone nom="..." />.
F. Mise en page : tableau de bord en "bento grid" (cartes de tailles variées) ; barre latérale repliable ; en-tête collant translucide ; tableaux de données avec en-tête collant, survol de ligne, badges de statut en forme de pilule, actions dans un menu déroulant.
G. Mouvement : micro-animations de 150 à 200 ms (survol, ouverture de modale, apparition des toasts) ; transitions de page via la View Transitions API quand elle est disponible ; tout désactivé si prefers-reduced-motion.
H. Retours visuels : notifications "toast" (jamais d'alert()), squelettes de chargement (skeletons), états vides avec icône et bouton d'action, boutons avec état "chargement" pour éviter les doubles clics.
I. Navigation rapide : palette de commandes (Ctrl+K) et raccourcis clavier ; à la caisse, tout doit être faisable au clavier.
J. Accessibilité : WCAG 2.2 AA (contraste ≥ 4,5:1 dans les deux modes), focus visible, labels associés aux champs, rôles ARIA sur modales et menus, cibles tactiles ≥ 44 px.
K. Responsive : conçu mobile-first ; tableaux qui se transforment en cartes sur mobile ; l'écran de caisse doit être excellent sur tablette. Application installable (PWA).
L. Performance et réseau limité : aucun CDN, aucun fichier externe ; images optimisées en lazy loading ; JavaScript minimal.
M. Documents PDF (factures, rapports) : DomPDF ne gère pas flexbox, grid ni OKLCH ; utiliser des tables HTML et des couleurs hexadécimales, tout en gardant la même identité visuelle (accent orange, typographie sobre).

Confirme que tu as compris ces conventions et ce thème, et résume-les en 15 lignes. N'écris pas encore de code.
````

---

## Phase 1 — Fondations

### Prompt 1 — Installation et configuration

````
Initialise le projet :
1. Crée un projet Laravel 11 nommé "quincaillerie".
2. Configure .env pour MySQL (base "quincaillerie", utf8mb4), APP_LOCALE=fr, APP_FALLBACK_LOCALE=fr, APP_TIMEZONE=Indian/Antananarivo, APP_NAME="Quincaillerie".
3. Installe et configure Tailwind CSS v4 avec Vite (plugin @tailwindcss/vite, configuration CSS-first dans resources/css/app.css), ainsi que Alpine.js.
4. Installe les dépendances front : @fontsource-variable/inter (police locale), lucide (icônes SVG), chart.js. Aucune ressource externe via CDN.
5. Installe les packages PHP : barryvdh/laravel-dompdf (PDF), maatwebsite/excel (export Excel), picqer/php-barcode-generator (codes-barres), laravel-lang/lang (traductions françaises de validation, auth, pagination).
6. Applique les traductions françaises (validation, auth, passwords, pagination).
7. Crée le helper app/helpers.php (chargé via composer autoload "files") avec format_ar($montant) qui renvoie par exemple "35 000 Ar" (espace insécable).
8. Crée un fichier README.md décrivant l'installation du projet.
9. Initialise Git avec un .gitignore correct.

Vérifie que "npm run build" et "php artisan serve" fonctionnent, puis liste ce que tu as fait.
````

### Prompt 2 — Migrations (tables de référence)

````
Crée les migrations des tables de référence, dans cet ordre, avec clés étrangères, index et contraintes :

- roles : id, nom (unique), description (nullable), timestamps.
- droits : id, code (unique, ex : "ventes.creer"), libelle, module, timestamps.
- role_droit : role_id, droit_id (clé primaire composite, suppression en cascade).
- utilisateurs : id, nom, email (unique), telephone (nullable), password, role_id (FK roles), actif (boolean, défaut true), preference_theme (clair, sombre, auto ; défaut auto), remember_token, timestamps, softDeletes. (Remplace la table users par défaut de Laravel : adapte aussi password_reset_tokens et sessions si nécessaire, et la config auth.)
- categories : id, nom (unique), description (nullable), actif (boolean), timestamps.
- unites : id, nom, abreviation (ex : pièce/pce, kilogramme/kg, mètre/m, litre/L, sac, pot, carton), timestamps.
- produits : id, reference (unique), code_barres (unique, nullable), nom, description (nullable), image (nullable), categorie_id (FK), unite_id (FK), prix_achat decimal(12,2), prix_vente decimal(12,2), prix_gros decimal(12,2) nullable, stock_actuel decimal(12,3) défaut 0, stock_minimum decimal(12,3) défaut 0, actif (boolean), timestamps, softDeletes. Index sur nom, reference, code_barres.
- fournisseurs : id, nom, contact (nullable), telephone, email (nullable), adresse (nullable), actif, timestamps, softDeletes.
- clients : id, nom, telephone (nullable), email (nullable), adresse (nullable), plafond_credit decimal(12,2) nullable, actif, timestamps, softDeletes.
- parametres : id, cle (unique), valeur (text, nullable), timestamps.

Respecte strictement les noms français. Lance php artisan migrate:fresh pour vérifier.
````

### Prompt 3 — Migrations (tables de transactions)

````
Crée les migrations des tables de transactions :

- achats : id, numero (unique, format ACH-AAAA-NNNNN), fournisseur_id, utilisateur_id, date_achat, total decimal(12,2), montant_paye decimal(12,2) défaut 0, reste_a_payer decimal(12,2), statut (valide, annule), notes nullable, timestamps.
- lignes_achat : id, achat_id (cascade), produit_id, quantite decimal(12,3), prix_achat decimal(12,2), total decimal(12,2).
- ventes : id, numero (unique, format VTE-AAAA-NNNNN), client_id nullable, utilisateur_id, date_vente, sous_total, remise, total, montant_paye, reste_a_payer (tous decimal(12,2)), mode_paiement (especes, mobile_money, virement, cheque, credit), statut (validee, annulee), notes nullable, timestamps.
- lignes_vente : id, vente_id (cascade), produit_id, quantite decimal(12,3), prix_unitaire, prix_achat_unitaire (copie du prix d'achat au moment de la vente, pour calculer le bénéfice), remise decimal(12,2) défaut 0, total.
- factures : id, numero (unique, FAC-AAAA-NNNNN), vente_id (unique), date_emission, total, statut (emise, annulee), timestamps.
- paiements : id, payable_type et payable_id (relation polymorphe vers ventes ou achats), montant decimal(12,2), mode (especes, mobile_money, virement, cheque), reference nullable, date_paiement, utilisateur_id, timestamps.
- mouvements_stock : id, produit_id, type (achat, vente, retour_client, retour_fournisseur, ajustement_positif, ajustement_negatif, perte), sens (entree, sortie), quantite decimal(12,3), stock_avant, stock_apres, utilisateur_id, motif nullable, reference_type et reference_id nullables (morph), created_at. Index sur (produit_id, created_at).
- inventaires : id, numero, date_inventaire, statut (en_cours, valide), utilisateur_id, notes nullable, timestamps.
- lignes_inventaire : id, inventaire_id (cascade), produit_id, stock_theorique, stock_compte, ecart.
- depenses : id, categorie (salaires, transport, electricite, eau, loyer, entretien, fournitures, autres), libelle, montant decimal(12,2), date_depense, mode_paiement, utilisateur_id, notes nullable, timestamps.
- retours : id, numero, type (client, fournisseur), vente_id nullable, achat_id nullable, utilisateur_id, date_retour, total, motif, statut (valide, annule), timestamps.
- lignes_retour : id, retour_id (cascade), produit_id, quantite, prix_unitaire, total.
- journal_activites : id, utilisateur_id nullable, action, modele nullable, modele_id nullable, details (json nullable), adresse_ip nullable, created_at.

Utilise des enums PHP 8.1 (app/Enums) pour les statuts, types et modes de paiement. Chaque enum expose des méthodes libelle() (texte français) et couleur() (nom du jeton de couleur sémantique : succes, alerte, danger, info, neutre) qui serviront aux badges de statut du thème. Lance migrate:fresh et corrige toute erreur.
````

### Prompt 4 — Modèles et relations

````
Crée tous les modèles Eloquent français listés dans les conventions. Pour chacun :
- protected $table explicite, $fillable, $casts (dates, décimaux, enums) ;
- toutes les relations avec clés étrangères explicites : Categorie hasMany Produit ; Produit belongsTo Categorie et Unite, hasMany LigneAchat, LigneVente, MouvementStock ; Fournisseur hasMany Achat ; Client hasMany Vente ; Vente hasMany LigneVente, hasOne Facture, morphMany Paiement ; Achat hasMany LigneAchat, morphMany Paiement ; Retour hasMany LigneRetour ; Inventaire hasMany LigneInventaire ; Role belongsToMany Droit via role_droit, hasMany Utilisateur ; Utilisateur belongsTo Role ; MouvementStock morphTo reference ; etc.
- Utilisateur doit étendre Authenticatable et fournir une méthode aDroit(string $code): bool.
- Produit : scopes actif(), enRupture(), stockFaible(), recherche($terme) (nom, référence, code-barres) ; accesseur etatStock (normal, faible, rupture) utilisé par les badges colorés.
- Vente et Achat : accesseur estSoldee.
- Client : accesseur creanceTotale (somme des reste_a_payer des ventes validées). Fournisseur : accesseur detteTotale.
Crée les factories correspondantes pour les tests.
````

### Prompt 5 — Seeders

````
Crée les seeders :
1. RoleSeeder : Administrateur, Responsable, Vendeur/Caissier, Magasinier.
2. DroitSeeder : crée les droits par module avec des codes de la forme "module.action" (produits.voir/creer/modifier/desactiver, categories.gerer, fournisseurs.gerer, clients.gerer, achats.voir/creer/annuler, ventes.voir/creer/annuler, ventes.remise, stock.voir, stock.ajuster, inventaires.gerer, retours.gerer, paiements.gerer, factures.voir, depenses.gerer, rapports.voir, finances.voir, utilisateurs.gerer, roles.gerer, parametres.gerer, journal.voir).
3. Association rôles-droits : Administrateur = tous ; Responsable = tout sauf utilisateurs, rôles, paramètres ; Vendeur/Caissier = ventes.voir/creer, clients.gerer, factures.voir, paiements.gerer, produits.voir ; Magasinier = produits.*, stock.*, inventaires.gerer.
4. Un administrateur par défaut (email admin@quincaillerie.test, mot de passe à définir dans .env).
5. Les 10 catégories de la liste du cahier des charges, des unités courantes, et les paramètres par défaut (nom_entreprise, adresse, telephone, email, nif_stat, taux_tva = 0, prefixe_facture = FAC, pied_de_facture, couleur_accent = orange).
6. Un DemoSeeder séparé (activable) avec environ 60 produits réalistes de quincaillerie (ciment, tuyaux PVC, robinets, peinture, câbles, vis, cadenas, etc.), 10 fournisseurs, 30 clients, en prix réalistes en Ariary, et quelques ventes et achats des 30 derniers jours pour que le tableau de bord ait de belles données de démonstration.
````

---

## Phase 2 — Design system, accès et interface

### Prompt 6 — Design system « moderne 2026 »

````
Crée le design system complet de l'application, en respectant le thème A à M du contexte.

1. resources/css/app.css (Tailwind v4) :
   - @import "tailwindcss" ; police Inter variable importée localement ;
   - bloc @theme avec tous les jetons de couleur en OKLCH (fond, surface, surface-élevée, texte, texte-doux, bordure, primaire + variantes 50 à 900 d'un orange sécurité, succès, alerte, danger, info), les rayons (--radius-carte: 1rem, --radius-controle: 0.75rem), les ombres douces à deux couches, l'échelle d'espacement ;
   - variante sombre via @custom-variant dark (classe .dark sur <html>) avec toutes les valeurs de jetons redéfinies pour le mode sombre (fond quasi noir bleuté, surfaces légèrement plus claires, accent orange un peu plus lumineux, contrastes AA respectés) ;
   - classes utilitaires : .chiffres (tabular-nums), .verre (backdrop-blur + fond translucide), .focus-anneau (anneau de focus visible), animations personnalisées (apparition, glissement, squelette pulsé) désactivées sous prefers-reduced-motion.
2. Script de thème : placé dans le <head> avant le rendu pour appliquer la classe dark sans flash ; composant Alpine "theme" (clair / sombre / auto) mémorisé dans localStorage et, une fois connecté, enregistré dans utilisateurs.preference_theme.
3. Composants Blade (resources/views/components) tous compatibles clair/sombre, accessibles et documentés :
   - x-icone (Lucide en SVG en ligne) ;
   - x-bouton (variantes : primaire, secondaire, fantome, danger ; tailles ; état chargement avec spinner ; icône optionnelle) ;
   - x-champ, x-select, x-textarea, x-case, x-interrupteur (avec label, aide, erreur, préfixe/suffixe comme "Ar") ;
   - x-carte, x-carte-stat (valeur, libellé, tendance en pourcentage avec flèche, mini-graphique optionnel) ;
   - x-badge (pilule, couleur selon les jetons sémantiques, point coloré) ;
   - x-tableau (en-tête collant, survol, tri, pagination, transformation en cartes sous 768 px) et x-menu-actions (menu déroulant "⋯") ;
   - x-modal (verre dépoli, fermeture Échap, piégeage du focus) et x-confirmation (modale de confirmation de suppression/annulation) ;
   - x-toast (système global : Alpine store + écouteur d'événements ; types succès/erreur/info ; auto-fermeture ; empilement) branché sur les messages flash de Laravel ;
   - x-squelette (skeleton de carte, de ligne de tableau, de texte) ;
   - x-etat-vide (icône, titre, texte, bouton d'action) ;
   - x-entete-page (titre, fil d'Ariane, boutons d'action) ;
   - x-recherche (champ avec icône et raccourci clavier affiché).
4. Une page /design-systeme (réservée à l'environnement local) qui affiche tous les composants dans les deux modes, pour validation visuelle.
5. Vérifie les contrastes AA dans les deux modes et la navigation clavier de chaque composant interactif.
````

### Prompt 7 — Authentification, rôles et droits

````
Implémente l'authentification (sans Breeze/Jetstream, ou avec Breeze Blade adapté) pour la table utilisateurs :
- page de connexion moderne en écran partagé : à gauche un panneau de marque (dégradé orange discret, logo, slogan, motif géométrique léger), à droite un formulaire épuré dans une carte ; œil pour afficher/masquer le mot de passe ; compatible clair/sombre ; entièrement responsive (le panneau de marque disparaît sur mobile) ;
- déconnexion, "mot de passe oublié" optionnel ;
- refus de connexion si l'utilisateur est inactif, avec message clair en toast ;
- middleware "droit:code" qui vérifie $user->aDroit($code) et renvoie une page 403 en français au design du thème ;
- directive Blade @droit('code') ... @enddroit ;
- Gate::before pour donner tous les droits à l'Administrateur ;
- limitation des tentatives de connexion (throttle) ;
- tests : un vendeur ne peut pas accéder aux utilisateurs ; un utilisateur inactif ne peut pas se connecter.
````

### Prompt 8 — Layout et navigation

````
Construis le layout principal avec les composants du design system :
1. resources/views/layouts/app.blade.php : barre latérale à gauche (icônes Lucide + libellés, repliable en mode "icônes seules", mémorisé ; sur mobile, tiroir avec fond assombri) ; en-tête collant en verre dépoli avec : bouton de menu, champ de recherche affichant "Ctrl K", cloche de notifications, bascule de thème (clair/sombre/auto), menu utilisateur (avatar à initiales, rôle, déconnexion) ; zone de contenu avec largeur maximale confortable et transitions de page (View Transitions API).
2. Menu latéral exactement selon le cahier des charges, regroupé en sections avec titres discrets, entrée active mise en évidence par l'accent orange ; chaque entrée affichée seulement si l'utilisateur a le droit :
   TABLEAU DE BORD ; STOCK (Produits, Catégories, Entrées, Sorties, Mouvements, Inventaire) ; VENTES (Nouvelle vente, Liste des ventes, Factures, Retours clients) ; ACHATS (Nouvel achat, Liste des achats, Fournisseurs, Retours fournisseurs) ; CLIENTS (Clients, Crédits, Historique) ; FINANCES (Paiements, Dépenses, Créances, Résultats) ; RAPPORTS (Ventes, Achats, Stock, Finances) ; ADMINISTRATION (Utilisateurs, Rôles et droits, Paramètres, Journal d'activité).
3. Barre de navigation inférieure sur mobile avec les 4 actions principales (Accueil, Vente, Produits, Menu).
4. Pages 403, 404, 419 et 500 en français, au design du thème, avec illustration simple (icône) et bouton de retour.
5. Les routes non encore implémentées pointent vers une page "Bientôt disponible" (x-etat-vide).
6. PWA : manifest.webmanifest (nom, icônes, couleur de thème orange, mode standalone) et service worker qui met en cache uniquement les ressources statiques (CSS, JS, polices) ; jamais les données métier.
````

---

## Phase 3 — Données de base

### Prompt 9 — Catégories et unités

````
Implémente les CRUD de Catégories et d'Unités (contrôleur resource, FormRequest, vues Blade avec les composants du design system, recherche, pagination, toasts en français, squelettes de chargement, état vide avec bouton "Ajouter"). Création et modification dans une modale (sans changer de page). Une catégorie ou une unité utilisée par des produits ne peut pas être supprimée : elle peut seulement être désactivée (catégories) ou refusée (unités) avec un message clair. Affiche le nombre de produits par catégorie dans une carte. Droits : categories.gerer. Ajoute les tests.
````

### Prompt 10 — Produits

````
Implémente le module Produits :
- Liste paginée avec recherche instantanée (nom, référence, code-barres), filtres sous forme de "puces" (catégorie, statut actif/inactif, état du stock : normal, faible, rupture), tri par colonne, bascule d'affichage tableau / grille de cartes avec image du produit.
- Création et modification dans un panneau latéral coulissant (slide-over) : référence (générée automatiquement si vide, ex : PRD-00001), code-barres (saisie ou lecture scanner ; génération EAN-13 possible), photo (téléversement avec aperçu, redimensionnée), catégorie, unité, prix d'achat, prix de vente, prix de gros, stock minimum, avec calcul en direct de la marge (montant et pourcentage). Le stock initial saisi à la création passe obligatoirement par MouvementStockService (type ajustement_positif, motif "Stock initial").
- Validation : prix de vente >= prix d'achat (avertissement, pas blocage), unicité référence et code-barres.
- Pas de suppression : bouton "Désactiver / Réactiver". Un produit inactif n'apparaît pas dans les ventes et achats.
- Fiche produit en page "bento" : informations, stock actuel avec jauge colorée par rapport au minimum, mini-graphique des ventes, historique des mouvements, dernières ventes et achats.
- Impression d'étiquettes avec code-barres (PDF).
- Badge visuel : vert (OK), orange (proche du minimum), rouge (rupture).
Droits : produits.*. Tests complets.
````

### Prompt 11 — Fournisseurs et clients

````
Implémente les modules Fournisseurs et Clients (CRUD, désactivation, recherche, pagination, avatars à initiales colorées, états vides).
- Fiche fournisseur : coordonnées, historique des achats, dette totale (somme des reste_a_payer) en grande carte de statistique, historique des paiements.
- Fiche client : coordonnées, plafond de crédit avec barre de progression (crédit utilisé / plafond), historique des ventes et factures, créance totale, historique des paiements.
- Un client "Client comptoir" (par défaut, non supprimable) pour les ventes anonymes.
- Page "Crédits" : liste des clients ayant une créance, triée par montant décroissant, avec badges d'ancienneté (moins de 30 jours, 30-60 jours, plus de 60 jours).
Droits : fournisseurs.gerer, clients.gerer. Tests.
````

---

## Phase 4 — Cœur métier

### Prompt 12 — Service de gestion du stock

````
Crée app/Services/MouvementStockService.php, seule porte d'entrée pour modifier le stock :
- méthode enregistrer(Produit $produit, TypeMouvement $type, float $quantite, ?Model $reference, ?string $motif): MouvementStock ;
- le sens (entrée/sortie) est déduit du type ;
- verrouillage de la ligne produit (lockForUpdate) dans une transaction pour éviter les ventes simultanées sur le même stock ;
- calcul de stock_avant et stock_apres ; refus (exception métier en français) si le stock deviendrait négatif, sauf paramètre explicite ;
- mise à jour de produits.stock_actuel ;
- méthode de contrôle de cohérence (recalcule le stock à partir des mouvements) et commande Artisan "stock:verifier" qui signale les écarts.
Écris des tests unitaires solides : entrées, sorties, stock insuffisant, concurrence, cohérence.
````

### Prompt 13 — Achats

````
Implémente le module Achats avec AchatService :
- Écran "Nouvel achat" en deux colonnes : à gauche la recherche produit et les lignes (quantité, prix d'achat pré-rempli et modifiable), à droite une carte récapitulative collante (fournisseur, total calculé en direct avec Alpine.js, montant payé : total, partiel ou zéro = achat à crédit, mode de paiement, notes, bouton "Enregistrer l'achat" avec état chargement).
- À l'enregistrement, dans une transaction : numéro automatique ACH-AAAA-NNNNN, création de l'achat et des lignes, mouvement de stock "achat" (entrée) pour chaque ligne, mise à jour du prix d'achat du produit si demandé, création du paiement s'il y a un montant payé, calcul de reste_a_payer. Toast de succès avec lien vers le bon d'achat.
- Liste des achats (filtres en puces : période, fournisseur, statut de paiement avec badges Payé / Partiel / À crédit), détail, bon d'achat en PDF.
- Annulation d'un achat : possible seulement si le stock reste suffisant ; génère les mouvements inverses ; journalisée ; passe par la modale de confirmation avec motif.
- Ajout de paiements ultérieurs sur un achat à crédit.
Droits : achats.*. Tests : stock augmenté, achat à crédit, annulation.
````

### Prompt 14 — Ventes (caisse)

````
Implémente le module Ventes avec VenteService. L'écran de caisse est LA vitrine du thème : il doit être rapide, tactile et agréable, en deux zones côte à côte sur tablette/PC (catalogue à gauche, ticket à droite) et en onglets sur mobile.
- Catalogue : barre de recherche toujours active (focus automatique) pour nom, référence ou code-barres ; la saisie d'un code-barres + Entrée ajoute directement le produit au panier ; puces de catégories ; grille de cartes produit (image, nom, prix, badge de stock) cliquables ; produits en rupture grisés.
- Ticket (panier) : lignes modifiables (boutons + / −, saisie de quantité, suppression avec annulation possible pendant 5 secondes), choix du prix détail ou gros, sous-total, remise, total en très grand format avec animation de changement de valeur discrète.
- Client (par défaut "Client comptoir"), remise globale ou par ligne : autorisée seulement avec le droit ventes.remise, avec plafond paramétrable.
- Paiement dans une modale : choix du mode par grandes tuiles (espèces, Mobile Money, virement, chèque, crédit), montant reçu avec boutons de montants rapides (billets courants en Ar), calcul de la monnaie à rendre en évidence, reste à payer. Une vente à crédit est interdite pour le client comptoir et refusée si elle dépasse le plafond de crédit du client.
- Raccourcis clavier (affichés dans une aide F1) : F2 recherche, F4 client, F8 remise, F9 paiement, Échap annuler, Ctrl+Entrée valider.
- À la validation, dans une transaction : numéro VTE-AAAA-NNNNN, vente + lignes (avec prix_achat_unitaire copié), mouvement de stock "vente" (sortie) pour chaque ligne, refus clair si stock insuffisant, création du paiement, création de la facture (voir prompt suivant). Écran de réussite animé avec boutons "Imprimer le ticket" et "Nouvelle vente".
- Panier conservé localement si la page est rechargée ou la connexion coupée brièvement (localStorage), jamais validé hors ligne.
- Liste des ventes (filtres en puces : date, client, vendeur, statut), détail d'une vente.
- Annulation d'une vente (droit ventes.annuler, motif obligatoire) : remet le stock via mouvements inverses, annule la facture, journalise.
Tests : stock diminué, vente refusée si stock insuffisant, crédit, remise sans droit, annulation.
````

### Prompt 15 — Factures

````
Implémente les factures :
- Création automatique à chaque vente validée ; numéro séquentiel FAC-AAAA-NNNNN sans trou, remis à zéro chaque année, généré dans la transaction avec verrouillage.
- Modèle de facture PDF (DomPDF) conforme au cahier des charges et à l'identité visuelle du thème (voir règle M : tables HTML, couleurs hexadécimales, accent orange, typographie sobre, beaucoup d'espace) : en-tête entreprise avec logo (depuis parametres), numéro, client, date, vendeur, tableau des lignes à bandes légères, sous-total, remise, total à payer mis en valeur, montant payé, reste, mode de paiement, TVA si taux > 0, code-barres du numéro de facture, pied de page paramétrable. Format A4 et format ticket 80 mm. Filigrane "ANNULÉE" si la facture est annulée.
- Page "Factures" : liste, recherche, filtres en puces, aperçu dans une modale, impression, téléchargement PDF, envoi par email ou lien de partage (WhatsApp) optionnel.
- Une facture n'est jamais modifiable ; une correction passe par annulation ou retour.
Tests : numérotation sans doublon, PDF généré.
````

### Prompt 16 — Paiements, créances et dettes

````
Implémente les paiements :
- PaiementService : enregistrer un paiement partiel ou total sur une vente ou un achat (morph), validation (montant > 0 et <= reste_a_payer), mise à jour de montant_paye et reste_a_payer dans une transaction.
- Page "Paiements" : historique filtrable (période, mode, client/fournisseur), avec cartes de totaux par mode de paiement en haut.
- Page "Créances" : clients qui nous doivent de l'argent, détail par facture, bouton "Encaisser" ouvrant une modale de paiement.
- Page "Dettes fournisseurs" équivalente.
- Reçu de paiement en PDF.
Droits : paiements.gerer. Tests.
````

### Prompt 17 — Retours

````
Implémente les retours avec RetourService :
- Retour client : choisir la vente d'origine, sélectionner les produits et quantités (limitées à la quantité vendue moins les retours déjà faits), motif obligatoire (liste de motifs courants + texte libre) ; mouvement de stock "retour_client" (entrée) ; remboursement ou avoir enregistré (diminue la créance si la vente est à crédit).
- Retour fournisseur : même logique sur un achat ; mouvement "retour_fournisseur" (sortie) ; diminue la dette.
- Assistant en étapes (1. Document d'origine, 2. Produits, 3. Confirmation) avec indicateur de progression.
- Liste, détail et bon de retour PDF, annulation possible avec motif.
Droits : retours.gerer. Tests.
````

### Prompt 18 — Inventaire et mouvements de stock

````
Implémente :
1. Page "Mouvements" : liste filtrable de mouvements_stock (produit, type, sens, période, utilisateur), avec flèche verte (entrée) ou rouge (sortie), stock avant/après, référence cliquable vers le document d'origine, export Excel.
2. Pages "Entrées" et "Sorties" : vues filtrées de ces mouvements, avec la possibilité d'enregistrer manuellement une perte ou un ajustement (motif obligatoire, droit stock.ajuster).
3. Inventaire physique : création d'un inventaire "en cours", saisie des quantités comptées dans une liste optimisée pour tablette (gros champs numériques, passage automatique à la ligne suivante avec Entrée, recherche, filtre "non comptés", barre de progression du comptage), import CSV optionnel, calcul des écarts avec le stock théorique (écarts colorés), validation qui génère les mouvements d'ajustement positif/négatif ; un inventaire validé n'est plus modifiable ; impression de la feuille de comptage et du rapport d'écarts en PDF.
Tests : un inventaire validé corrige bien le stock.
````

### Prompt 19 — Dépenses

````
Implémente le module Dépenses : CRUD dans une modale (catégorie parmi salaires, transport, électricité, eau, loyer, entretien, fournitures, autres, avec icône par catégorie ; libellé ; montant ; date ; mode de paiement ; notes), liste filtrable par période et catégorie avec total, graphique en anneau par catégorie (couleurs issues des jetons du thème). Droit : depenses.gerer. Les dépenses ne sont pas supprimables par un simple utilisateur : suppression réservée à l'Administrateur et journalisée. Tests.
````

---

## Phase 5 — Pilotage

### Prompt 20 — Tableau de bord

````
Implémente le tableau de bord (page d'accueil après connexion) en "bento grid" responsive, selon le cahier des charges :
- Bandeau d'accueil personnalisé ("Bonjour, [prénom]") avec la date et un raccourci "Nouvelle vente".
- Cartes de statistiques (x-carte-stat) avec tendance par rapport à la veille ou à la période précédente : ventes du jour, achats du jour, bénéfice du jour (ventes − coût d'achat des produits vendus − dépenses du jour), nombre de produits actifs, de clients, de fournisseurs.
- Grande carte "Ventes" avec graphique (Chart.js) sur 7 / 30 / 90 jours, sélecteur de période, couleurs lues dans les variables CSS du thème, mise à jour automatique en changeant de mode clair/sombre.
- Carte "Stock faible" : liste des produits sous le minimum avec jauge et lien vers le produit.
- Carte "Top 5 produits du mois" avec barres horizontales ; carte "Créances en cours" ; carte "Dernières ventes".
- Squelettes de chargement pendant la récupération des données (chargement différé des cartes lourdes via requêtes asynchrones).
- Contenu adapté au rôle : un vendeur ne voit pas les achats ni le bénéfice (droit finances.voir).
Requêtes optimisées (agrégations SQL, cache de 60 secondes pour les stats lourdes). Tests des calculs.
````

### Prompt 21 — Rapports

````
Implémente le module Rapports (droit rapports.voir), chaque rapport avec filtres de période (sélecteur moderne avec raccourcis : aujourd'hui, 7 jours, ce mois, mois dernier, personnalisé), affichage écran avec graphiques, export PDF et export Excel :
- Ventes : par jour, semaine, mois, utilisateur, produit.
- Achats : par période, par fournisseur, produits achetés.
- Stock : état actuel (avec valeur du stock au prix d'achat), stock faible, ruptures, produits sans vente depuis N jours, mouvements.
- Finances (droit finances.voir) : chiffre d'affaires, achats, dépenses, bénéfice, paiements reçus, créances, dettes.
Les PDF reprennent l'identité visuelle du thème (règle M). Crée une classe de requête par rapport (app/Rapports) pour séparer la logique de l'affichage. Tests sur des jeux de données connus.
````

---

## Phase 6 — Administration

### Prompt 22 — Utilisateurs, rôles, paramètres, journal

````
Implémente l'administration :
1. Utilisateurs : CRUD, attribution d'un rôle, activation/désactivation, réinitialisation du mot de passe ; un administrateur ne peut pas se désactiver lui-même ni supprimer le dernier administrateur.
2. Rôles et droits : liste des rôles en cartes, matrice de cases à cocher (interrupteurs) des droits par module, création de rôles personnalisés.
3. Paramètres : onglets (Entreprise : nom, adresse, téléphone, email, NIF/STAT, logo ; Facturation : préfixes de numérotation, taux de TVA, format de facture par défaut, pied de page ; Ventes : remise maximale par rôle ; Apparence : thème par défaut clair/sombre/auto et couleur d'accent parmi quelques choix prédéfinis, appliqués via les jetons CSS).
4. Journal d'activité : trait Journalisable qui enregistre automatiquement création, modification, suppression, annulation, connexion/déconnexion (utilisateur, action, objet, anciennes/nouvelles valeurs, IP). Page de consultation en chronologie filtrable, non modifiable.
Droits : utilisateurs.gerer, roles.gerer, parametres.gerer, journal.voir. Tests.
````

### Prompt 23 — Alertes, sauvegarde, palette de commandes

````
Ajoute :
1. Alertes de stock faible : notification dans l'application (cloche dans l'en-tête avec compteur et panneau déroulant) et email quotidien récapitulatif au responsable via une commande planifiée (Laravel Scheduler).
2. Sauvegarde automatique de la base de données (spatie/laravel-backup ou mysqldump planifié), quotidienne, avec conservation de 14 jours, et page d'administration pour la lancer manuellement et télécharger la dernière sauvegarde.
3. Palette de commandes (Ctrl+K), modale en verre dépoli : recherche globale (produits, clients, fournisseurs, factures, ventes) avec résultats groupés et icônes, plus actions rapides ("Nouvelle vente", "Nouvel achat", "Ajouter un produit", "Changer de thème", pages du menu) ; navigation aux flèches, validation avec Entrée, historique des recherches récentes ; respecte les droits de l'utilisateur.
4. Import de produits depuis un fichier Excel/CSV (avec aperçu et rapport d'erreurs) et export de la liste des produits.
````

---

## Phase 7 — Qualité et livraison

### Prompt 24 — Tests, revue technique et audit visuel

````
Fais une revue complète du projet :
1. Lance tous les tests, complète ceux qui manquent (objectif : couvrir tous les services : stock, achats, ventes, paiements, retours, inventaire, facturation, rapports).
2. Scénario de bout en bout (test Feature) : achat de 100 sacs de ciment → vente de 10 → retour client de 2 → inventaire avec écart de -1 → vérifier stock, mouvements, paiements, créances, bénéfice.
3. Vérifie que le stock n'est modifié nulle part ailleurs que dans MouvementStockService.
4. Vérifie que toutes les routes sont protégées par authentification et droits.
5. Cherche les problèmes N+1 (eager loading), ajoute les index manquants.
6. Vérifie la protection CSRF, XSS (échappement Blade), injection SQL, mass-assignment.
7. AUDIT VISUEL ET ACCESSIBILITÉ du thème :
   - parcours de TOUTES les pages en mode clair et en mode sombre, à 375 px, 768 px et 1440 px de largeur : aucun débordement horizontal, aucun texte illisible, aucune couleur codée en dur ;
   - contrastes WCAG AA (≥ 4,5:1 pour le texte) ; navigation entière au clavier ; focus toujours visible ; labels et rôles ARIA présents ;
   - vérification de prefers-reduced-motion ;
   - audit Lighthouse (objectifs : Performance ≥ 90, Accessibilité ≥ 95, Bonnes pratiques ≥ 95, PWA installable) ;
   - cohérence : mêmes composants, mêmes espacements, mêmes icônes partout ; aucun alert()/confirm() natif restant.
8. Liste les problèmes trouvés par gravité et corrige-les.
````

### Prompt 25 — Déploiement et documentation

````
Prépare la mise en production :
1. Checklist de configuration (.env de production, APP_DEBUG=false, cache de config/routes/vues, optimisation autoload, build Vite de production, HTTPS, permissions des dossiers).
2. Procédure d'installation sur un serveur Linux (Nginx ou Apache, PHP 8.2, MySQL), avec le cron du scheduler et le worker de file d'attente, compression gzip/brotli et en-têtes de cache longue durée pour les ressources statiques.
3. Script ou procédure de mise à jour (git pull, composer install --no-dev, php artisan migrate --force, npm run build).
4. Documentation utilisateur en français (guide par rôle : vendeur, magasinier, responsable, administrateur), illustrée par des captures d'écran en mode clair, avec les procédures : faire une vente (avec raccourcis clavier), faire un achat, inventaire, annuler une vente, clôture de journée, changer de thème, installer l'application sur tablette ou téléphone.
5. Mets à jour le README.md.
````

---

## Prompts utiles en cours de route

**Correction d'un bug**
````
Voici le problème : [décris le comportement attendu et observé, colle le message d'erreur]. Trouve la cause racine, corrige-la sans casser les conventions du projet (noms français, stock uniquement via MouvementStockService, transactions, jetons du thème), et ajoute un test qui reproduit le bug.
````

**Ajout d'une colonne ou d'une table**
````
Je veux ajouter [fonctionnalité]. Propose d'abord les changements de base de données (noms français, cohérents avec les tables existantes), attends ma validation, puis crée la migration, le modèle, les relations, l'interface (avec les composants du design system) et les tests.
````

**Revue d'un module terminé**
````
Relis le module [nom] : vérifie le respect des conventions du Prompt 0, la gestion des droits, les cas limites (stock insuffisant, montants à zéro, doubles clics, annulation), la cohérence visuelle avec le thème 2026 (clair et sombre, mobile et PC), l'accessibilité, la lisibilité du code et les tests manquants. Liste les problèmes par gravité puis corrige-les.
````

**Retouche visuelle d'une page**
````
Modernise la page [nom] pour qu'elle respecte le thème 2026 : utilise uniquement les composants du design system et les jetons de couleur, vérifie le rendu clair et sombre, ajoute les états de chargement (squelettes) et l'état vide, améliore la version mobile. Ne modifie pas la logique métier.
````

**Changer la couleur d'accent**
````
Change la couleur d'accent de l'application de l'orange à [couleur] en modifiant uniquement les jetons --color-primaire-* dans @theme (valeurs OKLCH, mode clair et sombre). Vérifie les contrastes AA et mets à jour les couleurs hexadécimales des modèles PDF.
````
