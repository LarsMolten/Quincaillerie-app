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
