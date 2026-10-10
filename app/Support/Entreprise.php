<?php

namespace App\Support;

use App\Models\Parametre;
use Illuminate\Support\Facades\Storage;

/**
 * Identité de l'entreprise (paramètres) pour l'en-tête et le pied des documents PDF :
 * factures, tickets, reçus de paiement.
 */
class Entreprise
{
    /**
     * @return array{nom: string, adresse: ?string, telephone: ?string, email: ?string, nif_stat: ?string, pied: ?string, logo: ?string}
     */
    public static function donnees(): array
    {
        return [
            'nom' => (string) Parametre::valeur('nom_entreprise', config('app.name')),
            'adresse' => Parametre::valeur('adresse'),
            'telephone' => Parametre::valeur('telephone'),
            'email' => Parametre::valeur('email'),
            'nif_stat' => Parametre::valeur('nif_stat'),
            'pied' => Parametre::valeur('pied_de_facture'),
            'logo' => self::logo(),
        ];
    }

    /** Logo (paramètre « logo » : chemin sur le disque privé) en data URI, ou null. */
    private static function logo(): ?string
    {
        $chemin = trim((string) Parametre::valeur('logo', ''));

        if ($chemin === '' || ! Storage::disk('local')->exists($chemin)) {
            return null;
        }

        $type = Storage::disk('local')->mimeType($chemin);

        return in_array($type, ['image/png', 'image/jpeg'], true)
            ? 'data:'.$type.';base64,'.base64_encode(Storage::disk('local')->get($chemin))
            : null;
    }
}
