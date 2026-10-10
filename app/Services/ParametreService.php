<?php

namespace App\Services;

use App\Models\Parametre;
use App\Models\Role;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Paramètres de l'application (Administration > Paramètres, droit parametres.gerer).
 * Chaque valeur modifiée est journalisée (trait Journalisable de Parametre : clé, ancienne et nouvelle valeur).
 * Le logo est stocké sur le disque privé (local) et lu par App\Support\Entreprise pour les PDF.
 */
class ParametreService
{
    /** Dossier du logo sur le disque privé. */
    private const DOSSIER_LOGO = 'parametres';

    /**
     * Enregistre des paramètres clé => valeur (seules les valeurs réellement changées sont écrites).
     *
     * @param  array<string, scalar|null>  $valeurs
     */
    public function enregistrer(array $valeurs): void
    {
        DB::transaction(function () use ($valeurs) {
            foreach ($valeurs as $cle => $valeur) {
                $valeur = is_bool($valeur) ? ($valeur ? '1' : '0') : ($valeur === null ? '' : (string) $valeur);
                $parametre = Parametre::firstOrNew(['cle' => $cle]);
                $parametre->valeur = $valeur;
                $parametre->save();
            }
        });

        Parametre::viderCache();
    }

    /**
     * Remise maximale par rôle (en %, null = plafond général).
     *
     * @param  array<int|string, float|string|null>  $remises  role_id => remise
     */
    public function enregistrerRemises(array $remises): void
    {
        DB::transaction(function () use ($remises) {
            foreach (Role::whereIn('id', array_keys($remises))->get() as $role) {
                $remise = $remises[$role->id];
                $role->update(['remise_max' => $remise === null || $remise === '' ? null : $remise]);
            }
        });
    }

    /** Remplace le logo (l'ancien fichier est supprimé après l'enregistrement). */
    public function remplacerLogo(UploadedFile $fichier): void
    {
        $ancien = (string) Parametre::valeur('logo', '');
        $chemin = $fichier->storeAs(self::DOSSIER_LOGO, 'logo-'.now()->format('YmdHis').'.'.$fichier->extension(), 'local');

        $this->enregistrer(['logo' => $chemin]);
        $this->supprimerFichier($ancien);
    }

    public function retirerLogo(): void
    {
        $ancien = (string) Parametre::valeur('logo', '');
        $this->enregistrer(['logo' => '']);
        $this->supprimerFichier($ancien);
    }

    private function supprimerFichier(string $chemin): void
    {
        // Seulement un fichier du dossier du logo (jamais un chemin arbitraire venu de la base)
        if ($chemin !== '' && str_starts_with($chemin, self::DOSSIER_LOGO.'/')) {
            Storage::disk('local')->delete($chemin);
        }
    }
}
