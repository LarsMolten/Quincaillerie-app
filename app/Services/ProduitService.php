<?php

namespace App\Services;

use App\Enums\TypeMouvementStock;
use App\Models\Produit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Création et modification des produits. Le stock n'est jamais modifié ici directement :
 * le stock initial passe par MouvementStockService (mouvement « Stock initial »).
 */
class ProduitService
{
    public function __construct(
        private readonly MouvementStockService $stock,
        private readonly PhotoProduitService $photos,
    ) {}

    public function creer(array $donnees, ?UploadedFile $photo = null, float $stockInitial = 0): Produit
    {
        $chemin = $photo ? $this->photos->enregistrer($photo) : null;

        try {
            return DB::transaction(function () use ($donnees, $chemin, $stockInitial) {
                $donnees['reference'] = filled($donnees['reference'] ?? null)
                    ? $donnees['reference']
                    : $this->prochaineReference();

                $produit = Produit::create([...$donnees, 'image' => $chemin]);

                if ($stockInitial > 0) {
                    $this->stock->enregistrer($produit, TypeMouvementStock::AjustementPositif, $stockInitial, $produit, 'Stock initial');
                }

                return $produit;
            });
        } catch (\Throwable $erreur) {
            // Pas de fichier orphelin si la création échoue
            $this->photos->supprimer($chemin);

            throw $erreur;
        }
    }

    public function modifier(Produit $produit, array $donnees, ?UploadedFile $photo = null, bool $retirerPhoto = false): Produit
    {
        $ancienne = $produit->image;

        if ($photo) {
            $donnees['image'] = $this->photos->enregistrer($photo);
        } elseif ($retirerPhoto) {
            $donnees['image'] = null;
        }

        // La référence vide garde la référence existante
        if (blank($donnees['reference'] ?? null)) {
            unset($donnees['reference']);
        }

        $produit->update($donnees);

        if (array_key_exists('image', $donnees) && $ancienne !== $produit->image) {
            $this->photos->supprimer($ancienne);
        }

        return $produit;
    }

    public function basculerStatut(Produit $produit): Produit
    {
        $produit->update(['actif' => ! $produit->actif]);

        return $produit;
    }

    /** Prochaine référence PRD-NNNNN (verrou pour éviter deux créations simultanées identiques). */
    public function prochaineReference(): string
    {
        $derniere = Produit::withTrashed()
            ->where('reference', 'like', 'PRD-%')
            ->lockForUpdate()
            ->pluck('reference')
            ->map(fn (string $reference) => (int) substr($reference, 4))
            ->max();

        return sprintf('PRD-%05d', ($derniere ?? 0) + 1);
    }
}
