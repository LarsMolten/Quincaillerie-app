<?php

namespace App\Models;

use App\Enums\ModePaiement;
use App\Enums\StatutVente;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Vente extends Model
{
    use HasFactory;

    protected $table = 'ventes';

    protected $fillable = [
        'numero',
        'client_id',
        'utilisateur_id',
        'date_vente',
        'sous_total',
        'remise',
        'total',
        'montant_paye',
        'reste_a_payer',
        'mode_paiement',
        'statut',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_vente' => 'datetime',
            'sous_total' => 'decimal:2',
            'remise' => 'decimal:2',
            'total' => 'decimal:2',
            'montant_paye' => 'decimal:2',
            'reste_a_payer' => 'decimal:2',
            'mode_paiement' => ModePaiement::class,
            'statut' => StatutVente::class,
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id')->withTrashed();
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'utilisateur_id')->withTrashed();
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneVente::class, 'vente_id');
    }

    public function facture(): HasOne
    {
        return $this->hasOne(Facture::class, 'vente_id');
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
        return $this->hasMany(Retour::class, 'vente_id');
    }

    public function scopeValidees(Builder $requete): void
    {
        $requete->where('statut', StatutVente::Validee);
    }

    /** Vrai quand le client a tout réglé. */
    protected function estSoldee(): Attribute
    {
        return Attribute::get(fn (): bool => (float) $this->reste_a_payer <= 0);
    }
}
