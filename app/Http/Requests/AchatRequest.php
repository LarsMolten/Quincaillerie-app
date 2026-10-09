<?php

namespace App\Http\Requests;

use App\Enums\ModePaiement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Enregistrement d'un achat (droit achats.creer, vérifié par la route).
 * Les règles métier (produits actifs, total, montant payé) sont aussi vérifiées par AchatService.
 */
class AchatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $nombre = fn ($valeur) => filled($valeur) ? str_replace([' ', "\u{00A0}", ','], ['', '', '.'], (string) $valeur) : $valeur;

        $this->merge([
            'montant_paye' => $nombre($this->input('montant_paye')) ?? 0,
            'lignes' => collect($this->input('lignes', []))
                ->map(fn ($ligne) => [
                    'produit_id' => $ligne['produit_id'] ?? null,
                    'quantite' => $nombre($ligne['quantite'] ?? null),
                    'prix_achat' => $nombre($ligne['prix_achat'] ?? null),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function rules(): array
    {
        return [
            'fournisseur_id' => ['required', Rule::exists('fournisseurs', 'id')->where('actif', true)->whereNull('deleted_at')],
            'date_achat' => ['required', 'date', 'before_or_equal:today'],
            'lignes' => ['required', 'array', 'min:1', 'max:200'],
            'lignes.*.produit_id' => ['required', 'integer', Rule::exists('produits', 'id')->where('actif', true)->whereNull('deleted_at')],
            'lignes.*.quantite' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'lignes.*.prix_achat' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'montant_paye' => ['numeric', 'min:0'],
            'mode_paiement' => ['nullable', Rule::requiredIf(fn () => (float) $this->input('montant_paye') > 0), Rule::in(array_map(fn ($m) => $m->value, ModePaiement::encaissements()))],
            'notes' => ['nullable', 'string', 'max:1000'],
            'maj_prix' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validateur) {
                if ($validateur->errors()->isNotEmpty()) {
                    return;
                }
                $total = collect($this->input('lignes'))->sum(fn ($l) => (float) $l['quantite'] * (float) $l['prix_achat']);
                if ((float) $this->input('montant_paye') > round($total, 2) + 0.001) {
                    $validateur->errors()->add('montant_paye', 'Le montant payé ne peut pas dépasser le total de l\'achat ('.format_ar($total).').');
                }
            },
        ];
    }

    public function attributes(): array
    {
        $attributs = [
            'fournisseur_id' => 'fournisseur',
            'date_achat' => 'date de l\'achat',
            'lignes' => 'produits',
            'montant_paye' => 'montant payé',
            'mode_paiement' => 'mode de paiement',
            'notes' => 'notes',
        ];

        // « Ligne 2 : quantité » plutôt que « lignes.1.quantite »
        foreach (array_keys($this->input('lignes', [])) as $index) {
            $numero = $index + 1;
            $attributs["lignes.{$index}.produit_id"] = "ligne {$numero} : produit";
            $attributs["lignes.{$index}.quantite"] = "ligne {$numero} : quantité";
            $attributs["lignes.{$index}.prix_achat"] = "ligne {$numero} : prix d'achat";
        }

        return $attributs;
    }

    public function messages(): array
    {
        return [
            'fournisseur_id.exists' => 'Choisissez un fournisseur actif.',
            'lignes.required' => 'Ajoutez au moins un produit.',
            'lignes.min' => 'Ajoutez au moins un produit.',
            'lignes.*.produit_id.exists' => 'Le produit de la :attribute est introuvable ou désactivé.',
            'lignes.*.quantite.gt' => 'La :attribute doit être supérieure à 0.',
            'mode_paiement.required' => 'Choisissez le mode de paiement du montant versé.',
            'date_achat.before_or_equal' => 'La date de l\'achat ne peut pas être dans le futur.',
        ];
    }
}
