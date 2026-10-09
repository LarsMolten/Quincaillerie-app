<?php

namespace App\Models;

use App\Enums\StatutInventaire;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Inventaire extends Model
{
    use HasFactory;

    protected $table = 'inventaires';

    protected $fillable = [
        'numero',
        'date_inventaire',
        'statut',
        'utilisateur_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_inventaire' => 'date',
            'statut' => StatutInventaire::class,
        ];
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'utilisateur_id')->withTrashed();
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneInventaire::class, 'inventaire_id');
    }

    /** Ajustements de stock générés à la validation de l'inventaire. */
    public function mouvementsStock(): MorphMany
    {
        return $this->morphMany(MouvementStock::class, 'reference');
    }
}
