<?php

namespace App\Models;

use App\Enums\SensMouvement;
use App\Enums\TypeMouvementStock;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Historique des entrées et sorties de stock. Créé uniquement par MouvementStockService.
 */
class MouvementStock extends Model
{
    use HasFactory;

    /** Historique non modifiable : pas de colonne updated_at. */
    public const UPDATED_AT = null;

    protected $table = 'mouvements_stock';

    protected $fillable = [
        'produit_id',
        'type',
        'sens',
        'quantite',
        'stock_avant',
        'stock_apres',
        'utilisateur_id',
        'motif',
        'reference_type',
        'reference_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeMouvementStock::class,
            'sens' => SensMouvement::class,
            'quantite' => 'decimal:3',
            'stock_avant' => 'decimal:3',
            'stock_apres' => 'decimal:3',
        ];
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class, 'produit_id')->withTrashed();
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'utilisateur_id')->withTrashed();
    }

    /** Document à l'origine du mouvement (achat, vente, retour, inventaire). */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Libellé du document d'origine et son adresse si l'utilisateur peut l'ouvrir
     * (null : ajustement manuel sans document).
     *
     * @return array{libelle: string, url: ?string}|null
     */
    public function lienDocument(?Utilisateur $utilisateur): ?array
    {
        $document = $this->reference;

        [$libelle, $route, $droit] = match (true) {
            $document instanceof Vente => [$document->numero, 'ventes.show', 'ventes.voir'],
            $document instanceof Achat => [$document->numero, 'achats.show', 'achats.voir'],
            $document instanceof Retour => [$document->numero, 'retours.show', 'retours.gerer'],
            $document instanceof Inventaire => [$document->numero, 'inventaires.show', 'inventaires.gerer'],
            $document instanceof Produit => ['Stock initial', 'produits.show', 'produits.voir'],
            default => [null, null, null],
        };

        if ($libelle === null) {
            return null;
        }

        return ['libelle' => $libelle, 'url' => $utilisateur?->can($droit) ? route($route, $document) : null];
    }
}
