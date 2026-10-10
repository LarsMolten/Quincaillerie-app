<?php

namespace App\Exports\Rapports;

use App\Enums\TypeMouvementStock;
use App\Rapports\Periode;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Classeur Excel d'un rapport : une feuille « Synthèse » (avec la période) puis une feuille par section.
 * Les données viennent des classes app/Rapports (aucun calcul ici).
 */
class RapportExcel implements Export, WithMultipleSheets
{
    /** @param  array<string, mixed>  $donnees */
    public function __construct(
        private readonly string $rapport,
        private readonly array $donnees,
        private readonly Periode $periode,
    ) {}

    public function sheets(): array
    {
        return match ($this->rapport) {
            'ventes' => $this->ventes(),
            'achats' => $this->achats(),
            'stock' => $this->stock(),
            default => $this->finances(),
        };
    }

    /** @param  array<string, mixed>  $valeurs [libellé => valeur] */
    private function synthese(array $valeurs): Feuille
    {
        $lignes = [['Période', $this->periode->libelle()], ['Édité le', now()->format('d/m/Y H:i')]];
        foreach ($valeurs as $libelle => $valeur) {
            $lignes[] = [$libelle, $valeur];
        }

        return new Feuille('Synthèse', ['Indicateur', 'Valeur'], $lignes);
    }

    private function ventes(): array
    {
        $d = $this->donnees;
        $s = $d['synthese'];

        return [
            $this->synthese([
                'Ventes (Ar)' => $s['brut'], 'Retours clients (Ar)' => $s['retours'], 'Chiffre d\'affaires net (Ar)' => $s['net'],
                'Nombre de ventes' => $s['nombre'], 'Panier moyen (Ar)' => $s['panier'], 'Remises (Ar)' => $s['remises'],
                'Coût d\'achat (Ar)' => $s['cout'], 'Marge brute (Ar)' => $s['marge'], 'Taux de marge (%)' => $s['taux_marge'],
            ]),
            new Feuille('Évolution', ['Période', 'Ventes (Ar)', 'Retours (Ar)', 'Net (Ar)', 'Nombre de ventes'],
                array_map(fn ($g) => [$g['libelle'], $g['brut'], $g['retours'], $g['net'], $g['nombre']], $d['serie'])),
            new Feuille('Par vendeur', ['Vendeur', 'Ventes', 'Vendu (Ar)', 'Retours (Ar)', 'Net (Ar)', 'Panier moyen (Ar)'],
                $d['parVendeur']->map(fn ($v) => [$v->nom, $v->nombre, $v->brut, $v->retours, $v->net, $v->panier])->all()),
            new Feuille('Par produit', ['Produit', 'Référence', 'Catégorie', 'Unité', 'Quantité nette', 'Retournés', 'CA net (Ar)', 'Coût (Ar)', 'Marge (Ar)'],
                $d['parProduit']->map(fn ($p) => [$p->nom, $p->reference, $p->categorie, $p->unite, $p->quantite, $p->retournes, $p->chiffre, $p->cout, $p->marge])->all()),
        ];
    }

    private function achats(): array
    {
        $d = $this->donnees;
        $s = $d['synthese'];

        return [
            $this->synthese([
                'Achats (Ar)' => $s['total'], 'Nombre d\'achats' => $s['nombre'], 'Retours fournisseurs (Ar)' => $s['retours'],
                'Achats nets (Ar)' => $s['net'], 'Payé (Ar)' => $s['paye'], 'Reste dû (Ar)' => $s['reste'],
            ]),
            new Feuille('Évolution', ['Période', 'Achats (Ar)', 'Retours (Ar)', 'Net (Ar)', 'Nombre'],
                array_map(fn ($g) => [$g['libelle'], $g['total'], $g['retours'], $g['net'], $g['nombre']], $d['serie'])),
            new Feuille('Par fournisseur', ['Fournisseur', 'Achats', 'Total (Ar)', 'Payé (Ar)', 'Reste dû (Ar)', 'Retours (Ar)'],
                $d['parFournisseur']->map(fn ($f) => [$f->nom, $f->nombre, $f->total, $f->paye, $f->reste, $f->retours])->all()),
            new Feuille('Produits achetés', ['Produit', 'Référence', 'Unité', 'Quantité', 'Prix moyen (Ar)', 'Montant (Ar)', 'Retournés', 'Montant retourné (Ar)'],
                $d['produits']->map(fn ($p) => [$p->nom, $p->reference, $p->unite, $p->quantite, $p->prix_moyen, $p->montant, $p->retournes, $p->montant_retours])->all()),
        ];
    }

