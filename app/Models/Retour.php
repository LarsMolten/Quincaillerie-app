<?php

namespace App\Models;

use App\Enums\StatutRetour;
use App\Enums\TypeRetour;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Retour client (lié à une vente) ou retour fournisseur (lié à un achat).
 */
class Retour extends Model
{
    use HasFactory;

    protected $table = 'retours';

    protected $fillable = [
        'numero',
        'type',
        'vente_id',
        'achat_id',
        'utilisateur_id',
        'date_retour',
        'total',
        'motif',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeRetour::class,
            'date_retour' => 'date',
            'total' => 'decimal:2',
            'statut' => StatutRetour::class,
        ];
    }

    public function vente(): BelongsTo
    {
        return $this->belongsTo(Vente::class, 'vente_id');
    }

    public function achat(): BelongsTo
    {
        return $this->belongsTo(Achat::class, 'achat_id');
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'utilisateur_id')->withTrashed();
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneRetour::class, 'retour_id');
    }

    public function mouvementsStock(): MorphMany
    {
        return $this->morphMany(MouvementStock::class, 'reference');
    }
}
