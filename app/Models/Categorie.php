<?php

namespace App\Models;

use App\Models\Concerns\Journalisable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Categorie extends Model
{
    use HasFactory, Journalisable, SoftDeletes;

    protected $table = 'categories';

    public const JOURNAL_FEMININ = true;

    protected $fillable = [
        'nom',
        'description',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
        ];
    }

    public function produits(): HasMany
    {
        return $this->hasMany(Produit::class, 'categorie_id');
    }

    public function scopeActif(Builder $requete): void
    {
        $requete->where('actif', true);
    }
}
