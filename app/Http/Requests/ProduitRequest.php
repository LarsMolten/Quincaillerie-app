<?php

namespace App\Http\Requests;

use App\Support\Ean13;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création et modification d'un produit (droits produits.creer / produits.modifier, vérifiés par la route).
 * Un prix de vente inférieur au prix d'achat n'est pas une erreur : le contrôleur affiche un avertissement.
 */
class ProduitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $nettoyer = fn (?string $valeur) => filled($valeur) ? trim($valeur) : null;
        // Les montants saisis peuvent contenir des espaces (« 35 000 »)
        $nombre = fn ($valeur) => filled($valeur) ? str_replace([' ', "\u{00A0}", ','], ['', '', '.'], (string) $valeur) : null;

        $this->merge([
            'reference' => $nettoyer($this->input('reference')),
            'code_barres' => $nettoyer($this->input('code_barres')),
            'nom' => $nettoyer($this->input('nom')),
            'description' => $nettoyer($this->input('description')),
            'prix_achat' => $nombre($this->input('prix_achat')),
            'prix_vente' => $nombre($this->input('prix_vente')),
            'prix_gros' => $nombre($this->input('prix_gros')),
            'stock_minimum' => $nombre($this->input('stock_minimum')) ?? 0,
            'stock_initial' => $nombre($this->input('stock_initial')) ?? 0,
        ]);
    }

    public function rules(): array
    {
        $produit = $this->route('produit');
        $creation = $produit === null;

        return [
            'reference' => ['nullable', 'string', 'max:50', Rule::unique('produits', 'reference')->ignore($produit)],
            'code_barres' => [
                'nullable', 'regex:/^\d{8,14}$/', Rule::unique('produits', 'code_barres')->ignore($produit),
                function (string $attribut, mixed $valeur, Closure $echec) {
                    if (strlen((string) $valeur) === 13 && ! Ean13::estValide((string) $valeur)) {
                        $echec('Ce code EAN-13 est invalide (chiffre de contrôle incorrect). Vérifiez la saisie ou rescannez.');
                    }
                },
            ],
            'nom' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'categorie_id' => [
                'required',
                // Catégorie active, ou la catégorie actuelle du produit (même désactivée depuis)
                Rule::exists('categories', 'id')->whereNull('deleted_at')
                    ->where(fn ($q) => $q->where('actif', true)->when($produit, fn ($q) => $q->orWhere('id', $produit->categorie_id))),
            ],
            'unite_id' => ['required', Rule::exists('unites', 'id')->whereNull('deleted_at')],
            'prix_achat' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'prix_vente' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'prix_gros' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'stock_minimum' => ['numeric', 'min:0', 'max:1000000000'],
            'stock_initial' => $creation ? ['numeric', 'min:0', 'max:1000000000'] : ['prohibited_unless:stock_initial,0'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:4096'],
            'retirer_photo' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'reference' => 'référence',
            'code_barres' => 'code-barres',
            'nom' => 'nom',
            'description' => 'description',
            'categorie_id' => 'catégorie',
            'unite_id' => 'unité',
            'prix_achat' => 'prix d\'achat',
            'prix_vente' => 'prix de vente',
            'prix_gros' => 'prix de gros',
            'stock_minimum' => 'stock minimum',
            'stock_initial' => 'stock initial',
            'photo' => 'photo',
        ];
    }

    public function messages(): array
    {
        return [
            'reference.unique' => 'Cette référence est déjà utilisée par un autre produit.',
            'code_barres.unique' => 'Ce code-barres est déjà attribué à un autre produit.',
            'code_barres.regex' => 'Le code-barres doit contenir de 8 à 14 chiffres.',
            'categorie_id.exists' => 'Choisissez une catégorie active.',
            'unite_id.exists' => 'Choisissez une unité valide.',
            'stock_initial.prohibited_unless' => 'Le stock d\'un produit existant se modifie depuis Stock > Entrées ou Sorties.',
            'photo.max' => 'La photo ne doit pas dépasser 4 Mo.',
        ];
    }

    /** Données à enregistrer (sans les champs techniques). */
    public function donnees(): array
    {
        return collect($this->validated())
            ->except(['stock_initial', 'photo', 'retirer_photo'])
            ->all();
    }
}
