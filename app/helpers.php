<?php

if (! function_exists('format_ar')) {
    /**
     * Formate un montant en Ariary, sans décimales.
     *
     * Les milliers et la devise sont séparés par une espace insécable
     * pour éviter un retour à la ligne au milieu du montant.
     * Exemple : format_ar(35000) renvoie « 35 000 Ar ».
     */
    function format_ar(int|float|string|null $montant): string
    {
        $espaceInsecable = "\u{00A0}";

        return number_format(round((float) $montant), 0, ',', $espaceInsecable).$espaceInsecable.'Ar';
    }
}

if (! function_exists('format_quantite')) {
    /**
     * Formate une quantité de stock sans zéros inutiles, avec l'unité éventuelle.
     * Exemples : format_quantite(12) → « 12 », format_quantite(2.5, 'kg') → « 2,5 kg »,
     * format_quantite(1250, 'pce') → « 1 250 pce » (espaces insécables).
     */
    function format_quantite(int|float|string|null $quantite, ?string $unite = null): string
    {
        $espaceInsecable = "\u{00A0}";
        $texte = number_format((float) $quantite, 3, ',', $espaceInsecable);
        $texte = rtrim(rtrim($texte, '0'), ',');

        if ($texte === '-0') {
            $texte = '0';
        }

        return $unite ? $texte.$espaceInsecable.$unite : $texte;
    }
}
