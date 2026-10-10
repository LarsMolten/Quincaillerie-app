<?php

namespace App\Models;

use App\Enums\StatutInventaire;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

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

    /** Les lignes d'un inventaire validé ne changent plus (le statut est relu en base). */
    protected static function booted(): void
    {
        static::updating(function (LigneInventaire $ligne) {
            if (Inventaire::whereKey($ligne->inventaire_id)->first(['statut'])?->statut === StatutInventaire::Valide) {
                throw new LogicException('Cet inventaire est validé : ses comptages ne sont plus modifiables.');
            }
        });
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
