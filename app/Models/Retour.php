<?php

namespace App\Models;

use App\Enums\ModePaiement;
use App\Enums\StatutRetour;
use App\Enums\TypeRetour;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Retour client (lié à une vente) ou retour fournisseur (lié à un achat), numéroté RET-AAAA-NNNNN.
 * Son montant diminue d'abord le reste à payer du document (montant_avoir) ; l'excédent est remboursé.
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
        'montant_avoir',
        'montant_rembourse',
        'mode_remboursement',
        'motif',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeRetour::class,
            'date_retour' => 'date',
            'total' => 'decimal:2',
            'montant_avoir' => 'decimal:2',
            'montant_rembourse' => 'decimal:2',
            'mode_remboursement' => ModePaiement::class,
            'statut' => StatutRetour::class,
        ];
    }

    /** Document d'origine : la vente (retour client) ou l'achat (retour fournisseur). */
    public function document(): Vente|Achat|null
    {
        return $this->type === TypeRetour::Client ? $this->vente : $this->achat;
    }

    public function scopeValides(Builder $requete): void
    {
        $requete->where('statut', StatutRetour::Valide);
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
