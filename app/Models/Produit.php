<?php

namespace App\Models;

use App\Enums\EtatStock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Produit extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'produits';

    /**
     * « stock_actuel » est volontairement absent : seul MouvementStockService le modifie.
     */
    protected $fillable = [
        'reference',
        'code_barres',
        'nom',
        'description',
        'image',
        'categorie_id',
        'unite_id',
        'prix_achat',
        'prix_vente',
        'prix_gros',
        'stock_minimum',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'prix_achat' => 'decimal:2',
            'prix_vente' => 'decimal:2',
            'prix_gros' => 'decimal:2',
            'stock_actuel' => 'decimal:3',
            'stock_minimum' => 'decimal:3',
            'actif' => 'boolean',
        ];
    }

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(Categorie::class, 'categorie_id')->withTrashed();
    }

    public function unite(): BelongsTo
    {
        return $this->belongsTo(Unite::class, 'unite_id')->withTrashed();
    }

    public function lignesAchat(): HasMany
    {
        return $this->hasMany(LigneAchat::class, 'produit_id');
    }

    public function lignesVente(): HasMany
    {
        return $this->hasMany(LigneVente::class, 'produit_id');
    }

    public function mouvementsStock(): HasMany
    {
        return $this->hasMany(MouvementStock::class, 'produit_id');
    }

    public function scopeActif(Builder $requete): void
    {
        $requete->where('actif', true);
    }

    /** Produits dont le stock est épuisé (ou négatif si autorisé). */
    public function scopeEnRupture(Builder $requete): void
    {
        $requete->where('stock_actuel', '<=', 0);
    }

    /** Produits encore disponibles mais au niveau du stock minimum ou en dessous. */
    public function scopeStockFaible(Builder $requete): void
    {
        $requete->where('stock_actuel', '>', 0)
            ->whereColumn('stock_actuel', '<=', 'stock_minimum');
    }

    /** Recherche par nom, référence ou code-barres. */
    public function scopeRecherche(Builder $requete, ?string $terme): void
    {
        $terme = trim((string) $terme);

        if ($terme === '') {
            return;
        }

        $requete->where(function (Builder $sousRequete) use ($terme) {
            $sousRequete->where('nom', 'like', "%{$terme}%")
                ->orWhere('reference', 'like', "%{$terme}%")
                ->orWhere('code_barres', $terme);
        });
    }

    /** Niveau de stock affiché dans les badges : normal, faible ou rupture. */
    protected function etatStock(): Attribute
    {
        return Attribute::get(function (): EtatStock {
            $stock = (float) $this->stock_actuel;

            return match (true) {
                $stock <= 0 => EtatStock::Rupture,
                $stock <= (float) $this->stock_minimum => EtatStock::Faible,
                default => EtatStock::Normal,
            };
        });
    }
}
