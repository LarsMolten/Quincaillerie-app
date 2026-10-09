<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LigneAchat extends Model
{
    use HasFactory;

    protected $table = 'lignes_achat';

    public $timestamps = false;

    protected $fillable = [
        'achat_id',
        'produit_id',
        'quantite',
        'prix_achat',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'quantite' => 'decimal:3',
            'prix_achat' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function achat(): BelongsTo
    {
        return $this->belongsTo(Achat::class, 'achat_id');
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class, 'produit_id')->withTrashed();
    }
}
