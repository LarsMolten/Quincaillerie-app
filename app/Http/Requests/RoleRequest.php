<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création et modification d'un rôle (droit roles.gerer). La remise maximale se règle dans Paramètres > Ventes.
 */
class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nom' => is_string($this->input('nom')) ? trim(preg_replace('/\s+/', ' ', $this->input('nom'))) : null,
            'description' => filled($this->input('description')) ? trim((string) $this->input('description')) : null,
            'modele_id' => filled($this->input('modele_id')) ? $this->input('modele_id') : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:60', Rule::unique('roles', 'nom')->ignore($this->route('role'))],
            'description' => ['nullable', 'string', 'max:255'],
            'modele_id' => $this->route('role') ? ['prohibited'] : ['nullable', 'integer', Rule::exists('roles', 'id')],
        ];
    }

    public function attributes(): array
    {
        return ['nom' => 'nom du rôle', 'description' => 'description', 'modele_id' => 'rôle modèle'];
    }

    public function messages(): array
    {
        return ['nom.unique' => 'Un rôle porte déjà ce nom.'];
    }
}
