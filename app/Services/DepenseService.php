<?php

namespace App\Services;

use App\Exceptions\OperationRefuseeException;
use App\Models\Depense;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Dépenses : saisie, modification, et suppression (douce) réservée à l'Administrateur, journalisée.
 */
class DepenseService
{
    public function __construct(private readonly JournalService $journal) {}

    /** @param  array<string, mixed>  $donnees  données validées (DepenseRequest) */
    public function creer(array $donnees): Depense
    {
        return Depense::create([...$donnees, 'utilisateur_id' => Auth::id()]);
    }

    /** @param  array<string, mixed>  $donnees */
    public function modifier(Depense $depense, array $donnees): Depense
    {
        $depense->update($donnees);

        return $depense;
    }

    public function supprimer(Depense $depense, Utilisateur $utilisateur): void
    {
        if (! $utilisateur->estAdministrateur()) {
            throw new OperationRefuseeException('Seul l\'Administrateur peut supprimer une dépense.');
        }

        DB::transaction(function () use ($depense, $utilisateur) {
            $depense->delete();

            $this->journal->enregistrer('depense.supprimee', $depense, [
                'libelle' => $depense->libelle,
                'categorie' => $depense->categorie->value,
                'montant' => (float) $depense->montant,
                'date' => $depense->date_depense->toDateString(),
            ], $utilisateur);
        });
    }
}
