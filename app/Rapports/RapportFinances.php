<?php

namespace App\Rapports;

use App\Enums\CategorieDepense;
use App\Enums\ModePaiement;
use App\Enums\StatutAchat;
use App\Enums\StatutRetour;
use App\Enums\TypeRetour;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rapport financier : chiffre d'affaires net, achats, dépenses (par catégorie), bénéfice et marge,
 * paiements reçus et versés par mode, remboursements de retours, créances et dettes (situation à ce jour).
 * Formule du bénéfice commune au tableau de bord (Indicateurs). Tendances par rapport à la période précédente.
 */
class RapportFinances
{
    public function __construct(
        private readonly Indicateurs $indicateurs,
        private readonly TableauDeBord $tableau,
    ) {}

    /** @return array<string, mixed> */
    public function donnees(Periode $periode): array
    {
        $jours = $this->indicateurs->parJour($periode->debut, $periode->fin);
        $avant = $this->indicateurs->parJour($periode->precedente()->debut, $periode->precedente()->fin);

        $somme = fn (array $donnees, string $cle) => round((float) array_sum(array_column($donnees, $cle)), 2);
        $indicateur = fn (string $cle) => [
            'valeur' => $somme($jours, $cle),
            'precedent' => $somme($avant, $cle),
            'tendance' => $this->tableau->tendance($somme($jours, $cle), $somme($avant, $cle)),
        ];

        $ventes = $somme($jours, 'ventes');
        $benefice = $somme($jours, 'benefice');

        return [
            'synthese' => [
                'brut' => $somme($jours, 'brut'),
                'retours' => $somme($jours, 'retours'),
                'ventes' => $indicateur('ventes'),
                'cout' => $somme($jours, 'cout'),
                'marge_brute' => round($ventes - $somme($jours, 'cout'), 2),
                'depenses' => $indicateur('depenses'),
                'benefice' => $indicateur('benefice'),
                'achats' => $indicateur('achats'),
                'taux_marge' => $ventes > 0 ? round($benefice / $ventes * 100, 1) : null,
            ],
            'serie' => $this->serie($periode, $jours),
            'depensesParCategorie' => $this->depensesParCategorie($periode),
            'encaissements' => $this->paiementsParMode($periode, 'vente'),
            'decaissements' => $this->paiementsParMode($periode, 'achat'),
            'remboursements' => $this->remboursements($periode),
            'creances' => $this->tableau->creances(),
            'dettes' => $this->dettes(),
        ];
    }

    /** @return list<array{cle: string, libelle: string, ventes: float, depenses: float, benefice: float}> */
    private function serie(Periode $periode, array $jours): array
    {
        $groupes = [];
        foreach ($jours as $jour => $valeurs) {
            $cle = $periode->groupe(Carbon::parse($jour));
            $groupes[$cle] ??= ['cle' => $cle, 'libelle' => $periode->libelleGroupe($cle), 'ventes' => 0.0, 'depenses' => 0.0, 'benefice' => 0.0];
            foreach (['ventes', 'depenses', 'benefice'] as $cleValeur) {
                $groupes[$cle][$cleValeur] = round($groupes[$cle][$cleValeur] + $valeurs[$cleValeur], 2);
            }
        }

        return array_values($groupes);
    }

    private function depensesParCategorie(Periode $periode): Collection
    {
        $totaux = DB::table('depenses')
            ->whereNull('deleted_at')
            ->whereDate('date_depense', '>=', $periode->debut->toDateString())
            ->whereDate('date_depense', '<=', $periode->fin->toDateString())
            ->groupBy('categorie')
            ->selectRaw('categorie, SUM(montant) as total, COUNT(*) as nombre')
            ->get()->keyBy('categorie');
        $total = (float) $totaux->sum('total');

        return collect(CategorieDepense::cases())
            ->filter(fn (CategorieDepense $c) => isset($totaux[$c->value]))
            ->map(fn (CategorieDepense $c) => (object) [
                'categorie' => $c,
                'total' => (float) $totaux[$c->value]->total,
                'nombre' => (int) $totaux[$c->value]->nombre,
                'part' => $total > 0 ? round((float) $totaux[$c->value]->total / $total * 100, 1) : 0.0,
            ])
            ->sortByDesc('total')
            ->values();
    }

    /** Paiements de la période par mode : reçus des clients (vente) ou versés aux fournisseurs (achat). */
    private function paiementsParMode(Periode $periode, string $type): Collection
    {
        $totaux = DB::table('paiements')
            ->where('payable_type', $type)
            ->where('date_paiement', '>=', $periode->debut)
            ->where('date_paiement', '<', $periode->fin->copy()->addDay())
            ->groupBy('mode')
            ->selectRaw('mode, SUM(montant) as total, COUNT(*) as nombre')
            ->get()->keyBy('mode');

        return collect(ModePaiement::encaissements())->map(fn (ModePaiement $m) => (object) [
            'mode' => $m,
            'total' => (float) ($totaux[$m->value]->total ?? 0),
            'nombre' => (int) ($totaux[$m->value]->nombre ?? 0),
        ]);
    }

    /** Remboursements des retours de la période : versés aux clients, reçus des fournisseurs. */
    private function remboursements(Periode $periode): array
    {
        $totaux = DB::table('retours')
            ->where('statut', StatutRetour::Valide->value)
            ->where('montant_rembourse', '>', 0)
            ->whereDate('date_retour', '>=', $periode->debut->toDateString())
            ->whereDate('date_retour', '<=', $periode->fin->toDateString())
            ->groupBy('type')
            ->selectRaw('type, SUM(montant_rembourse) as total')
            ->pluck('total', 'type');

        return [
            'clients' => (float) ($totaux[TypeRetour::Client->value] ?? 0),
            'fournisseurs' => (float) ($totaux[TypeRetour::Fournisseur->value] ?? 0),
        ];
    }

    /** Dettes fournisseurs en cours (achats validés non soldés), à ce jour. */
    private function dettes(int $limite = 5): array
    {
        $base = DB::table('achats')
            ->join('fournisseurs', 'fournisseurs.id', '=', 'achats.fournisseur_id')
            ->where('achats.statut', StatutAchat::Valide->value)
            ->where('achats.reste_a_payer', '>', 0);

        return [
            'total' => (float) (clone $base)->sum('achats.reste_a_payer'),
            'creanciers' => (int) (clone $base)->distinct()->count('achats.fournisseur_id'),
            'principaux' => (clone $base)->groupBy('fournisseurs.id', 'fournisseurs.nom')
                ->selectRaw('fournisseurs.id, fournisseurs.nom, SUM(achats.reste_a_payer) as du, COUNT(*) as achats')
                ->orderByDesc('du')->limit($limite)->get(),
        ];
    }
}
