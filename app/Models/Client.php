<?php

namespace App\Models;

use App\Enums\StatutVente;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    /** Client par défaut de la caisse : toujours présent, jamais à crédit. */
    public const COMPTOIR = 'Client comptoir';

    protected $table = 'clients';

    protected $fillable = [
        'nom',
        'telephone',
        'email',
        'adresse',
        'plafond_credit',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'plafond_credit' => 'decimal:2',
            'actif' => 'boolean',
        ];
    }

    public function ventes(): HasMany
    {
        return $this->hasMany(Vente::class, 'client_id');
    }

    public function scopeActif(Builder $requete): void
    {
        $requete->where('actif', true);
    }

    public function estComptoir(): bool
    {
        return $this->nom === self::COMPTOIR;
    }

    /** Montant restant dû par le client sur les ventes validées. */
    protected function creanceTotale(): Attribute
    {
        return Attribute::get(fn (): float => (float) $this->ventes()
            ->where('statut', StatutVente::Validee)
            ->sum('reste_a_payer'));
    }
}
