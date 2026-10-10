<?php

namespace App\Http\Requests;

use App\Enums\CouleurAccent;
use App\Services\NumerotationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Enregistrement d'un onglet des paramètres (droit parametres.gerer) : entreprise, facturation, ventes, apparence.
 */
class ParametresRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $nettoyer = fn (mixed $valeur) => is_string($valeur) && trim($valeur) !== '' ? trim($valeur) : null;
        $nombre = fn (mixed $valeur) => is_string($valeur) && trim($valeur) !== '' ? str_replace([' ', "\u{00A0}", ','], ['', '', '.'], trim($valeur)) : null;

        match ($this->route('onglet')) {
            'entreprise' => $this->merge(collect(['nom_entreprise', 'adresse', 'telephone', 'email', 'nif_stat'])
                ->mapWithKeys(fn (string $cle) => [$cle => $nettoyer($this->input($cle))])->all()),
            'facturation' => $this->merge([
                ...collect(NumerotationService::PREFIXES)
                    ->mapWithKeys(fn (array $p) => [$p[0] => is_string($this->input($p[0])) ? mb_strtoupper(trim($this->input($p[0]))) : null])
                    ->all(),
                'taux_tva' => $nombre($this->input('taux_tva')),
                'pied_de_facture' => $nettoyer($this->input('pied_de_facture')),
            ]),
            'ventes' => $this->merge([
                'remise_max_pourcentage' => $nombre($this->input('remise_max_pourcentage')),
                'remises' => collect((array) $this->input('remises', []))->map($nombre)->all(),
            ]),
            default => null,
        };
    }

    public function rules(): array
    {
        return match ($this->route('onglet')) {
            'entreprise' => [
                'nom_entreprise' => ['required', 'string', 'max:150'],
                'adresse' => ['nullable', 'string', 'max:255'],
                'telephone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 .\-]{6,}$/'],
                'email' => ['nullable', 'email', 'max:150'],
                'nif_stat' => ['nullable', 'string', 'max:100'],
                'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:1024', 'dimensions:max_width=2000,max_height=2000'],
                'retirer_logo' => ['boolean'],
            ],
            'facturation' => [
                // Préfixes : 2 à 5 lettres majuscules, tous différents
                ...collect(NumerotationService::PREFIXES)
                    ->mapWithKeys(fn (array $p) => [$p[0] => ['required', 'regex:/^[A-Z]{2,5}$/', function (string $attribut, mixed $valeur, \Closure $echec) {
                        $autres = collect(NumerotationService::PREFIXES)->pluck(0)->reject(fn (string $cle) => $cle === $attribut);
                        if ($autres->contains(fn (string $cle) => $this->input($cle) === $valeur)) {
                            $echec('Ce préfixe est déjà utilisé par un autre type de document.');
                        }
                    }]])
                    ->all(),
                'taux_tva' => ['required', 'numeric', 'min:0', 'max:100'],
                'format_facture' => ['required', Rule::in(['a4', 'ticket'])],
                'pied_de_facture' => ['nullable', 'string', 'max:500'],
            ],
            'ventes' => [
                'remise_max_pourcentage' => ['required', 'numeric', 'min:0', 'max:100'],
                'remises' => ['array'],
                'remises.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'stock_negatif_autorise' => ['boolean'],
            ],
            'apparence' => [
                'theme_defaut' => ['required', Rule::in(['clair', 'sombre', 'auto'])],
                'couleur_accent' => ['required', Rule::enum(CouleurAccent::class)],
            ],
            default => [],
        };
    }

    public function attributes(): array
    {
        return [
            'nom_entreprise' => 'nom de l\'entreprise',
            'telephone' => 'téléphone',
            'email' => 'adresse email',
            'nif_stat' => 'NIF / STAT',
            'logo' => 'logo',
            ...collect(NumerotationService::PREFIXES)->mapWithKeys(fn (array $p) => [$p[0] => 'préfixe ('.mb_strtolower($p[2]).')'])->all(),
            'taux_tva' => 'taux de TVA',
            'format_facture' => 'format de facture',
            'pied_de_facture' => 'pied de facture',
            'remise_max_pourcentage' => 'remise maximale générale',
            'remises.*' => 'remise maximale du rôle',
            'theme_defaut' => 'thème par défaut',
            'couleur_accent' => 'couleur d\'accent',
        ];
    }

    public function messages(): array
    {
        return [
            'regex' => 'Le :attribute doit contenir 2 à 5 lettres majuscules (ex. FAC).',
            'telephone.regex' => 'Le numéro de téléphone n\'est pas valide.',
            'logo.dimensions' => 'Le logo ne doit pas dépasser 2000 × 2000 pixels.',
        ];
    }
}
