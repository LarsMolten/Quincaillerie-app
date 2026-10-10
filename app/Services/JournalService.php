<?php

namespace App\Services;

use App\Models\JournalActivite;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Enregistre les actions dans journal_activites : connexions, annulations, remises, ajustements de stock,
 * et (trait Journalisable) créations, modifications, suppressions et restaurations.
 */
class JournalService
{
    /** Journalisation suspendue (seeders : données de base et de démonstration). */
    private static bool $suspendu = false;

    /**
     * @param  string  $action  ex. « connexion », « vente.annule »
     * @param  Model|null  $modele  document concerné (vente, produit…)
     * @param  array<string, mixed>  $details  informations utiles à l'audit
     * @param  Utilisateur|null  $utilisateur  auteur, par défaut l'utilisateur connecté
     */
    public function enregistrer(
        string $action,
        ?Model $modele = null,
        array $details = [],
        ?Utilisateur $utilisateur = null,
    ): ?JournalActivite {
        if (self::$suspendu) {
            return null;
        }

        return JournalActivite::create([
            'utilisateur_id' => ($utilisateur ?? Auth::user())?->getKey(),
            'action' => $action,
            'modele' => $modele?->getMorphClass(),
            'modele_id' => $modele?->getKey(),
            'details' => $details ?: null,
            'adresse_ip' => request()->ip(),
        ]);
    }

    /**
     * Exécute $operation sans rien journaliser (ex. remplissage de la base par les seeders).
     *
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     */
    public static function sansJournal(callable $operation): mixed
    {
        $avant = self::$suspendu;
        self::$suspendu = true;

        try {
            return $operation();
        } finally {
            self::$suspendu = $avant;
        }
    }
}
