<?php

namespace App\Enums;

use Illuminate\Database\Eloquent\Model;

/**
 * État de règlement d'un achat ou d'une vente, déduit de montant_paye / reste_a_payer.
 */
enum StatutPaiement: string
{
    case Paye = 'paye';
    case Partiel = 'partiel';
    case Credit = 'credit';

    public static function de(Model $document): self
    {
        return match (true) {
            (float) $document->reste_a_payer <= 0 => self::Paye,
            (float) $document->montant_paye > 0 => self::Partiel,
            default => self::Credit,
        };
    }

    /** Texte affiché dans l'interface. */
    public function libelle(): string
    {
        return match ($this) {
            self::Paye => 'Payé',
            self::Partiel => 'Partiel',
            self::Credit => 'À crédit',
        };
    }

    /** Jeton de couleur sémantique du badge (succes, alerte, danger, info, neutre). */
    public function couleur(): string
    {
        return match ($this) {
            self::Paye => 'succes',
            self::Partiel => 'alerte',
            self::Credit => 'danger',
        };
    }
}
