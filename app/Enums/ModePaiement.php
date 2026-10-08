<?php

namespace App\Enums;

/**
 * Mode de règlement d'une vente, d'un paiement ou d'une dépense.
 */
enum ModePaiement: string
{
    case Especes = 'especes';
    case MobileMoney = 'mobile_money';
    case Virement = 'virement';
    case Cheque = 'cheque';
    case Credit = 'credit';

    /** Texte affiché dans l'interface. */
    public function libelle(): string
    {
        return match ($this) {
            self::Especes => 'Espèces',
            self::MobileMoney => 'Mobile Money',
            self::Virement => 'Virement',
            self::Cheque => 'Chèque',
            self::Credit => 'Crédit',
        };
    }

    /** Jeton de couleur sémantique du badge (succes, alerte, danger, info, neutre). */
    public function couleur(): string
    {
        return match ($this) {
            self::Especes => 'succes',
            self::MobileMoney, self::Virement => 'info',
            self::Cheque => 'neutre',
            self::Credit => 'alerte',
        };
    }

    /**
     * Modes d'encaissement réel (sans le crédit), utilisés pour les paiements et les dépenses.
     *
     * @return list<self>
     */
    public static function encaissements(): array
    {
        return array_values(array_filter(self::cases(), fn (self $mode) => $mode !== self::Credit));
    }
}
