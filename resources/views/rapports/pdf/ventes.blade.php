{{-- Rapport des ventes (PDF, A4 paysage) --}}
@extends('rapports.pdf._mise-en-page')

@php
    $s = $donnees['synthese'];
    $max = max(1, ...array_map(fn ($g) => abs($g['net']), $donnees['serie']));
@endphp

@section('contenu')
    <table class="cartes" style="margin-top: 5mm;">
        <tr>
            <td><div class="doux">Chiffre d'affaires net</div><div class="valeur">{{ format_ar($s['net']) }}</div><div class="doux">{{ format_ar($s['brut']) }} vendus - {{ format_ar($s['retours']) }} retournés</div></td>
            <td><div class="doux">Ventes</div><div class="valeur">{{ $s['nombre'] }}</div><div class="doux">Panier moyen {{ format_ar($s['panier']) }}</div></td>
            <td><div class="doux">Marge brute</div><div class="valeur">{{ format_ar($s['marge']) }}</div><div class="doux">{{ $s['taux_marge'] !== null ? str_replace('.', ',', $s['taux_marge']).' % du CA net' : '-' }}</div></td>
            <td><div class="doux">Remises accordées</div><div class="valeur">{{ format_ar($s['remises']) }}</div><div class="doux">Coût d'achat {{ format_ar($s['cout']) }}</div></td>
        </tr>
    </table>

    <table>
        <tr>
            <td style="width: 58%; padding-right: 4mm;">
                <h2>Évolution ({{ ['jour' => 'par jour', 'semaine' => 'par semaine', 'mois' => 'par mois'][$periode->granularite()] }})</h2>
                <table class="lignes">
                    <thead><tr><th>Période</th><th class="droite">Ventes</th><th class="droite">Retours</th><th class="droite">Net</th><th style="width: 30%;"></th></tr></thead>
                    <tbody>
                        @foreach ($donnees['serie'] as $g)
                            <tr @class(['bande' => $loop->even])>
                                <td>{{ $g['libelle'] }}</td>
                                <td class="droite">{{ format_ar($g['brut']) }}</td>
                                <td class="droite doux">{{ $g['retours'] > 0 ? format_ar($g['retours']) : '-' }}</td>
                                <td @class(['droite', 'negatif' => $g['net'] < 0])>{{ format_ar($g['net']) }}</td>
                                <td>@if ($g['net'] > 0)<div class="barre" style="width: {{ round($g['net'] / $max * 100) }}%;"></div>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
            <td style="width: 42%;">
                <h2>Par vendeur</h2>
                <table class="lignes">
                    <thead><tr><th>Vendeur</th><th class="droite">Ventes</th><th class="droite">Retours</th><th class="droite">Net</th></tr></thead>
                    <tbody>
                        @forelse ($donnees['parVendeur'] as $v)
                            <tr @class(['bande' => $loop->even])><td>{{ $v->nom }}</td><td class="droite">{{ $v->nombre }}</td><td class="droite doux">{{ format_ar($v->retours) }}</td><td class="droite">{{ format_ar($v->net) }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="doux">Aucune vente.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <h2>Par produit (retours déduits)</h2>
    <table class="lignes">
        <thead><tr><th>Produit</th><th>Référence</th><th>Catégorie</th><th class="droite">Quantité</th><th class="droite">Retournés</th><th class="droite">CA net</th><th class="droite">Coût</th><th class="droite">Marge</th></tr></thead>
        <tbody>
            @forelse ($donnees['parProduit'] as $p)
                <tr @class(['bande' => $loop->even])>
                    <td>{{ $p->nom }}</td><td class="doux">{{ $p->reference }}</td><td class="doux">{{ $p->categorie }}</td>
                    <td class="droite">{{ format_quantite($p->quantite, $p->unite) }}</td>
                    <td class="droite doux">{{ $p->retournes > 0 ? format_quantite($p->retournes) : '-' }}</td>
                    <td class="droite">{{ format_ar($p->chiffre) }}</td><td class="droite doux">{{ format_ar($p->cout) }}</td>
                    <td @class(['droite', 'negatif' => $p->marge < 0])>{{ format_ar($p->marge) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="doux">Aucun produit vendu sur cette période.</td></tr>
            @endforelse
            @if ($donnees['parProduit']->isNotEmpty())
                <tr class="total"><td colspan="5">Total</td><td class="droite">{{ format_ar($donnees['parProduit']->sum('chiffre')) }}</td><td class="droite">{{ format_ar($donnees['parProduit']->sum('cout')) }}</td><td class="droite">{{ format_ar($donnees['parProduit']->sum('marge')) }}</td></tr>
            @endif
        </tbody>
    </table>
@endsection
