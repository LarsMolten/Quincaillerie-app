<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Compte de connexion à l'application (remplace le modèle User de Laravel).
 * Les relations (rôle, droits) sont ajoutées avec les autres modèles.
 */
class Utilisateur extends Authenticatable
{
    use Notifiable, SoftDeletes;

    protected $table = 'utilisateurs';

    protected $fillable = [
        'nom',
        'email',
        'telephone',
        'password',
        'role_id',
        'actif',
        'preference_theme',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'actif' => 'boolean',
        ];
    }
}
