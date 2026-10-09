<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Droit d'accès de la forme « module.action » (ex. « ventes.creer »).
 */
class Droit extends Model
{
    use HasFactory;

    protected $table = 'droits';

    protected $fillable = [
        'code',
        'libelle',
        'module',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_droit', 'droit_id', 'role_id');
    }
}
