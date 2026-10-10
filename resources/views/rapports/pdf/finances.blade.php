{{-- Rapport financier (PDF, A4) --}}
@extends('rapports.pdf._mise-en-page')

@php
    $s = $donnees['synthese'];
    // Espaces insécables : la tendance « (-6,7 %) » ne se coupe pas en fin de ligne
    $tendance = fn (?float $t) => $t === null ? '' : "\u{00A0}(".($t > 0 ? '+' : '').str_replace('.', ',', $t)."\u{00A0}%)";
@endphp

@section('contenu')
    <table class="cartes" style="margin-top: 5mm;">
        <tr>
            <td><div class="doux">Chiffre d'affaires net</div><div class="valeur">{{ format_ar($s['ventes']['valeur']) }}</div><div class="doux">Précédente : {{ format_ar($s['ventes']['precedent']) }}{{ $tendance($s['ventes']['tendance']) }}</div></td>
            <td><div class="doux">Bénéfice</div><div @class(['valeur', 'negatif' => $s['benefice']['valeur'] < 0])>{{ format_ar($s['benefice']['valeur']) }}</div><div class="doux">Précédente : {{ format_ar($s['benefice']['precedent']) }}{{ $tendance($s['benefice']['tendance']) }}</div></td>
            <td><div class="doux">Dépenses</div><div class="valeur">{{ format_ar($s['depenses']['valeur']) }}</div><div class="doux">Précédente : {{ format_ar($s['depenses']['precedent']) }}</div></td>
            <td><div class="doux">Achats</div><div class="valeur">{{ format_ar($s['achats']['valeur']) }}</div><div class="doux">Précédente : {{ format_ar($s['achats']['precedent']) }}</div></td>
        </tr>
    </table>

    <table>
        <tr>
            <td style="width: 50%; padding-right: 4mm;">
                <h2>Compte de résultat</h2>
                <table class="lignes">
                    <tbody>
                        <tr><td>Ventes</td><td class="droite">{{ format_ar($s['brut']) }}</td></tr>
                        <tr class="bande"><td>- Retours clients</td><td class="droite">{{ format_ar(-$s['retours']) }}</td></tr>
                        <tr class="total"><td>= Chiffre d'affaires net</td><td class="droite">{{ format_ar($s['ventes']['valeur']) }}</td></tr>
                        <tr><td>- Coût d'achat des produits vendus</td><td class="droite">{{ format_ar(-$s['cout']) }}</td></tr>
                        <tr class="total"><td>= Marge brute</td><td class="droite">{{ format_ar($s['marge_brute']) }}</td></tr>
                        <tr><td>- Dépenses</td><td class="droite">{{ format_ar(-$s['depenses']['valeur']) }}</td></tr>
                        <tr class="total"><td>= Bénéfice</td><td @class(['droite', 'negatif' => $s['benefice']['valeur'] < 0])>{{ format_ar($s['benefice']['valeur']) }}</td></tr>
                        <tr><td class="doux">Marge nette</td><td class="droite doux">{{ $s['taux_marge'] !== null ? str_replace('.', ',', $s['taux_marge']).' %' : '-' }}</td></tr>
                    </tbody>
                </table>
            </td>
            <td style="width: 50%;">
                <h2>Dépenses par catégorie</h2>
                <table class="lignes">
                    <thead><tr><th>Catégorie</th><th class="droite">Nombre</th><th class="droite">Montant</th><th class="droite">Part</th></tr></thead>
                    <tbody>
                        @forelse ($donnees['depensesParCategorie'] as $d)
                            <tr @class(['bande' => $loop->even])><td>{{ $d->categorie->libelle() }}</td><td class="droite">{{ $d->nombre }}</td><td class="droite">{{ format_ar($d->total) }}</td><td class="droite">{{ str_replace('.', ',', $d->part) }} %</td></tr>
                        @empty
                            <tr><td colspan="4" class="doux">Aucune dépense.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <table>
        <tr>
            <td style="width: 50%; padding-right: 4mm;">
                <h2>Paiements de la période</h2>
                <table class="lignes">
                    <thead><tr><th>Mode</th><th class="droite">Reçus des clients</th><th class="droite">Versés aux fournisseurs</th></tr></thead>
                    <tbody>
                        @foreach ($donnees['encaissements'] as $i => $e)
                            <tr @class(['bande' => $loop->even])><td>{{ $e->mode->libelle() }}</td><td class="droite">{{ format_ar($e->total) }}</td><td class="droite">{{ format_ar($donnees['decaissements'][$i]->total) }}</td></tr>
                        @endforeach
                        <tr class="total"><td>Total</td><td class="droite">{{ format_ar($donnees['encaissements']->sum('total')) }}</td><td class="droite">{{ format_ar($donnees['decaissements']->sum('total')) }}</td></tr>
                    </tbody>
                </table>
                <p class="doux">Remboursements de retours : {{ format_ar($donnees['remboursements']['clients']) }} versés aux clients, {{ format_ar($donnees['remboursements']['fournisseurs']) }} reçus des fournisseurs.</p>
            </td>
            <td style="width: 50%;">
                <h2>Créances et dettes (à ce jour)</h2>
                <table class="lignes">
                    <thead><tr><th>Tiers</th><th class="droite">Montant dû</th></tr></thead>
                    <tbody>
                        <tr class="total"><td>Créances clients ({{ $donnees['creances']['debiteurs'] }})</td><td class="droite">{{ format_ar($donnees['creances']['total']) }}</td></tr>
                        @foreach ($donnees['creances']['principaux'] as $c)<tr><td>{{ $c->nom }}</td><td class="droite">{{ format_ar($c->du) }}</td></tr>@endforeach
                        <tr class="total"><td>Dettes fournisseurs ({{ $donnees['dettes']['creanciers'] }})</td><td class="droite">{{ format_ar($donnees['dettes']['total']) }}</td></tr>
                        @foreach ($donnees['dettes']['principaux'] as $f)<tr><td>{{ $f->nom }}</td><td class="droite">{{ format_ar($f->du) }}</td></tr>@endforeach
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <h2>Évolution ({{ ['jour' => 'par jour', 'semaine' => 'par semaine', 'mois' => 'par mois'][$periode->granularite()] }})</h2>
    <table class="lignes">
        <thead><tr><th>Période</th><th class="droite">CA net</th><th class="droite">Dépenses</th><th class="droite">Bénéfice</th></tr></thead>
        <tbody>
            @foreach ($donnees['serie'] as $g)
                <tr @class(['bande' => $loop->even])><td>{{ $g['libelle'] }}</td><td class="droite">{{ format_ar($g['ventes']) }}</td><td class="droite">{{ format_ar($g['depenses']) }}</td><td @class(['droite', 'negatif' => $g['benefice'] < 0])>{{ format_ar($g['benefice']) }}</td></tr>
            @endforeach
        </tbody>
    </table>
@endsection
