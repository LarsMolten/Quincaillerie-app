<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Unité de vente d'un produit (pièce/pce, kilogramme/kg, mètre/m…).
 */
class Unite extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'unites';

    protected $fillable = [
        'nom',
        'abreviation',
    ];

    public function produits(): HasMany
    {
        return $this->hasMany(Produit::class, 'unite_id');
    }
}
