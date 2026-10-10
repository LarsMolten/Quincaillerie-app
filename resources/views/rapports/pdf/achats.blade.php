{{-- Rapport des achats (PDF, A4) --}}
@extends('rapports.pdf._mise-en-page')

@php
    $s = $donnees['synthese'];
    $max = max(1, ...array_map(fn ($g) => $g['total'], $donnees['serie']));
@endphp

@section('contenu')
    <table class="cartes" style="margin-top: 5mm;">
        <tr>
            <td><div class="doux">Achats</div><div class="valeur">{{ format_ar($s['total']) }}</div><div class="doux">{{ $s['nombre'] }} achat(s)</div></td>
            <td><div class="doux">Retours fournisseurs</div><div class="valeur">{{ format_ar($s['retours']) }}</div><div class="doux">Net {{ format_ar($s['net']) }}</div></td>
            <td><div class="doux">Déjà payé</div><div class="valeur">{{ format_ar($s['paye']) }}</div></td>
            <td><div class="doux">Reste dû</div><div class="valeur">{{ format_ar($s['reste']) }}</div></td>
        </tr>
    </table>

    <h2>Par fournisseur</h2>
    <table class="lignes">
        <thead><tr><th>Fournisseur</th><th class="droite">Achats</th><th class="droite">Total</th><th class="droite">Payé</th><th class="droite">Reste dû</th><th class="droite">Retours</th></tr></thead>
        <tbody>
            @forelse ($donnees['parFournisseur'] as $f)
                <tr @class(['bande' => $loop->even])><td>{{ $f->nom }}</td><td class="droite">{{ $f->nombre }}</td><td class="droite">{{ format_ar($f->total) }}</td><td class="droite">{{ format_ar($f->paye) }}</td><td class="droite">{{ format_ar($f->reste) }}</td><td class="droite doux">{{ $f->retours > 0 ? format_ar($f->retours) : '-' }}</td></tr>
            @empty
                <tr><td colspan="6" class="doux">Aucun achat sur cette période.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Évolution ({{ ['jour' => 'par jour', 'semaine' => 'par semaine', 'mois' => 'par mois'][$periode->granularite()] }})</h2>
    <table class="lignes">
        <thead><tr><th>Période</th><th class="droite">Achats</th><th class="droite">Retours</th><th class="droite">Net</th><th style="width: 35%;"></th></tr></thead>
        <tbody>
            @foreach (array_filter($donnees['serie'], fn ($g) => $g['total'] > 0 || $g['retours'] > 0) as $g)
                <tr><td>{{ $g['libelle'] }}</td><td class="droite">{{ format_ar($g['total']) }}</td><td class="droite doux">{{ $g['retours'] > 0 ? format_ar($g['retours']) : '-' }}</td><td class="droite">{{ format_ar($g['net']) }}</td>
                    <td>@if ($g['total'] > 0)<div class="barre" style="width: {{ round($g['total'] / $max * 100) }}%;"></div>@endif</td></tr>
            @endforeach
        </tbody>
    </table>

    <h2>Produits achetés</h2>
    <table class="lignes">
        <thead><tr><th>Produit</th><th>Référence</th><th class="droite">Quantité</th><th class="droite">Prix moyen</th><th class="droite">Montant</th><th class="droite">Retournés</th></tr></thead>
        <tbody>
            @forelse ($donnees['produits'] as $p)
                <tr @class(['bande' => $loop->even])><td>{{ $p->nom }}</td><td class="doux">{{ $p->reference }}</td><td class="droite">{{ format_quantite($p->quantite, $p->unite) }}</td><td class="droite">{{ format_ar($p->prix_moyen) }}</td><td class="droite">{{ format_ar($p->montant) }}</td><td class="droite doux">{{ $p->retournes > 0 ? format_quantite($p->retournes, $p->unite) : '-' }}</td></tr>
            @empty
                <tr><td colspan="6" class="doux">Aucun produit acheté.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
