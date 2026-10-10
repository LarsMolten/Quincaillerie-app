<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Réinitialisation du mot de passe d'un compte par un administrateur (droit utilisateurs.gerer).
 */
class MotDePasseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }

    public function attributes(): array
    {
        return ['password' => 'nouveau mot de passe'];
    }
}
