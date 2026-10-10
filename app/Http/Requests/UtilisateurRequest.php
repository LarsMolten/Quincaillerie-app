<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Création et modification d'un compte (droit utilisateurs.gerer, vérifié par la route).
 * Le mot de passe n'est demandé qu'à la création ; ensuite : « Réinitialiser le mot de passe ».
 * Les règles de sécurité (dernier administrateur, propre compte) sont dans UtilisateurService.
 */
class UtilisateurRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $nettoyer = fn (mixed $valeur) => is_string($valeur) && trim($valeur) !== '' ? trim(preg_replace('/\s+/', ' ', $valeur)) : null;

        $this->merge([
            'nom' => $nettoyer($this->input('nom')),
            'email' => is_string($this->input('email')) ? mb_strtolower(trim($this->input('email'))) : null,
            'telephone' => $nettoyer($this->input('telephone')),
        ]);
    }

    public function rules(): array
    {
        $utilisateur = $this->route('utilisateur');

        return [
            'nom' => ['required', 'string', 'max:150'],
            // Unique y compris parmi les comptes supprimés (l'historique garde leur adresse)
            'email' => ['required', 'email', 'max:150', Rule::unique('utilisateurs', 'email')->ignore($utilisateur)],
            'telephone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 .\-]{6,}$/'],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
            'password' => $utilisateur ? ['prohibited'] : ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'actif' => $utilisateur ? ['prohibited'] : ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nom' => 'nom',
            'email' => 'adresse email',
            'telephone' => 'téléphone',
            'role_id' => 'rôle',
            'password' => 'mot de passe',
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Cette adresse email est déjà utilisée par un autre compte (éventuellement supprimé).',
        ];
    }
}
