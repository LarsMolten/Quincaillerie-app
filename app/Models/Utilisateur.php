<?php

namespace App\Models;

use App\Enums\PreferenceTheme;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Compte de connexion à l'application (remplace le modèle User de Laravel).
 */
class Utilisateur extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

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
            'preference_theme' => PreferenceTheme::class,
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function ventes(): HasMany
    {
        return $this->hasMany(Vente::class, 'utilisateur_id');
    }

    public function achats(): HasMany
    {
        return $this->hasMany(Achat::class, 'utilisateur_id');
    }

    public function mouvementsStock(): HasMany
    {
        return $this->hasMany(MouvementStock::class, 'utilisateur_id');
    }

    public function activites(): HasMany
    {
        return $this->hasMany(JournalActivite::class, 'utilisateur_id');
    }

    public function estAdministrateur(): bool
    {
        return (bool) $this->role?->estAdministrateur();
    }

    /** Initiales pour l'avatar : « Rakotonirina Andry » → « RA ». */
    protected function initiales(): Attribute
    {
        return Attribute::get(fn (): string => collect(preg_split('/\s+/', trim($this->nom)))
            ->filter()
            ->take(2)
            ->map(fn (string $mot) => mb_strtoupper(mb_substr($mot, 0, 1)))
            ->implode(''));
    }

    /** Prénom affiché dans les salutations (dernier mot du nom malgache : « Rakotonirina Andry » → « Andry »). */
    protected function prenom(): Attribute
    {
        return Attribute::get(fn (): string => collect(preg_split('/\s+/', trim($this->nom)))->filter()->last() ?? $this->nom);
    }

    /**
     * Indique si l'utilisateur possède le droit demandé (ex. « ventes.creer »).
     * Un compte inactif n'a aucun droit ; l'Administrateur les a tous.
     */
    public function aDroit(string $code): bool
    {
        if (! $this->actif || ! $this->role) {
            return false;
        }

        if ($this->role->estAdministrateur()) {
            return true;
        }

        // Les droits du rôle sont chargés une seule fois par requête
        return $this->role->droits->contains('code', $code);
    }
}