    private function stock(): array
    {
        $d = $this->donnees;
        $s = $d['synthese'];
        $produit = fn ($p) => [$p->nom, $p->reference, $p->categorie, $p->unite, $p->stock_actuel, $p->stock_minimum, $p->prix_achat];
        $entetesProduit = ['Produit', 'Référence', 'Catégorie', 'Unité', 'Stock', 'Stock minimum', 'Prix d\'achat (Ar)'];

        return [
            $this->synthese([
                'Valeur du stock au prix d\'achat (Ar)' => $s['valeur'], 'Produits actifs' => $s['produits'],
                'Produits en stock faible' => $s['faibles'], 'Ruptures' => $s['ruptures'], 'Mouvements de la période' => $d['nombreMouvements'],
            ]),
            new Feuille('État du stock', [...$entetesProduit, 'Valeur (Ar)', 'État'],
                $d['etat']->map(fn ($p) => [...$produit($p), $p->valeur, ['normal' => 'En stock', 'faible' => 'Stock faible', 'rupture' => 'Rupture'][$p->etat]])->all()),
            new Feuille('Par catégorie', ['Catégorie', 'Produits', 'Valeur (Ar)'], $d['parCategorie']->map(fn ($c) => [$c->categorie, $c->produits, $c->valeur])->all()),
            new Feuille('Stock faible', $entetesProduit, $d['faibles']->map($produit)->all()),
            new Feuille('Ruptures', $entetesProduit, $d['ruptures']->map($produit)->all()),
            new Feuille("Sans vente {$d['joursSansVente']} jours", [...$entetesProduit, 'Dernière vente', 'Valeur immobilisée (Ar)'],
                $d['sansVente']->map(fn ($p) => [...$produit($p), $p->derniere_vente ? Carbon::parse($p->derniere_vente)->format('d/m/Y') : 'Jamais', $p->valeur])->all()),
            new Feuille('Mouvements', ['Date', 'Produit', 'Référence', 'Type', 'Quantité', 'Unité', 'Stock avant', 'Stock après', 'Utilisateur', 'Motif'],
                (clone $d['mouvements'])->get()->map(fn ($m) => [
                    Carbon::parse($m->created_at)->format('d/m/Y H:i'), $m->produit, $m->reference,
                    TypeMouvementStock::from($m->type)->libelle(),
                    ($m->sens === 'entree' ? 1 : -1) * (float) $m->quantite, $m->unite, (float) $m->stock_avant, (float) $m->stock_apres, $m->utilisateur, $m->motif,
                ])->all()),
        ];
    }

    private function finances(): array
    {
        $d = $this->donnees;
        $s = $d['synthese'];

        return [
            $this->synthese([
                'Ventes (Ar)' => $s['brut'], 'Retours clients (Ar)' => $s['retours'], 'Chiffre d\'affaires net (Ar)' => $s['ventes']['valeur'],
                'Coût d\'achat des produits vendus (Ar)' => $s['cout'], 'Marge brute (Ar)' => $s['marge_brute'], 'Dépenses (Ar)' => $s['depenses']['valeur'],
                'Bénéfice (Ar)' => $s['benefice']['valeur'], 'Marge nette (%)' => $s['taux_marge'], 'Achats (Ar)' => $s['achats']['valeur'],
                'CA net période précédente (Ar)' => $s['ventes']['precedent'], 'Bénéfice période précédente (Ar)' => $s['benefice']['precedent'],
                'Créances clients à ce jour (Ar)' => $d['creances']['total'], 'Dettes fournisseurs à ce jour (Ar)' => $d['dettes']['total'],
                'Remboursements versés aux clients (Ar)' => $d['remboursements']['clients'], 'Remboursements reçus des fournisseurs (Ar)' => $d['remboursements']['fournisseurs'],
            ]),
            new Feuille('Évolution', ['Période', 'CA net (Ar)', 'Dépenses (Ar)', 'Bénéfice (Ar)'],
                array_map(fn ($g) => [$g['libelle'], $g['ventes'], $g['depenses'], $g['benefice']], $d['serie'])),
            new Feuille('Dépenses', ['Catégorie', 'Nombre', 'Montant (Ar)', 'Part (%)'],
                $d['depensesParCategorie']->map(fn ($l) => [$l->categorie->libelle(), $l->nombre, $l->total, $l->part])->all()),
            new Feuille('Paiements', ['Mode', 'Reçus des clients (Ar)', 'Paiements reçus', 'Versés aux fournisseurs (Ar)', 'Paiements versés'],
                $d['encaissements']->map(fn ($e, $i) => [$e->mode->libelle(), $e->total, $e->nombre, $d['decaissements'][$i]->total, $d['decaissements'][$i]->nombre])->all()),
            new Feuille('Créances et dettes', ['Type', 'Nom', 'Montant dû (Ar)'], [
                ...$d['creances']['principaux']->map(fn ($c) => ['Client', $c->nom, (float) $c->du])->all(),
                ...$d['dettes']['principaux']->map(fn ($f) => ['Fournisseur', $f->nom, (float) $f->du])->all(),
            ]),
        ];
    }
}
