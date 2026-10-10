{{-- Rapport des achats : synthèse, évolution, par fournisseur, produits achetés --}}
@extends('layouts.app')

@section('titre', $titre)

@php
    $s = $donnees['synthese'];
    $serie = $donnees['serie'];
    $champs = view('rapports._select', ['nom' => 'fournisseur', 'libelle' => 'Fournisseur', 'options' => $listes['fournisseurs'], 'valeur' => $filtres['fournisseur'], 'vide' => 'Tous les fournisseurs'])->render();
    $granularite = ['jour' => 'par jour', 'semaine' => 'par semaine', 'mois' => 'par mois'][$periode->granularite()];
@endphp

@section('page')
    @include('rapports._entete')

    <section aria-label="Synthèse" class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-carte-stat libelle="Achats" :valeur="format_ar($s['total'])" icone="truck"><span class="chiffres">{{ $s['nombre'] }} achat(s)</span></x-carte-stat>
        <x-carte-stat libelle="Retours fournisseurs" :valeur="format_ar($s['retours'])" icone="undo-2"><span class="chiffres">Achats nets {{ format_ar($s['net']) }}</span></x-carte-stat>
        <x-carte-stat libelle="Déjà payé" :valeur="format_ar($s['paye'])" icone="banknote" />
        <x-carte-stat libelle="Reste dû sur ces achats" :valeur="format_ar($s['reste'])" icone="hand-coins" />
    </section>

    <div class="grid gap-4 lg:grid-cols-3">
        <x-carte :titre="'Achats '.$granularite" class="lg:col-span-3">
            <div class="h-64" x-data="graphiqueRapport({{ \Illuminate\Support\Js::from(['type' => 'bar', 'libelles' => array_column($serie, 'libelle'), 'series' => [['libelle' => 'Achats', 'valeurs' => array_column($serie, 'total'), 'jeton' => 'graphique-2']]]) }})">
                <canvas x-ref="canvas" role="img" aria-label="Barres des achats {{ $granularite }} (valeurs dans le tableau)"></canvas>
            </div>
            @include('rapports._tableau-serie', ['legende' => 'Achats '.$granularite, 'colonnes' => ['libelle' => 'Période', 'total' => 'Achats', 'retours' => 'Retours', 'net' => 'Net'], 'lignes' => $serie])
        </x-carte>

        <x-carte titre="Par fournisseur" class="lg:col-span-3" :padding="false">
            @if ($donnees['parFournisseur']->isEmpty())
                <x-etat-vide icone="store" titre="Aucun achat" texte="Aucun achat sur cette période." />
            @else
                <x-tableau :colonnes="[
                    'fournisseur' => 'Fournisseur',
                    'nombre' => ['libelle' => 'Achats', 'alignement' => 'droite'],
                    'total' => ['libelle' => 'Total', 'alignement' => 'droite'],
                    'paye' => ['libelle' => 'Payé', 'alignement' => 'droite'],
                    'reste' => ['libelle' => 'Reste dû', 'alignement' => 'droite'],
                    'retours' => ['libelle' => 'Retours', 'alignement' => 'droite'],
                ]" class="border-0 shadow-none">
                    @foreach ($donnees['parFournisseur'] as $f)
                        <x-tableau.ligne>
                            <x-tableau.cellule libelle="Fournisseur" principale><span class="font-medium">{{ $f->nom }}</span></x-tableau.cellule>
                            <x-tableau.cellule libelle="Achats" alignement="droite">{{ $f->nombre }}</x-tableau.cellule>
                            <x-tableau.cellule libelle="Total" alignement="droite" class="font-semibold">{{ format_ar($f->total) }}</x-tableau.cellule>
                            <x-tableau.cellule libelle="Payé" alignement="droite">{{ format_ar($f->paye) }}</x-tableau.cellule>
                            <x-tableau.cellule libelle="Reste dû" alignement="droite"><span @class(['font-semibold text-danger-texte' => $f->reste > 0, 'text-texte-doux' => $f->reste <= 0])>{{ format_ar($f->reste) }}</span></x-tableau.cellule>
                            <x-tableau.cellule libelle="Retours" alignement="droite" class="text-texte-doux">{{ $f->retours > 0 ? format_ar($f->retours) : '—' }}</x-tableau.cellule>
                        </x-tableau.ligne>
                    @endforeach
                </x-tableau>
            @endif
        </x-carte>

        <x-carte titre="Produits achetés" class="lg:col-span-3" :padding="false">
            @if ($donnees['produits']->isEmpty())
                <x-etat-vide icone="package" titre="Aucun produit acheté" texte="Aucun achat sur cette période." />
            @else
                <x-tableau :colonnes="[
                    'produit' => 'Produit',
                    'quantite' => ['libelle' => 'Quantité', 'alignement' => 'droite'],
                    'prix' => ['libelle' => 'Prix moyen', 'alignement' => 'droite'],
                    'montant' => ['libelle' => 'Montant', 'alignement' => 'droite'],
                    'retours' => ['libelle' => 'Retournés', 'alignement' => 'droite'],
                ]" class="border-0 shadow-none">
                    @foreach ($donnees['produits'] as $p)
                        <x-tableau.ligne>
                            <x-tableau.cellule libelle="Produit" principale>
                                <a href="{{ route('produits.show', $p->id) }}" class="font-medium hover:underline">{{ $p->nom }}</a>
                                <span class="block text-xs font-normal text-texte-doux">{{ $p->reference }}</span>
                            </x-tableau.cellule>
                            <x-tableau.cellule libelle="Quantité" alignement="droite">{{ format_quantite($p->quantite, $p->unite) }}</x-tableau.cellule>
                            <x-tableau.cellule libelle="Prix moyen" alignement="droite">{{ format_ar($p->prix_moyen) }}</x-tableau.cellule>
                            <x-tableau.cellule libelle="Montant" alignement="droite" class="font-semibold">{{ format_ar($p->montant) }}</x-tableau.cellule>
                            <x-tableau.cellule libelle="Retournés" alignement="droite" class="text-texte-doux">{{ $p->retournes > 0 ? format_quantite($p->retournes, $p->unite) : '—' }}</x-tableau.cellule>
                        </x-tableau.ligne>
                    @endforeach
                </x-tableau>
            @endif
        </x-carte>
    </div>
@endsection
