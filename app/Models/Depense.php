<?php

namespace App\Models;

use App\Enums\CategorieDepense;
use App\Enums\ModePaiement;
use App\Models\Concerns\Journalisable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Dépense de fonctionnement (salaires, loyer, électricité…), déduite du bénéfice (CLAUDE.md §5.7).
 * Suppression douce, réservée à l'Administrateur et journalisée (DepenseService).
 */
class Depense extends Model
{
    use HasFactory, Journalisable, SoftDeletes;

    protected $table = 'depenses';

    public const JOURNAL_FEMININ = true;

    protected $fillable = [
        'categorie',
        'libelle',
        'montant',
        'date_depense',
        'mode_paiement',
        'utilisateur_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'categorie' => CategorieDepense::class,
            'montant' => 'decimal:2',
            'date_depense' => 'date',
            'mode_paiement' => ModePaiement::class,
        ];
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'utilisateur_id')->withTrashed();
    }
}
