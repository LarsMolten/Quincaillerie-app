<?php

namespace App\Models;

use App\Enums\CategorieDepense;
use App\Enums\ModePaiement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Depense extends Model
{
    use HasFactory;

    protected $table = 'depenses';

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
