<?php

namespace App\Http\Requests;

use App\Enums\ModePaiement;
use App\Services\RetourService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un retour (assistant). Les quantités retournables, les montants et le stock
 * sont contrôlés par RetourService dans la transaction.
 */
class RetourRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'precision' => filled($this->input('precision')) ? trim((string) $this->input('precision')) : null,
        ]);
    }

    public function rules(): array
    {
        $type = $this->input('type') === 'fournisseur' ? 'fournisseur' : 'client';

        return [
            'type' => ['required', Rule::in(['client', 'fournisseur'])],
            'vente_id' => ['exclude_unless:type,client', 'required', 'integer', 'exists:ventes,id'],
            'achat_id' => ['exclude_unless:type,fournisseur', 'required', 'integer', 'exists:achats,id'],
            'lignes' => ['required', 'array', 'min:1'],
            'lignes.*.produit_id' => ['required', 'integer', 'distinct'],
            'lignes.*.quantite' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'motif' => ['required', Rule::in(RetourService::MOTIFS[$type])],
            'precision' => ['nullable', 'required_if:motif,Autre', 'string', 'max:200'],
            'mode_remboursement' => ['nullable', Rule::in(array_map(fn (ModePaiement $m) => $m->value, ModePaiement::encaissements()))],
        ];
    }

    public function attributes(): array
    {
        return [
            'lignes' => 'produits retournés',
            'lignes.*.quantite' => 'quantité',
            'motif' => 'motif',
            'precision' => 'précision du motif',
            'mode_remboursement' => 'mode de remboursement',
        ];
    }

    public function messages(): array
    {
        return [
            'lignes.required' => 'Indiquez au moins un produit à retourner.',
            'lignes.*.quantite.gt' => 'La quantité retournée doit être supérieure à 0.',
            'motif.required' => 'Choisissez le motif du retour.',
            'motif.in' => 'Choisissez un motif dans la liste.',
            'precision.required_if' => 'Précisez le motif du retour.',
            'vente_id.required' => 'Choisissez la vente d\'origine.',
            'achat_id.required' => 'Choisissez l\'achat d\'origine.',
        ];
    }

    /** Motif enregistré : « Motif courant — précision », ou la précision seule pour « Autre ». */
    public function motifComplet(): string
    {
        $motif = (string) $this->validated('motif');
        $precision = $this->validated('precision');

        return match (true) {
            $motif === 'Autre' => (string) $precision,
            filled($precision) => "{$motif} — {$precision}",
            default => $motif,
        };
    }

    /** @return array<int, float> [produit_id => quantité] */
    public function quantites(): array
    {
        return collect($this->validated('lignes'))
            ->mapWithKeys(fn (array $ligne) => [(int) $ligne['produit_id'] => (float) $ligne['quantite']])
            ->all();
    }
}
