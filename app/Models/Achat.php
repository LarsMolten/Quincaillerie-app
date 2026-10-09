<?php

namespace App\Models;

use App\Enums\StatutAchat;
use App\Enums\StatutPaiement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Achat extends Model
{
    use HasFactory;

    protected $table = 'achats';

    protected $fillable = [
        'numero',
        'fournisseur_id',
        'utilisateur_id',
        'date_achat',
        'total',
        'montant_paye',
        'reste_a_payer',
        'statut',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_achat' => 'date',
            'total' => 'decimal:2',
            'montant_paye' => 'decimal:2',
            'reste_a_payer' => 'decimal:2',
            'statut' => StatutAchat::class,
        ];
    }

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class, 'fournisseur_id')->withTrashed();
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'utilisateur_id')->withTrashed();
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneAchat::class, 'achat_id');
    }

    public function paiements(): MorphMany
    {
        return $this->morphMany(Paiement::class, 'payable');
    }

    /** Mouvements de stock générés par ce document. */
    public function mouvementsStock(): MorphMany
    {
        return $this->morphMany(MouvementStock::class, 'reference');
    }

    public function retours(): HasMany
    {
        return $this->hasMany(Retour::class, 'achat_id');
    }

    public function scopeValides(Builder $requete): void
    {
        $requete->where('statut', StatutAchat::Valide);
    }

    /** Badge de règlement : payé, partiel ou à crédit. */
    protected function statutPaiement(): Attribute
    {
        return Attribute::get(fn (): StatutPaiement => StatutPaiement::de($this));
    }

    /** Vrai quand il ne reste plus rien à payer au fournisseur. */
    protected function estSoldee(): Attribute
    {
        return Attribute::get(fn (): bool => (float) $this->reste_a_payer <= 0);
    }
}
