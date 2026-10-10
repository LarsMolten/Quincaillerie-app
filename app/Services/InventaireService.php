<?php

namespace App\Services;

use App\Enums\StatutInventaire;
use App\Enums\TypeMouvementStock;
use App\Exceptions\OperationRefuseeException;
use App\Models\Inventaire;
use App\Models\LigneInventaire;
use App\Models\Produit;
use App\Support\Entreprise;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DocumentPdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Inventaire physique : ouverture (tous les produits actifs ou certaines catégories, numéro INV-AAAA-NNNNN),
 * comptage, import CSV, puis validation qui corrige le stock par des ajustements positifs ou négatifs.
 * L'écart est calculé sur le stock réel au moment de la validation ; un inventaire validé n'est plus modifiable.
 */
class InventaireService
{
    /** Rejeux en cas d'interblocage sur le premier numéro de l'année (voir NumerotationService). */
    private const TENTATIVES = 5;

    public function __construct(
        private readonly MouvementStockService $stock,
        private readonly NumerotationService $numerotation,
        private readonly JournalService $journal,
    ) {}

    /**
     * @param  list<int>|null  $categories  null : tous les produits actifs
     */
    public function ouvrir(?array $categories = null, ?string $notes = null): Inventaire
    {
        return DB::transaction(function () use ($categories, $notes) {
            $produits = Produit::query()->actif()
                ->when($categories, fn ($q) => $q->whereIn('categorie_id', $categories))
                ->with('categorie')
                ->get()
                ->sortBy(fn (Produit $p) => mb_strtolower(($p->categorie?->nom ?? '').' '.$p->nom))
                ->values();

            if ($produits->isEmpty()) {
                throw new OperationRefuseeException('Aucun produit actif dans ce périmètre.');
            }

            $dejaEnCours = LigneInventaire::query()
                ->whereIn('produit_id', $produits->pluck('id'))
                ->whereHas('inventaire', fn ($q) => $q->where('statut', StatutInventaire::EnCours))
                ->with(['inventaire', 'produit'])
                ->first();

            if ($dejaEnCours) {
                throw new OperationRefuseeException(sprintf(
                    '« %s » est déjà dans l\'inventaire en cours %s : validez-le ou choisissez d\'autres catégories.',
                    $dejaEnCours->produit->nom, $dejaEnCours->inventaire->numero,
                ));
            }

            $inventaire = Inventaire::create([
                'numero' => $this->numerotation->suivant(Inventaire::class, $this->numerotation->prefixe('inventaire'), today()),
                'date_inventaire' => today(),
                'statut' => StatutInventaire::EnCours,
                'utilisateur_id' => Auth::id(),
                'notes' => filled($notes) ? trim($notes) : null,
            ]);

            $inventaire->lignes()->createMany($produits->map(fn (Produit $p) => [
                'produit_id' => $p->id,
                'stock_theorique' => $p->stock_actuel,
            ])->all());

            $this->journal->enregistrer('inventaire.ouvert', $inventaire, [
                'numero' => $inventaire->numero,
                'produits' => $produits->count(),
                'categories' => $categories,
            ]);

            return $inventaire;
        }, self::TENTATIVES);
    }

    /** Quantité comptée d'une ligne (null : remet le produit à « non compté »). Écart provisoire sur le stock actuel. */
    public function compter(Inventaire $inventaire, LigneInventaire $ligne, ?float $quantite): LigneInventaire
    {
        $this->verifierEnCours($inventaire);

        if ($ligne->inventaire_id !== $inventaire->id) {
            throw new OperationRefuseeException('Ce produit ne fait pas partie de cet inventaire.');
        }

        if ($quantite !== null && ($quantite < 0 || ! is_finite($quantite))) {
            throw new OperationRefuseeException('La quantité comptée ne peut pas être négative.');
        }

        $compte = $quantite === null ? null : round($quantite, 3);
        $ligne->update([
            'stock_compte' => $compte,
            'ecart' => $compte === null ? null : round($compte - (float) $ligne->produit->stock_actuel, 3),
        ]);

        return $ligne;
    }

