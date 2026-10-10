<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Envoi d'une facture par email (droit factures.voir, vérifié par la route).
 */
class EnvoiFactureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => trim((string) $this->input('email')),
            'message' => filled($this->input('message')) ? trim((string) $this->input('message')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'message' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'email' => 'adresse email',
            'message' => 'message',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Indiquez l\'adresse email du destinataire.',
            'email.email' => 'L\'adresse email n\'est pas valide.',
        ];
    }
}
