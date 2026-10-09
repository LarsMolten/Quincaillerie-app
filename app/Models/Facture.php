<?php

namespace App\Models;

use App\Enums\StatutFacture;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Facture d'une vente. Une facture émise n'est jamais modifiée : elle est annulée.
 */
class Facture extends Model
{
    use HasFactory;

    protected $table = 'factures';

    protected $fillable = [
        'numero',
        'vente_id',
        'date_emission',
        'total',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'date_emission' => 'datetime',
            'total' => 'decimal:2',
            'statut' => StatutFacture::class,
        ];
    }

    public function vente(): BelongsTo
    {
        return $this->belongsTo(Vente::class, 'vente_id');
    }
}
