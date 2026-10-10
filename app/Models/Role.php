<?php

namespace App\Models;

use App\Models\Concerns\Journalisable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory, Journalisable;

    /** Rôle disposant de tous les droits. */
    public const ADMINISTRATEUR = 'Administrateur';

    /** Rôles livrés avec l'application : ni supprimables ni renommables (référencés par le code). */
    public const PAR_DEFAUT = [self::ADMINISTRATEUR, 'Responsable', 'Vendeur/Caissier', 'Magasinier'];

    protected $table = 'roles';

    protected $fillable = [
        'nom',
        'description',
        'remise_max',
    ];

    protected function casts(): array
    {
        return [
            'remise_max' => 'decimal:2',
        ];
    }

    public function droits(): BelongsToMany
    {
        return $this->belongsToMany(Droit::class, 'role_droit', 'role_id', 'droit_id');
    }

    public function utilisateurs(): HasMany
    {
        return $this->hasMany(Utilisateur::class, 'role_id');
    }

    public function estAdministrateur(): bool
    {
        return $this->nom === self::ADMINISTRATEUR;
    }

    public function estParDefaut(): bool
    {
        return in_array($this->nom, self::PAR_DEFAUT, true);
    }

    /** Remise maximale (en %) des vendeurs de ce rôle : la sienne, sinon le plafond général. */
    public function plafondRemise(): float
    {
        return $this->remise_max !== null
            ? (float) $this->remise_max
            : (float) Parametre::valeur('remise_max_pourcentage', 0);
    }
}
