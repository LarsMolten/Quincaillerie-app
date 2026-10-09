<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trace des actions sensibles : annulations, remises, ajustements, suppressions, connexions.
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
