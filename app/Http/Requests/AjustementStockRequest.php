<?php

namespace App\Http\Requests;

use App\Enums\TypeMouvementStock;
use App\Services\AjustementStockService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ajustement manuel du stock : produit actif, type (ajustement ou perte), quantité, motif obligatoire.
 */
class AjustementStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $quantite = $this->input('quantite');

        $this->merge([
            'quantite' => filled($quantite) ? str_replace([' ', "\u{00A0}", ','], ['', '', '.'], (string) $quantite) : $quantite,
            'motif' => is_string($this->input('motif')) ? trim($this->input('motif')) : $this->input('motif'),
        ]);
    }

    public function rules(): array
    {
        return [
            'produit_id' => ['required', 'integer', Rule::exists('produits', 'id')->whereNull('deleted_at')],
            'type' => ['required', Rule::in(array_map(fn (TypeMouvementStock $t) => $t->value, AjustementStockService::TYPES))],
            'quantite' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'motif' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'produit_id' => 'produit',
            'type' => 'type d\'ajustement',
            'quantite' => 'quantité',
            'motif' => 'motif',
        ];
    }

    public function messages(): array
    {
        return [
            'produit_id.required' => 'Choisissez le produit.',
            'quantite.gt' => 'La quantité doit être supérieure à 0.',
            'motif.required' => 'Indiquez le motif de l\'ajustement.',
            'motif.min' => 'Le motif doit être plus précis (5 caractères au moins).',
        ];
    }
}
