<?php

namespace App\Http\Requests;

use App\Enums\ModePaiement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Paiement d'un document (achat, plus tard vente). Le plafond (reste à payer) est vérifié par PaiementService.
 */
class PaiementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $montant = $this->input('montant');

        $this->merge([
            'montant' => filled($montant) ? str_replace([' ', "\u{00A0}", ','], ['', '', '.'], (string) $montant) : $montant,
            'reference' => filled($this->input('reference')) ? trim($this->input('reference')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'montant' => ['required', 'numeric', 'gt:0'],
            'mode' => ['required', Rule::in(array_map(fn ($m) => $m->value, ModePaiement::encaissements()))],
            'reference' => ['nullable', 'string', 'max:100'],
            'date_paiement' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    public function attributes(): array
    {
        return [
            'montant' => 'montant',
            'mode' => 'mode de paiement',
            'reference' => 'référence',
            'date_paiement' => 'date du paiement',
        ];
    }

    public function messages(): array
    {
        return [
            'montant.gt' => 'Le montant doit être supérieur à 0.',
            'date_paiement.before_or_equal' => 'La date du paiement ne peut pas être dans le futur.',
        ];
    }
}
