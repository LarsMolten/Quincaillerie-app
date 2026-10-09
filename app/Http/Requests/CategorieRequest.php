<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Création et modification d'une catégorie (droit categories.gerer, vérifié par la route).
 */
class CategorieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nom' => trim((string) $this->input('nom')),
            'description' => filled($this->input('description')) ? trim($this->input('description')) : null,
            'actif' => $this->boolean('actif', true),
        ]);
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:100', $this->regleUnique('nom')],
            'description' => ['nullable', 'string', 'max:255'],
            'actif' => ['boolean'],
        ];
    }

    /**
     * À la création, une catégorie supprimée de même nom sera restaurée : elle n'est pas un doublon.
     * En modification, le nom ne doit appartenir à aucune autre catégorie, même supprimée.
     */
    private function regleUnique(string $colonne): Unique
    {
        $regle = Rule::unique('categories', $colonne)->ignore($this->route('categorie'));

        return $this->isMethod('post') ? $regle->withoutTrashed() : $regle;
    }

    public function attributes(): array
    {
        return [
            'nom' => 'nom',
            'description' => 'description',
        ];
    }

    public function messages(): array
    {
        return [
            'nom.unique' => 'Une catégorie porte déjà ce nom.',
        ];
    }
}
