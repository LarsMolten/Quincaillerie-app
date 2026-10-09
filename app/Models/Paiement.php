<?php

namespace App\Models;

use App\Enums\ModePaiement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Règlement rattaché à une vente (encaissement) ou à un achat (décaissement).
 */
class Paiement extends Model
{
    use HasFactory;

    protected $table = 'paiements';

    protected $fillable = [
        'payable_type',
        'payable_id',
        'montant',
        'mode',
        'reference',
        'date_paiement',
        'utilisateur_id',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'mode' => ModePaiement::class,
            'date_paiement' => 'datetime',
        ];
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'utilisateur_id')->withTrashed();
    }
}
