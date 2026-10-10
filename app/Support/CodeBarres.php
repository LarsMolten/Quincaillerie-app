<?php

namespace App\Support;

use Picqer\Barcode\BarcodeGeneratorPNG;
use Picqer\Barcode\BarcodeGeneratorSVG;

/**
 * Dessin des codes-barres : EAN-13 si le code est un EAN valide, sinon Code 128
 * (codes internes, numéros de facture FAC-AAAA-NNNNN…).
 */
class CodeBarres
{
    /** SVG en ligne : la couleur suit le texte courant (thème clair ou sombre). */
    public static function svg(string $code, int $hauteur = 56): string
    {
        return str_replace(
            'fill="black"',
            'fill="currentColor"',
            (new BarcodeGeneratorSVG)->getBarcode($code, self::type($code), 2, $hauteur, 'black'),
        );
    }

    /** PNG encodé en base64, pour les PDF (DomPDF : <img src="data:image/png;base64,…">). */
    public static function pngBase64(string $code, int $largeurBarre = 2, int $hauteur = 50): string
    {
        return base64_encode((new BarcodeGeneratorPNG)->getBarcode($code, self::type($code), $largeurBarre, $hauteur));
    }

    private static function type(string $code): string
    {
        return Ean13::estValide($code) ? 'EAN13' : 'C128';
    }
}
