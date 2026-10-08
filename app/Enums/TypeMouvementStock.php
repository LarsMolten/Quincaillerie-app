<?php

namespace App\Enums;

/**
 * Nature d'un mouvement de stock.
 */
enum TypeMouvementStock: string
{
    case Achat = 'achat';
    case Vente = 'vente';
    case RetourClient = 'retour_client';
    case RetourFournisseur = 'retour_fournisseur';
    case AjustementPositif = 'ajustement_positif';
    case AjustementNegatif = 'ajustement_negatif';
    case Perte = 'perte';

    /** Texte affiché dans l'interface. */
    public function libelle(): string
    {
        return match ($this) {
            self::Achat => 'Achat',
            self::Vente => 'Vente',
            self::RetourClient => 'Retour client',
            self::RetourFournisseur => 'Retour fournisseur',
            self::AjustementPositif => 'Ajustement (+)',
            self::AjustementNegatif => 'Ajustement (−)',
            self::Perte => 'Perte',
        };
    }

    /** Jeton de couleur sémantique du badge (succes, alerte, danger, info, neutre). */
    public function couleur(): string
    {
        return match ($this) {
            self::Achat, self::RetourClient => 'succes',
            self::Vente => 'info',
            self::RetourFournisseur => 'neutre',
            self::AjustementPositif, self::AjustementNegatif => 'alerte',
            self::Perte => 'danger',
        };
    }

    /** Sens imposé par le type : entrée ou sortie de stock. */
    public function sens(): SensMouvement
    {
        return match ($this) {
            self::Achat, self::RetourClient, self::AjustementPositif => SensMouvement::Entree,
            self::Vente, self::RetourFournisseur, self::AjustementNegatif, self::Perte => SensMouvement::Sortie,
        };
    }
}
