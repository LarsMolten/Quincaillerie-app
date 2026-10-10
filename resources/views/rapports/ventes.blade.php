{{-- Rapport des ventes : synthèse, évolution (jour / semaine / mois), par vendeur, par produit --}}
@extends('layouts.app')

@section('titre', $titre)

@php
    $s = $donnees['synthese'];
    $serie = $donnees['serie'];
    $champs = view('rapports._select', ['nom' => 'vendeur', 'libelle' => 'Vendeur', 'options' => $listes['vendeurs'], 'valeur' => $filtres['vendeur'], 'vide' => 'Tous les vendeurs'])->render()
        .view('rapports._select', ['nom' => 'categorie', 'libelle' => 'Catégorie (produits)', 'options' => $listes['categories'], 'valeur' => $filtres['categorie'], 'vide' => 'Toutes les catégories'])->render();
    $granularite = ['jour' => 'par jour', 'semaine' => 'par semaine', 'mois' => 'par mois'][$periode->granularite()];
@endphp

@section('page')
    @include('rapports._entete')

    <section aria-label="Synthèse" class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-carte-stat libelle="Chiffre d'affaires net" :valeur="format_ar($s['net'])" icone="shopping-cart">
            <span class="chiffres">{{ format_ar($s['brut']) }} vendus − {{ format_ar($s['retours']) }} retournés</span>
        </x-carte-stat>
        <x-carte-stat libelle="Ventes" :valeur="$s['nombre']" icone="receipt"><span class="chiffres">Panier moyen {{ format_ar($s['panier']) }}</span></x-carte-stat>
        <x-carte-stat libelle="Marge brute" :valeur="format_ar($s['marge'])" icone="trending-up">
            <span class="chiffres">{{ $s['taux_marge'] !== null ? str_replace('.', ',', $s['taux_marge']).' % du CA net' : '—' }}</span>
        </x-carte-stat>
        <x-carte-stat libelle="Remises accordées" :valeur="format_ar($s['remises'])" icone="percent" />
    </section>

    <div class="grid gap-4 lg:grid-cols-3">
        <x-carte :titre="'Évolution du chiffre d’affaires net '.$granularite" class="lg:col-span-2">
            <div class="h-72" x-data="graphiqueRapport({{ \Illuminate\Support\Js::from(['type' => 'line', 'libelles' => array_column($serie, 'libelle'), 'series' => [['libelle' => 'CA net', 'valeurs' => array_column($serie, 'net'), 'jeton' => 'primaire']]]) }})">
                <canvas x-ref="canvas" role="img" aria-label="Courbe du chiffre d'affaires net {{ $granularite }} (valeurs dans le tableau)"></canvas>
            </div>
            @include('rapports._tableau-serie', ['legende' => 'Chiffre d’affaires '.$granularite, 'colonnes' => ['libelle' => 'Période', 'brut' => 'Vendu', 'retours' => 'Retours', 'net' => 'Net'], 'lignes' => $serie])
        </x-carte>

        <x-carte titre="Par vendeur">
            @if ($donnees['parVendeur']->isEmpty())
                <x-etat-vide icone="users" titre="Aucune vente" texte="Aucune vente sur cette période." />
            @else
                <div class="mb-4 h-40" x-data="graphiqueRapport({{ \Illuminate\Support\Js::from(['type' => 'bar', 'horizontal' => true, 'libelles' => $donnees['parVendeur']->pluck('nom'), 'series' => [['libelle' => 'CA net', 'valeurs' => $donnees['parVendeur']->pluck('net'), 'jeton' => 'graphique-2']]]) }})">
                    <canvas x-ref="canvas" role="img" aria-label="Chiffre d'affaires net par vendeur (détail dans la liste)"></canvas>
                </div>
                <ul class="divide-y divide-bordure text-sm">
                    @foreach ($donnees['parVendeur'] as $vendeur)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <span class="min-w-0"><span class="block truncate font-medium">{{ $vendeur->nom }}</span><span class="chiffres text-xs text-texte-doux">{{ $vendeur->nombre }} vente(s) · panier {{ format_ar($vendeur->panier) }}</span></span>
                            <span class="chiffres shrink-0 font-semibold">{{ format_ar($vendeur->net) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-carte>

        <x-carte titre="Par produit" description="Retours déduits · 20 premiers (tous dans les exports)" class="lg:col-span-3" :padding="false">
            @if ($donnees['parProduit']->isEmpty())
                <x-etat-vide icone="package" titre="Aucun produit vendu" texte="Aucune vente sur cette période." />
            @else
                <x-tableau :colonnes="[
                    'produit' => 'Produit',
                    'quantite' => ['libelle' => 'Quantité', 'alignement' => 'droite'],
                    'retours' => ['libelle' => 'Retournés', 'alignement' => 'droite'],
                    'chiffre' => ['libelle' => 'CA net', 'alignement' => 'droite'],
                    'marge' => ['libelle' => 'Marge', 'alignement' => 'droite'],
                ]" class="border-0 shadow-none">
                    @foreach ($donnees['parProduit']->take(20) as $produit)
                        <x-tableau.ligne>
                            <x-tableau.cellule libelle="Produit" principale>
                                <a href="{{ route('produits.show', $produit->id) }}" class="font-medium hover:underline">{{ $produit->nom }}</a>
                                <span class="block text-xs font-normal text-texte-doux">{{ $produit->reference }} · {{ $produit->categorie ?? 'Sans catégorie' }}</span>
                            </x-tableau.cellule>
                            <x-tableau.cellule libelle="Quantité" alignement="droite">{{ format_quantite($produit->quantite, $produit->unite) }}</x-tableau.cellule>
                            <x-tableau.cellule libelle="Retournés" alignement="droite" class="text-texte-doux">{{ $produit->retournes > 0 ? format_quantite($produit->retournes) : '—' }}</x-tableau.cellule>
                            <x-tableau.cellule libelle="CA net" alignement="droite" class="font-semibold">{{ format_ar($produit->chiffre) }}</x-tableau.cellule>
                            <x-tableau.cellule libelle="Marge" alignement="droite">
                                <span @class(['text-danger-texte' => $produit->marge < 0])>{{ format_ar($produit->marge) }}</span>
                            </x-tableau.cellule>
                        </x-tableau.ligne>
                    @endforeach
                </x-tableau>
            @endif
        </x-carte>
    </div>
@endsection
