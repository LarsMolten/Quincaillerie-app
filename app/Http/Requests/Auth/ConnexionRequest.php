<?php

namespace App\Http\Requests\Auth;

use App\Exceptions\CompteInactifException;
use App\Models\Utilisateur;
use App\Services\JournalService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConnexionRequest extends FormRequest
{
    /** Nombre d'essais avant blocage temporaire, par email et adresse IP. */
    public const ESSAIS_MAXIMUM = 5;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'email' => 'adresse email',
            'password' => 'mot de passe',
        ];
    }

    /**
     * Authentifie l'utilisateur : limitation des essais, vérification des identifiants,
     * refus des comptes inactifs (avant toute ouverture de session), puis connexion.
     *
     * @throws ValidationException
     * @throws CompteInactifException
     */
    public function authentifier(JournalService $journal): Utilisateur
    {
        $this->verifierLimite();

        $identifiants = $this->only('email', 'password');

        if (! Auth::validate($identifiants)) {
            RateLimiter::hit($this->cleLimite(), 60);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        /** @var Utilisateur $utilisateur */
        $utilisateur = Auth::getProvider()->retrieveByCredentials($identifiants);

        if (! $utilisateur->actif) {
            RateLimiter::hit($this->cleLimite(), 60);
            $journal->enregistrer('connexion.refusee', $utilisateur, ['motif' => 'compte inactif'], $utilisateur);

            throw new CompteInactifException;
        }

        Auth::login($utilisateur, $this->boolean('souvenir'));
        RateLimiter::clear($this->cleLimite());

        return $utilisateur;
    }

    /**
     * @throws ValidationException
     */
    private function verifierLimite(): void
    {
        if (! RateLimiter::tooManyAttempts($this->cleLimite(), self::ESSAIS_MAXIMUM)) {
            return;
        }

        event(new Lockout($this));

        $secondes = RateLimiter::availableIn($this->cleLimite());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', ['seconds' => $secondes, 'minutes' => ceil($secondes / 60)]),
        ]);
    }

    private function cleLimite(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
