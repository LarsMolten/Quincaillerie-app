# Quincaillerie

Application web de gestion de quincaillerie : produits, stock, achats, ventes (caisse), clients, fournisseurs, factures, paiements, dépenses et rapports.

Interface en français, montants en Ariary (Ar), fuseau horaire `Indian/Antananarivo`.

## Prérequis

- PHP 8.3 ou plus, avec les extensions `pdo_mysql`, `mbstring`, `gd`, `zip`, `intl`, `fileinfo`
- Composer 2
- Node.js 20 ou plus, avec npm
- MySQL 8 (ou MariaDB 10.6 ou plus)

## Installation

```bash
# 1. Dépendances
composer install
npm install

# 2. Configuration
cp .env.example .env
php artisan key:generate
```

Renseigner ensuite la connexion MySQL dans `.env` :

```dotenv
DB_DATABASE=quincaillerie
DB_USERNAME=root
DB_PASSWORD=
```

Créer la base de données en utf8mb4 :

```sql
CREATE DATABASE quincaillerie CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
# 3. Tables et données de base
php artisan migrate --seed

# 4. Ressources front (Tailwind CSS v4, Alpine.js, police Inter locale)
npm run build

# 5. Lancement
php artisan serve
```

L'application est alors disponible sur http://localhost:8000.

## Développement

```bash
npm run dev            # Vite avec rechargement à chaud
php artisan serve      # serveur PHP
php artisan test       # tests automatisés
```

## Stack technique

| Domaine | Outils |
|---|---|
| Back-end | Laravel 13, PHP 8.3+, MySQL (utf8mb4) |
| Front-end | Blade, Tailwind CSS v4 (configuration CSS-first), Alpine.js, Vite |
| Interface | Police Inter (hébergée localement), icônes Lucide, Chart.js |
| Documents | barryvdh/laravel-dompdf (PDF), maatwebsite/excel (Excel), picqer/php-barcode-generator (codes-barres) |
| Traductions | laravel-lang/lang (fichiers français dans `lang/fr`) |

Aucune ressource n'est chargée depuis un CDN : polices et scripts sont empaquetés par Vite. L'application fonctionne donc avec une connexion limitée.

## Utilitaires

- `format_ar($montant)` (`app/helpers.php`) : formate un montant en Ariary sans décimales, avec des espaces insécables. Exemple : `format_ar(35000)` donne « 35 000 Ar ».

## Conventions

Les règles de nommage, les règles métier et les règles du thème visuel sont décrites dans [CLAUDE.md](CLAUDE.md).
