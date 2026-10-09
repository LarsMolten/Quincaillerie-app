<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LigneInventaire extends Model
{
    use HasFactory;

    protected $table = 'lignes_inventaire';

    public $timestamps = false;

    protected $fillable = [
        'inventaire_id',
        'produit_id',
        'stock_theorique',
        'stock_compte',
        'ecart',
    ];

    protected function casts(): array
    {
        return [
            'stock_theorique' => 'decimal:3',
            'stock_compte' => 'decimal:3',
            'ecart' => 'decimal:3',
        ];
    }

    public function inventaire(): BelongsTo
    {
        return $this->belongsTo(Inventaire::class, 'inventaire_id');
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class, 'produit_id')->withTrashed();
    }
}
