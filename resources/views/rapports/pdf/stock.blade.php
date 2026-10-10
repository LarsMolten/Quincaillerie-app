{{-- Rapport de stock (PDF, A4 paysage) --}}
@extends('rapports.pdf._mise-en-page')

@php
    $s = $donnees['synthese'];
    $etats = ['normal' => 'En stock', 'faible' => 'Stock faible', 'rupture' => 'Rupture'];
    $maxCategorie = max(1, (float) $donnees['parCategorie']->max('valeur'));
    // Libellés de type sans signe moins U+2212 (absent de Helvetica : affiché « ? » par DomPDF)
    $libelleType = fn (\App\Enums\TypeMouvementStock $type) => str_replace("\u{2212}", '-', $type->libelle());
@endphp

@section('contenu')
    <table class="cartes" style="margin-top: 5mm;">
        <tr>
            <td><div class="doux">Valeur du stock (prix d'achat)</div><div class="valeur">{{ format_ar($s['valeur']) }}</div></td>
            <td><div class="doux">Produits actifs</div><div class="valeur">{{ $s['produits'] }}</div></td>
            <td><div class="doux">Stock faible</div><div class="valeur">{{ $s['faibles'] }}</div></td>
            <td><div class="doux">Ruptures</div><div class="valeur">{{ $s['ruptures'] }}</div></td>
        </tr>
    </table>

    <table>
        <tr>
            <td style="width: 50%; padding-right: 4mm;">
                <h2>Valeur par catégorie</h2>
                <table class="lignes">
                    <thead><tr><th>Catégorie</th><th class="droite">Produits</th><th class="droite">Valeur</th><th style="width: 35%;"></th></tr></thead>
                    <tbody>
                        @foreach ($donnees['parCategorie'] as $c)
                            <tr @class(['bande' => $loop->even])><td>{{ $c->categorie }}</td><td class="droite">{{ $c->produits }}</td><td class="droite">{{ format_ar($c->valeur) }}</td>
                                <td>@if ($c->valeur > 0)<div class="barre" style="width: {{ round($c->valeur / $maxCategorie * 100) }}%;"></div>@endif</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
            <td style="width: 50%;">
                <h2>Mouvements de la période ({{ $donnees['nombreMouvements'] }})</h2>
                <table class="lignes">
                    <thead><tr><th>Type</th><th class="droite">Mouvements</th><th class="droite">Quantité</th></tr></thead>
                    <tbody>
                        @forelse ($donnees['mouvementsParType'] as $l)
                            <tr @class(['bande' => $loop->even])><td>{{ $libelleType($l->type) }}</td><td class="droite">{{ $l->nombre }}</td><td class="droite">{{ $l->sens === 'entree' ? '+' : '-' }}{{ format_quantite($l->quantite) }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="doux">Aucun mouvement.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    @foreach ([['Stock faible', $donnees['faibles']], ['Ruptures', $donnees['ruptures']], ['Sans vente depuis '.$donnees['joursSansVente'].' jours', $donnees['sansVente']]] as [$titreListe, $produits])
        <h2>{{ $titreListe }} ({{ $produits->count() }})</h2>
        @if ($produits->isEmpty())
            <p class="doux">Aucun produit.</p>
        @else
            <table class="lignes">
                <thead><tr><th>Produit</th><th>Référence</th><th>Catégorie</th><th class="droite">Stock</th><th class="droite">Minimum</th><th class="droite">Prix d'achat</th>@if ($loop->last)<th class="droite">Dernière vente</th><th class="droite">Valeur</th>@endif</tr></thead>
                <tbody>
                    @foreach ($produits as $p)
                        <tr @class(['bande' => $loop->even])>
                            <td>{{ $p->nom }}</td><td class="doux">{{ $p->reference }}</td><td class="doux">{{ $p->categorie }}</td>
                            <td class="droite">{{ format_quantite($p->stock_actuel, $p->unite) }}</td><td class="droite doux">{{ format_quantite($p->stock_minimum) }}</td><td class="droite">{{ format_ar($p->prix_achat) }}</td>
                            @if (property_exists($p, 'derniere_vente'))
                                <td class="droite">{{ $p->derniere_vente ? \Illuminate\Support\Carbon::parse($p->derniere_vente)->format('d/m/Y') : 'Jamais' }}</td><td class="droite">{{ format_ar($p->valeur) }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach

    <h2>État actuel du stock</h2>
    <table class="lignes">
        <thead><tr><th>Produit</th><th>Référence</th><th>Catégorie</th><th class="droite">Stock</th><th class="droite">Prix d'achat</th><th class="droite">Valeur</th><th>État</th></tr></thead>
        <tbody>
            @foreach ($donnees['etat'] as $p)
                <tr @class(['bande' => $loop->even])><td>{{ $p->nom }}</td><td class="doux">{{ $p->reference }}</td><td class="doux">{{ $p->categorie }}</td><td class="droite">{{ format_quantite($p->stock_actuel, $p->unite) }}</td><td class="droite">{{ format_ar($p->prix_achat) }}</td><td class="droite">{{ format_ar($p->valeur) }}</td><td>{{ $etats[$p->etat] }}</td></tr>
            @endforeach
            <tr class="total"><td colspan="5">Valeur totale</td><td class="droite">{{ format_ar($s['valeur']) }}</td><td></td></tr>
        </tbody>
    </table>

    <h2>Derniers mouvements (au plus {{ \App\Rapports\RapportStock::MOUVEMENTS_ECRAN }})</h2>
    <table class="lignes">
        <thead><tr><th>Date</th><th>Produit</th><th>Type</th><th class="droite">Quantité</th><th class="droite">Stock</th><th>Par</th><th>Motif</th></tr></thead>
        <tbody>
            @forelse ((clone $donnees['mouvements'])->limit(\App\Rapports\RapportStock::MOUVEMENTS_ECRAN)->get() as $m)
                <tr @class(['bande' => $loop->even])>
                    <td style="white-space: nowrap;">{{ \Illuminate\Support\Carbon::parse($m->created_at)->format('d/m/Y H:i') }}</td><td>{{ $m->produit }}</td>
                    <td>{{ $libelleType(\App\Enums\TypeMouvementStock::from($m->type)) }}</td>
                    <td class="droite">{{ $m->sens === 'entree' ? '+' : '-' }}{{ format_quantite($m->quantite, $m->unite) }}</td>
                    <td class="droite doux" style="white-space: nowrap;">{{ format_quantite($m->stock_avant) }} &gt; {{ format_quantite($m->stock_apres) }}</td>
                    <td class="doux">{{ $m->utilisateur }}</td><td class="doux">{{ \Illuminate\Support\Str::limit((string) $m->motif, 60) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="doux">Aucun mouvement sur cette période.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
