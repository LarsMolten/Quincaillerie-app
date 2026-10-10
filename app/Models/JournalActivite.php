<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Trace des actions : connexions, annulations, remises, ajustements, et (trait Journalisable)
 * créations, modifications, suppressions. Non modifiable.
 */
class JournalActivite extends Model
{
    use HasFactory;

    /** Journal non modifiable : pas de colonne updated_at. */
    public const UPDATED_AT = null;

    protected $table = 'journal_activites';

    protected $fillable = [
        'utilisateur_id',
        'action',
        'modele',
        'modele_id',
        'details',
        'adresse_ip',
    ];

    protected static function booted(): void
    {
        // Journal non modifiable : une entrée ne se corrige ni ne se supprime
        static::updating(fn () => throw new LogicException('Le journal d\'activité ne peut pas être modifié.'));
        static::deleting(fn () => throw new LogicException('Le journal d\'activité ne peut pas être supprimé.'));
    }

    protected function casts(): array
    {
        return [
            'details' => 'array',
        ];
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'utilisateur_id')->withTrashed();
    }
}
