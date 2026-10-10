<?php

namespace App\Enums;

use App\Models\Parametre;

/**
 * Couleur d'accent de l'application (Paramètres > Apparence).
 * À l'écran : attribut data-accent sur <html>, qui redéfinit les jetons --primaire… (resources/css/app.css).
 * Dans les PDF et les emails (DomPDF, pas d'OKLCH) : palette hexadécimale équivalente (pdf()).
 * Chaque teinte respecte un contraste AA en modes clair et sombre.
 */
enum CouleurAccent: string
{
    case Orange = 'orange';
    case Bleu = 'bleu';
    case Vert = 'vert';
    case Violet = 'violet';
    case Ardoise = 'ardoise';

    /** Couleur en vigueur (paramètre « couleur_accent »), orange sécurité par défaut. */
    public static function courante(): self
    {
        return self::tryFrom((string) rescue(fn () => Parametre::valeur('couleur_accent'), null, false)) ?? self::Orange;
    }

    public function libelle(): string
    {
        return match ($this) {
            self::Orange => 'Orange sécurité',
            self::Bleu => 'Bleu',
            self::Vert => 'Vert',
            self::Violet => 'Violet',
            self::Ardoise => 'Ardoise',
        };
    }

    /**
     * Palette des documents PDF et des emails :
     * principal (bandeaux, filets), fonce (nom de l'entreprise), sombre (texte sur fond doux), doux et moyen (fonds).
     *
     * @return array{principal: string, fonce: string, sombre: string, doux: string, moyen: string}
     */
    public function pdf(): array
    {
        return match ($this) {
            self::Orange => ['principal' => '#fe7802', 'fonce' => '#c25400', 'sombre' => '#8a3c00', 'doux' => '#fff3e6', 'moyen' => '#fde2c4'],
            self::Bleu => ['principal' => '#1570d1', 'fonce' => '#1558ad', 'sombre' => '#173f81', 'doux' => '#e6f4ff', 'moyen' => '#c6e1ff'],
            self::Vert => ['principal' => '#1a8a51', 'fonce' => '#106c3e', 'sombre' => '#07502c', 'doux' => '#e0f9e8', 'moyen' => '#baecca'],
            self::Violet => ['principal' => '#7d51d2', 'fonce' => '#653eae', 'sombre' => '#4a2b83', 'doux' => '#f4eeff', 'moyen' => '#e3d7fb'],
            self::Ardoise => ['principal' => '#47566c', 'fonce' => '#3a495d', 'sombre' => '#2a3647', 'doux' => '#eef2f7', 'moyen' => '#d8dfe8'],
        };
    }
}
