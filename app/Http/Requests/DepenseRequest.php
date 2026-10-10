<?php

namespace App\Http\Requests;

use App\Enums\CategorieDepense;
use App\Enums\ModePaiement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Saisie ou modification d'une dépense.
 */
class DepenseRequest extends FormRequest
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
            'libelle' => is_string($this->input('libelle')) ? trim($this->input('libelle')) : $this->input('libelle'),
            'notes' => filled($this->input('notes')) ? trim((string) $this->input('notes')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'categorie' => ['required', Rule::enum(CategorieDepense::class)],
            'libelle' => ['required', 'string', 'max:255'],
            'montant' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'date_depense' => ['required', 'date', 'before_or_equal:today'],
            'mode_paiement' => ['required', Rule::in(array_map(fn (ModePaiement $m) => $m->value, ModePaiement::encaissements()))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'categorie' => 'catégorie',
            'libelle' => 'libellé',
            'montant' => 'montant',
            'date_depense' => 'date',
            'mode_paiement' => 'mode de paiement',
            'notes' => 'notes',
        ];
    }

    public function messages(): array
    {
        return [
            'categorie.required' => 'Choisissez la catégorie.',
            'categorie.enum' => 'Choisissez une catégorie dans la liste.',
            'libelle.required' => 'Indiquez le libellé de la dépense.',
            'montant.gt' => 'Le montant doit être supérieur à 0.',
            'date_depense.before_or_equal' => 'La date ne peut pas être dans le futur.',
        ];
    }
}
