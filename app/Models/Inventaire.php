<?php

namespace App\Models;

use App\Enums\StatutInventaire;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use LogicException;

/**
 * Inventaire physique (INV-AAAA-NNNNN) : en cours pendant le comptage, puis validé (non modifiable).
 */
class Inventaire extends Model
{
    use HasFactory;

    protected $table = 'inventaires';

    protected $fillable = [
        'numero',
        'date_inventaire',
        'statut',
        'utilisateur_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_inventaire' => 'date',
            'statut' => StatutInventaire::class,
        ];
    }

    /**
     * Un inventaire validé n'est plus modifiable, et aucun inventaire n'est supprimé
     * (garde-fou en plus de InventaireService).
     */
    protected static function booted(): void
    {
        static::updating(function (Inventaire $inventaire) {
            if ($inventaire->getOriginal('statut') === StatutInventaire::Valide) {
                throw new LogicException("L'inventaire {$inventaire->numero} est validé : il n'est plus modifiable.");
            }
        });

        static::deleting(fn (Inventaire $inventaire) => throw new LogicException("L'inventaire {$inventaire->numero} ne peut pas être supprimé."));
    }

    /** Produits comptés / total, pour la barre de progression. */
    public function scopeAvecProgression(Builder $requete): void
    {
        $requete->withCount(['lignes', 'lignes as lignes_comptees_count' => fn (Builder $q) => $q->whereNotNull('stock_compte')]);
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'utilisateur_id')->withTrashed();
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneInventaire::class, 'inventaire_id');
    }

    /** Ajustements de stock générés à la validation de l'inventaire. */
    public function mouvementsStock(): MorphMany
    {
        return $this->morphMany(MouvementStock::class, 'reference');
    }
}
