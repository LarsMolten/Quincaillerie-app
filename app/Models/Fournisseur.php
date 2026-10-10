<?php

namespace App\Models;

use App\Enums\StatutAchat;
use App\Models\Concerns\Journalisable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fournisseur extends Model
{
    use HasFactory, Journalisable, SoftDeletes;

    protected $table = 'fournisseurs';

    protected $fillable = [
        'nom',
        'contact',
        'telephone',
        'email',
        'adresse',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
        ];
    }

    public function achats(): HasMany
    {
        return $this->hasMany(Achat::class, 'fournisseur_id');
    }

    public function scopeActif(Builder $requete): void
    {
        $requete->where('actif', true);
    }

    /** Montant restant dû au fournisseur sur les achats validés. */
    protected function detteTotale(): Attribute
    {
        return Attribute::get(fn (): float => (float) $this->achats()
            ->where('statut', StatutAchat::Valide)
            ->sum('reste_a_payer'));
    }
}
