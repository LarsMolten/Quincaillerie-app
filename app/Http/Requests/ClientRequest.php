<?php

namespace App\Http\Requests;

use App\Models\Client;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Création et modification d'un client (droit clients.gerer, vérifié par la route).
 * Plafond de crédit vide = aucun crédit autorisé. Le « Client comptoir » est protégé par le contrôleur.
 */
class ClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $nettoyer = fn (?string $valeur) => filled($valeur) ? trim(preg_replace('/\s+/', ' ', $valeur)) : null;
        $plafond = $this->input('plafond_credit');

        $this->merge([
            'nom' => $nettoyer($this->input('nom')),
            'telephone' => $nettoyer($this->input('telephone')),
            'email' => $nettoyer($this->input('email')),
            'adresse' => $nettoyer($this->input('adresse')),
            'plafond_credit' => filled($plafond) ? str_replace([' ', "\u{00A0}"], '', (string) $plafond) : null,
        ]);
    }

    public function rules(): array
    {
        $client = $this->route('client');

        return [
            'nom' => [
                'required', 'string', 'max:150',
                // Le nom « Client comptoir » est réservé au client par défaut
                function (string $attribut, mixed $valeur, Closure $echec) use ($client) {
                    if (mb_strtolower((string) $valeur) === mb_strtolower(Client::COMPTOIR) && ! $client?->estComptoir()) {
                        $echec('Le nom « '.Client::COMPTOIR.' » est réservé au client par défaut.');
                    }
                },
            ],
            'telephone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 .\-]{6,}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'plafond_credit' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nom' => 'nom',
            'telephone' => 'téléphone',
            'email' => 'adresse email',
            'adresse' => 'adresse',
            'plafond_credit' => 'plafond de crédit',
        ];
    }

    public function messages(): array
    {
        return [
            'telephone.regex' => 'Le numéro de téléphone ne doit contenir que des chiffres, des espaces et éventuellement « + ».',
        ];
    }
}
