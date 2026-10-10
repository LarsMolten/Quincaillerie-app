<?php

namespace App\Models;

use App\Enums\StatutFacture;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Facture d'une vente. Une facture émise n'est jamais modifiée ni supprimée (CLAUDE.md §5.6) :
 * la seule évolution permise est son annulation (statut émise → annulée).
 * Une correction passe par l'annulation de la vente ou par un retour.
 */
class Facture extends Model
{
    use HasFactory;

    protected $table = 'factures';

    protected static function booted(): void
    {
        static::updating(function (Facture $facture) {
            $modifies = array_diff(array_keys($facture->getDirty()), ['statut', 'updated_at']);
            $annulation = $facture->getOriginal('statut') === StatutFacture::Emise && $facture->statut === StatutFacture::Annulee;

            if ($modifies !== [] || ! $annulation) {
                throw new LogicException("La facture {$facture->getOriginal('numero')} n'est pas modifiable : seule son annulation est permise.");
            }
        });

        static::deleting(function (Facture $facture) {
            throw new LogicException("La facture {$facture->numero} ne peut pas être supprimée : annulez la vente.");
        });
    }

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
