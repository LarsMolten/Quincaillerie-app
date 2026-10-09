<?php

namespace App\Http\Requests;

use App\Models\Unite;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Validator;

/**
 * Création et modification d'une unité (droit categories.gerer, vérifié par la route).
 */
class UniteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nom' => trim((string) $this->input('nom')),
            'abreviation' => trim((string) $this->input('abreviation')),
        ]);
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:50', $this->regleUnique('nom')],
            'abreviation' => ['required', 'string', 'max:20', $this->regleUnique('abreviation')],
        ];
    }

    /**
     * À la création, une unité supprimée correspondante sera restaurée : elle n'est pas un doublon.
     * En modification, la valeur ne doit appartenir à aucune autre unité, même supprimée.
     */
    private function regleUnique(string $colonne): Unique
    {
        $regle = Rule::unique('unites', $colonne)->ignore($this->route('unite'));

        return $this->isMethod('post') ? $regle->withoutTrashed() : $regle;
    }

    /**
     * Les index uniques portent aussi sur les unités supprimées : on ne peut réutiliser
     * un nom et une abréviation que s'ils appartiennent à la même unité supprimée (elle sera restaurée).
     */
    public function after(): array
    {
        return [
            function (Validator $validateur) {
                if (! $this->isMethod('post') || $validateur->errors()->isNotEmpty()) {
                    return;
                }

                $supprimees = Unite::onlyTrashed()
                    ->where(fn ($requete) => $requete->where('nom', $this->input('nom'))->orWhere('abreviation', $this->input('abreviation')))
                    ->count();

                if ($supprimees > 1) {
                    $validateur->errors()->add('nom', 'Ce nom et cette abréviation appartiennent à deux unités supprimées différentes : choisissez-en d\'autres.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'nom' => 'nom',
            'abreviation' => 'abréviation',
        ];
    }

    public function messages(): array
    {
        return [
            'nom.unique' => 'Une unité porte déjà ce nom.',
            'abreviation.unique' => 'Une unité utilise déjà cette abréviation.',
        ];
    }
}
