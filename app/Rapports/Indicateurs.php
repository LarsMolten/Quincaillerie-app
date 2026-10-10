<?php

namespace App\Rapports;

use App\Enums\StatutAchat;
use App\Enums\StatutRetour;
use App\Enums\StatutVente;
use App\Enums\TypeRetour;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Agrégats journaliers communs au tableau de bord et aux rapports (une seule formule du bénéfice) :
 * ventes nettes = ventes validées − retours clients validés ; coût = prix d'achat des produits restés vendus
 * (le coût d'un retour est le prix d'achat moyen du produit dans la vente d'origine) ; bénéfice = ventes nettes
 * − coût − dépenses (hors supprimées). Achats validés à part. Agrégations SQL uniquement (CLAUDE.md §5.7).
 */
class Indicateurs
{
    /**
     * Agrégats par jour sur un intervalle (bornes incluses), jours sans activité à 0.
     *
     * @return array<string, array{brut: float, retours: float, ventes: float, cout: float, depenses: float, achats: float, benefice: float, nombre: int}>
     */
    public function parJour(Carbon $debut, Carbon $fin): array
    {
        $debut = $debut->copy()->startOfDay();
        $finExclue = $fin->copy()->startOfDay()->addDay();

        $ventes = DB::table('ventes')
            ->where('statut', StatutVente::Validee->value)
            ->where('date_vente', '>=', $debut)->where('date_vente', '<', $finExclue)
            ->groupByRaw('DATE(date_vente)')
            ->selectRaw('DATE(date_vente) as jour, SUM(total) as total, COUNT(*) as nombre')
            ->get()->keyBy('jour');

        $couts = DB::table('lignes_vente')
            ->join('ventes', 'ventes.id', '=', 'lignes_vente.vente_id')
            ->where('ventes.statut', StatutVente::Validee->value)
            ->where('ventes.date_vente', '>=', $debut)->where('ventes.date_vente', '<', $finExclue)
            ->groupByRaw('DATE(ventes.date_vente)')
            ->selectRaw('DATE(ventes.date_vente) as jour, SUM(lignes_vente.quantite * lignes_vente.prix_achat_unitaire) as cout')
            ->pluck('cout', 'jour');

        // Retours clients validés : montant et coût d'achat (prix d'achat moyen du produit dans la vente d'origine)
        $retours = DB::table('retours')
            ->join('lignes_retour', 'lignes_retour.retour_id', '=', 'retours.id')
            ->where('retours.type', TypeRetour::Client->value)
            ->where('retours.statut', StatutRetour::Valide->value)
            ->whereDate('retours.date_retour', '>=', $debut->toDateString())
            ->whereDate('retours.date_retour', '<=', $fin->toDateString())
            ->groupByRaw('DATE(retours.date_retour)')
            ->selectRaw('DATE(retours.date_retour) as jour, SUM(lignes_retour.total) as montant, SUM(lignes_retour.quantite * (
                SELECT SUM(lv.quantite * lv.prix_achat_unitaire * 1.0) / SUM(lv.quantite)
                FROM lignes_vente lv WHERE lv.vente_id = retours.vente_id AND lv.produit_id = lignes_retour.produit_id
            )) as cout')
            ->get()->keyBy('jour');

        $depenses = DB::table('depenses')
            ->whereNull('deleted_at')
            ->whereDate('date_depense', '>=', $debut->toDateString())
            ->whereDate('date_depense', '<=', $fin->toDateString())
            ->groupByRaw('DATE(date_depense)')
            ->selectRaw('DATE(date_depense) as jour, SUM(montant) as total')
            ->pluck('total', 'jour');

        $achats = DB::table('achats')
            ->where('statut', StatutAchat::Valide->value)
            ->whereDate('date_achat', '>=', $debut->toDateString())
            ->whereDate('date_achat', '<=', $fin->toDateString())
            ->groupByRaw('DATE(date_achat)')
            ->selectRaw('DATE(date_achat) as jour, SUM(total) as total')
            ->pluck('total', 'jour');

        $resultat = [];
        for ($date = $debut->copy(); $date < $finExclue; $date->addDay()) {
            $cle = $date->toDateString();
            $ventesNettes = (float) ($ventes[$cle]->total ?? 0) - (float) ($retours[$cle]->montant ?? 0);
            $cout = (float) ($couts[$cle] ?? 0) - (float) ($retours[$cle]->cout ?? 0);
            $depense = (float) ($depenses[$cle] ?? 0);

            $resultat[$cle] = [
                'brut' => round((float) ($ventes[$cle]->total ?? 0), 2),
                'retours' => round((float) ($retours[$cle]->montant ?? 0), 2),
                'ventes' => round($ventesNettes, 2),
                'cout' => round($cout, 2),
                'depenses' => round($depense, 2),
                'achats' => round((float) ($achats[$cle] ?? 0), 2),
                'benefice' => round($ventesNettes - $cout - $depense, 2),
                'nombre' => (int) ($ventes[$cle]->nombre ?? 0),
            ];
        }

        return $resultat;
    }
}
