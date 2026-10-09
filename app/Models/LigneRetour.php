<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LigneRetour extends Model
{
    use HasFactory;

    protected $table = 'lignes_retour';

    public $timestamps = false;

    protected $fillable = [
        'retour_id',
        'produit_id',
        'quantite',
        'prix_unitaire',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'quantite' => 'decimal:3',
            'prix_unitaire' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function retour(): BelongsTo
    {
        return $this->belongsTo(Retour::class, 'retour_id');
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class, 'produit_id')->withTrashed();
    }
}