    /**
     * Import CSV : une ligne par produit, « référence (ou code-barres) ; quantité », séparateur ; ou ,
     * en-tête facultatif, décimales avec virgule acceptées.
     *
     * @return array{importees: int, inconnues: list<string>, invalides: list<string>}
     */
    public function importerCsv(Inventaire $inventaire, UploadedFile $fichier): array
    {
        $this->verifierEnCours($inventaire);

        $contenu = (string) file_get_contents($fichier->getRealPath());
        $contenu = preg_replace('/^\xEF\xBB\xBF/', '', $contenu); // BOM d'Excel
        $lignes = preg_split('/\r\n|\r|\n/', trim($contenu)) ?: [];
        $separateur = substr_count($lignes[0] ?? '', ';') >= substr_count($lignes[0] ?? '', ',') ? ';' : ',';

        // Recherche par référence ou code-barres (clés texte : un code-barres numérique ne doit pas être renuméroté)
        $parProduit = [];
        foreach ($inventaire->lignes()->with('produit')->get() as $l) {
            $parProduit['r:'.mb_strtolower($l->produit->reference)] = $l;
            if (filled($l->produit->code_barres)) {
                $parProduit['r:'.mb_strtolower($l->produit->code_barres)] = $l;
            }
        }

        $bilan = ['importees' => 0, 'inconnues' => [], 'invalides' => []];

        DB::transaction(function () use ($lignes, $separateur, $parProduit, $inventaire, &$bilan) {
            foreach ($lignes as $numero => $texte) {
                $colonnes = array_map('trim', str_getcsv($texte, $separateur));
                [$code, $valeur] = [$colonnes[0] ?? '', $colonnes[1] ?? ''];

                if ($code === '' || ($numero === 0 && ! is_numeric(str_replace(',', '.', $valeur)))) {
                    continue; // ligne vide ou en-tête
                }

                $ligne = $parProduit['r:'.mb_strtolower($code)] ?? null;
                $quantite = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], $valeur);

                if ($ligne === null) {
                    $bilan['inconnues'][] = $code;
                } elseif (! is_numeric($quantite) || (float) $quantite < 0) {
                    $bilan['invalides'][] = "{$code} ({$valeur})";
                } else {
                    $this->compter($inventaire, $ligne, (float) $quantite);
                    $bilan['importees']++;
                }
            }
        });

        return $bilan;
    }

    /**
     * Validation : écart = compté − stock réel du moment (relu sous verrou), corrigé par un ajustement
     * positif ou négatif. Les produits non comptés gardent leur stock, sur confirmation explicite.
     */
    public function valider(Inventaire $inventaire, bool $confirmerNonComptes = false): Inventaire
    {
        return DB::transaction(function () use ($inventaire, $confirmerNonComptes) {
            $verrouille = Inventaire::query()->whereKey($inventaire->getKey())->lockForUpdate()->firstOrFail();
            $this->verifierEnCours($verrouille);

            $lignes = $verrouille->lignes()->with('produit')->orderBy('id')->get();
            $nonComptes = $lignes->whereNull('stock_compte')->count();

            if ($lignes->whereNotNull('stock_compte')->isEmpty()) {
                throw new OperationRefuseeException('Aucun produit n\'a été compté : rien à valider.');
            }

            if ($nonComptes > 0 && ! $confirmerNonComptes) {
                throw new OperationRefuseeException("{$nonComptes} produit(s) ne sont pas comptés : confirmez pour valider sans eux (leur stock ne changera pas).");
            }

            $bilan = ['positifs' => 0, 'negatifs' => 0, 'valeur' => 0.0];

            foreach ($lignes->whereNotNull('stock_compte') as $ligne) {
                // Stock relu sous verrou : les ventes faites pendant le comptage sont prises en compte
                $actuel = (float) Produit::withTrashed()->whereKey($ligne->produit_id)->lockForUpdate()->value('stock_actuel');
                $ecart = round((float) $ligne->stock_compte - $actuel, 3);

                if ($ecart > 0) {
                    $this->stock->enregistrer($ligne->produit, TypeMouvementStock::AjustementPositif, $ecart, $verrouille, "Inventaire {$verrouille->numero}");
                    $bilan['positifs']++;
                } elseif ($ecart < 0) {
                    $this->stock->enregistrer($ligne->produit, TypeMouvementStock::AjustementNegatif, -$ecart, $verrouille, "Inventaire {$verrouille->numero}");
                    $bilan['negatifs']++;
                }

                $bilan['valeur'] += $ecart * (float) $ligne->produit->prix_achat;
                $ligne->update(['ecart' => $ecart]);
            }

            $verrouille->update(['statut' => StatutInventaire::Valide]);

            $this->journal->enregistrer('inventaire.valide', $verrouille, [
                'numero' => $verrouille->numero,
                'produits_comptes' => $lignes->count() - $nonComptes,
                'non_comptes' => $nonComptes,
                'ecarts_positifs' => $bilan['positifs'],
                'ecarts_negatifs' => $bilan['negatifs'],
                'valeur_ecarts' => round($bilan['valeur'], 2),
            ]);

            $inventaire->setRawAttributes($verrouille->getAttributes(), true);

            return $inventaire;
        }, self::TENTATIVES);
    }

    /** Feuille de comptage (stock théorique masqué) ou rapport d'écarts, au format A4. */
    public function pdf(Inventaire $inventaire, string $document): DocumentPdf
    {
        $inventaire->load(['utilisateur', 'lignes.produit.categorie', 'lignes.produit.unite']);

        return Pdf::loadView($document === 'feuille' ? 'inventaires.feuille' : 'inventaires.rapport', [
            'inventaire' => $inventaire,
            'groupes' => $inventaire->lignes->groupBy(fn (LigneInventaire $l) => $l->produit->categorie?->nom ?? 'Sans catégorie'),
            'entreprise' => Entreprise::donnees(),
        ])->setPaper('a4');
    }

    private function verifierEnCours(Inventaire $inventaire): void
    {
        if ($inventaire->statut === StatutInventaire::Valide) {
            throw new OperationRefuseeException("L'inventaire {$inventaire->numero} est validé : il n'est plus modifiable.");
        }
    }
}
