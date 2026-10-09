<?php

namespace App\Http\Requests;

use App\Enums\ModePaiement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation d'une vente envoyée par la caisse (droit ventes.creer, vérifié par la route).
 * Les prix ne sont pas reçus : VenteService les relit sur la fiche produit. Les règles métier
 * (stock, remise, crédit) sont contrôlées par le service.
 */
class VenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $nombre = fn ($valeur) => filled($valeur) ? str_replace([' ', "\u{00A0}", ','], ['', '', '.'], (string) $valeur) : $valeur;

        $this->merge([
            'remise' => $nombre($this->input('remise')) ?? 0,
            'montant_recu' => $nombre($this->input('montant_recu')) ?? 0,
            'lignes' => collect($this->input('lignes', []))
                ->map(fn ($ligne) => [
                    'produit_id' => $ligne['produit_id'] ?? null,
                    'quantite' => $nombre($ligne['quantite'] ?? null),
                    'tarif' => $ligne['tarif'] ?? 'detail',
                    'remise' => $nombre($ligne['remise'] ?? null) ?? 0,
                ])
                ->values()
                ->all(),
        ]);
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')->where('actif', true)->whereNull('deleted_at')],
            'lignes' => ['required', 'array', 'min:1', 'max:200'],
            'lignes.*.produit_id' => ['required', 'integer', Rule::exists('produits', 'id')->where('actif', true)->whereNull('deleted_at')],
            'lignes.*.quantite' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'lignes.*.tarif' => ['required', Rule::in(['detail', 'gros'])],
            'lignes.*.remise' => ['numeric', 'min:0'],
            'remise' => ['numeric', 'min:0'],
            'mode_paiement' => ['required', Rule::enum(ModePaiement::class)],
            'montant_recu' => ['numeric', 'min:0', 'max:10000000000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        $attributs = [
            'client_id' => 'client',
            'lignes' => 'produits',
            'remise' => 'remise',
            'mode_paiement' => 'mode de paiement',
            'montant_recu' => 'montant reçu',
        ];

        foreach (array_keys($this->input('lignes', [])) as $index) {
            $numero = $index + 1;
            $attributs["lignes.{$index}.produit_id"] = "ligne {$numero} : produit";
            $attributs["lignes.{$index}.quantite"] = "ligne {$numero} : quantité";
            $attributs["lignes.{$index}.remise"] = "ligne {$numero} : remise";
        }

        return $attributs;
    }

    public function messages(): array
    {
        return [
            'client_id.exists' => 'Choisissez un client actif.',
            'lignes.required' => 'Le ticket est vide : ajoutez au moins un produit.',
            'lignes.min' => 'Le ticket est vide : ajoutez au moins un produit.',
            'lignes.*.produit_id.exists' => 'Le produit de la :attribute est introuvable ou désactivé.',
            'lignes.*.quantite.gt' => 'La :attribute doit être supérieure à 0.',
            'mode_paiement.required' => 'Choisissez un mode de paiement.',
        ];
    }
}
