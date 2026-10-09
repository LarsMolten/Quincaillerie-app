<?php

namespace App\Services;

use App\Models\JournalActivite;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Enregistre les actions sensibles dans journal_activites :
 * connexions, annulations, remises, ajustements de stock, suppressions.
 */
class JournalService
{
    /**
     * @param  string  $action  ex. « connexion », « vente.annulee »
     * @param  Model|null  $modele  document concerné (vente, produit…)
     * @param  array<string, mixed>  $details  informations utiles à l'audit
     * @param  Utilisateur|null  $utilisateur  auteur, par défaut l'utilisateur connecté
     */
    public function enregistrer(
        string $action,
        ?Model $modele = null,
        array $details = [],
        ?Utilisateur $utilisateur = null,
    ): JournalActivite {
        return JournalActivite::create([
            'utilisateur_id' => ($utilisateur ?? Auth::user())?->getKey(),
            'action' => $action,
            'modele' => $modele?->getMorphClass(),
            'modele_id' => $modele?->getKey(),
            'details' => $details ?: null,
            'adresse_ip' => request()->ip(),
        ]);
    }
}
