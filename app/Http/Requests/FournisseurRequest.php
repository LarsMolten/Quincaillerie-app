<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Création et modification d'un fournisseur (droit fournisseurs.gerer, vérifié par la route).
 */
class FournisseurRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $nettoyer = fn (?string $valeur) => filled($valeur) ? trim(preg_replace('/\s+/', ' ', $valeur)) : null;

        $this->merge([
            'nom' => $nettoyer($this->input('nom')),
            'contact' => $nettoyer($this->input('contact')),
            'telephone' => $nettoyer($this->input('telephone')),
            'email' => $nettoyer($this->input('email')),
            'adresse' => $nettoyer($this->input('adresse')),
        ]);
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:150'],
            'contact' => ['nullable', 'string', 'max:150'],
            'telephone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9 .\-]{6,}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'adresse' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nom' => 'nom',
            'contact' => 'personne à contacter',
            'telephone' => 'téléphone',
            'email' => 'adresse email',
            'adresse' => 'adresse',
        ];
    }

    public function messages(): array
    {
        return [
            'telephone.regex' => 'Le numéro de téléphone ne doit contenir que des chiffres, des espaces et éventuellement « + ».',
        ];
    }
}
