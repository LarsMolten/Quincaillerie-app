<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    /** Rôle disposant de tous les droits. */
    public const ADMINISTRATEUR = 'Administrateur';

    protected $table = 'roles';

    protected $fillable = [
        'nom',
        'description',
    ];

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
}
