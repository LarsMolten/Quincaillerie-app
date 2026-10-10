{{-- Rapport de stock : valeur au prix d'achat, par catégorie, stock faible, ruptures, sans vente, mouvements de la période --}}
@extends('layouts.app')

@section('titre', $titre)

@php
    $s = $donnees['synthese'];
    $champs = view('rapports._select', ['nom' => 'categorie', 'libelle' => 'Catégorie', 'options' => $listes['categories'], 'valeur' => $filtres['categorie'], 'vide' => 'Toutes les catégories'])->render()
        .view('rapports._select', ['nom' => 'jours', 'libelle' => 'Sans vente depuis', 'options' => collect(\App\Rapports\RapportStock::JOURS_SANS_VENTE)->mapWithKeys(fn ($j) => [$j => "Sans vente : {$j} jours"]), 'valeur' => $donnees['joursSansVente'], 'vide' => 'Sans vente : 30 jours'])->render();
    $jetons = collect(range(1, 8))->map(fn ($n) => "graphique-{$n}");
@endphp

@section('page')
    @include('rapports._entete')

    <section aria-label="Synthèse" class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-carte-stat libelle="Valeur du stock (prix d'achat)" :valeur="format_ar($s['valeur'])" icone="boxes" />
        <x-carte-stat libelle="Produits actifs" :valeur="$s['produits']" icone="package" />
        <x-carte-stat libelle="Stock faible" :valeur="$s['faibles']" icone="triangle-alert" />
        <x-carte-stat libelle="Ruptures" :valeur="$s['ruptures']" icone="circle-x" />
    </section>

    <div class="grid gap-4 lg:grid-cols-3">
        <x-carte titre="Valeur par catégorie">
            @if ($donnees['parCategorie']->where('valeur', '>', 0)->isEmpty())
                <x-etat-vide icone="boxes" titre="Stock vide" texte="Aucun produit en stock." />
            @else
                @php($categories = $donnees['parCategorie']->where('valeur', '>', 0)->values())
                <div class="mx-auto mb-4 size-44" x-data="graphiqueRapport({{ \Illuminate\Support\Js::from(['type' => 'doughnut', 'libelles' => $categories->pluck('categorie'), 'series' => [['libelle' => 'Valeur', 'valeurs' => $categories->pluck('valeur'), 'jetons' => $categories->keys()->map(fn ($i) => $jetons[$i % 8])]]]) }})">
                    <canvas x-ref="canvas" role="img" aria-label="Répartition de la valeur du stock par catégorie (détail dans la liste)"></canvas>
                </div>
                <ul class="space-y-1.5 text-sm">
                    @foreach ($categories as $i => $c)
                        <li class="flex items-center gap-2">
                            <span @class(['size-3 shrink-0 rounded-full', ['bg-graphique-1', 'bg-graphique-2', 'bg-graphique-3', 'bg-graphique-4', 'bg-graphique-5', 'bg-graphique-6', 'bg-graphique-7', 'bg-graphique-8'][$i % 8]]) aria-hidden="true"></span>
                            <span class="flex-1 truncate">{{ $c->categorie }} <span class="text-texte-doux">({{ $c->produits }})</span></span>
                            <span class="chiffres font-medium">{{ format_ar($c->valeur) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-carte>

        <x-carte titre="Mouvements de la période" class="lg:col-span-2">
            @if ($donnees['mouvementsParType']->isEmpty())
                <x-etat-vide icone="history" titre="Aucun mouvement" texte="Aucune entrée ni sortie sur cette période." />
            @else
                <div class="h-56" x-data="graphiqueRapport({{ \Illuminate\Support\Js::from(['type' => 'bar', 'unite' => 'mouvement(s)', 'libelles' => $donnees['mouvementsParType']->map(fn ($l) => $l->type->libelle()), 'series' => [['libelle' => 'Mouvements', 'valeurs' => $donnees['mouvementsParType']->pluck('nombre'), 'jeton' => 'graphique-2']]]) }})">
                    <canvas x-ref="canvas" role="img" aria-label="Nombre de mouvements par type (détail dans la liste)"></canvas>
                </div>
                <ul class="mt-3 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                    @foreach ($donnees['mouvementsParType'] as $l)
                        <li class="flex justify-between gap-3"><span>{{ $l->type->libelle() }}</span><span class="chiffres text-texte-doux">{{ $l->nombre }} · {{ $l->sens === 'entree' ? '+' : '-' }}{{ format_quantite($l->quantite) }}</span></li>
                    @endforeach
                </ul>
                <p class="mt-3 text-sm text-texte-doux">{{ $donnees['nombreMouvements'] }} mouvement(s) au total, tous listés dans l'export Excel
                    (les {{ \App\Rapports\RapportStock::MOUVEMENTS_ECRAN }} plus récents dans le PDF).
                    @droit('stock.voir')<a href="{{ route('stock.mouvements', ['du' => $periode->debut->toDateString(), 'au' => $periode->fin->toDateString()]) }}" class="font-medium text-lien hover:underline">Voir le détail</a>@enddroit</p>
            @endif
        </x-carte>

        @foreach ([
            ['Stock faible', $donnees['faibles'], 'Aucun produit sous le minimum.'],
            ['Ruptures', $donnees['ruptures'], 'Aucune rupture.'],
            ['Sans vente depuis '.$donnees['joursSansVente'].' jours', $donnees['sansVente'], 'Tous les produits se sont vendus récemment.'],
        ] as [$titreListe, $produits, $vide])
            <x-carte :titre="$titreListe.' ('.$produits->count().')'" :padding="false">
                @if ($produits->isEmpty())
                    <p class="px-carte py-6 text-center text-sm text-texte-doux">{{ $vide }}</p>
                @else
                    <ul class="max-h-80 divide-y divide-bordure overflow-auto text-sm">
                        @foreach ($produits as $p)
                            <li class="flex items-center justify-between gap-3 px-carte py-2">
                                <span class="min-w-0">
                                    <a href="{{ route('produits.show', $p->id) }}" class="block truncate font-medium hover:underline">{{ $p->nom }}</a>
                                    <span class="text-xs text-texte-doux">
                                        @if (property_exists($p, 'derniere_vente'))
                                            {{ $p->derniere_vente ? 'Dernière vente '.\Illuminate\Support\Carbon::parse($p->derniere_vente)->translatedFormat('j M Y') : 'Jamais vendu' }}
                                        @else
                                            Minimum {{ format_quantite($p->stock_minimum, $p->unite) }}
                                        @endif
                                    </span>
                                </span>
                                <span class="chiffres shrink-0 text-right">
                                    <span class="block font-semibold">{{ format_quantite($p->stock_actuel, $p->unite) }}</span>
                                    @if (property_exists($p, 'derniere_vente'))<span class="text-xs text-texte-doux">{{ format_ar($p->valeur) }}</span>@endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-carte>
        @endforeach

        <x-carte titre="État actuel du stock" description="Valeur = stock × prix d'achat" class="lg:col-span-3" :padding="false">
            <x-tableau :colonnes="[
                'produit' => 'Produit',
                'categorie' => 'Catégorie',
                'stock' => ['libelle' => 'Stock', 'alignement' => 'droite'],
                'prix' => ['libelle' => 'Prix d\'achat', 'alignement' => 'droite'],
                'valeur' => ['libelle' => 'Valeur', 'alignement' => 'droite'],
                'etat' => 'État',
            ]" class="border-0 shadow-none">
                @foreach ($donnees['etat'] as $p)
                    <x-tableau.ligne>
                        <x-tableau.cellule libelle="Produit" principale>
                            <a href="{{ route('produits.show', $p->id) }}" class="font-medium hover:underline">{{ $p->nom }}</a>
                            <span class="block text-xs font-normal text-texte-doux">{{ $p->reference }}</span>
                        </x-tableau.cellule>
                        <x-tableau.cellule libelle="Catégorie" class="text-texte-doux">{{ $p->categorie }}</x-tableau.cellule>
                        <x-tableau.cellule libelle="Stock" alignement="droite">{{ format_quantite($p->stock_actuel, $p->unite) }}</x-tableau.cellule>
                        <x-tableau.cellule libelle="Prix d'achat" alignement="droite">{{ format_ar($p->prix_achat) }}</x-tableau.cellule>
                        <x-tableau.cellule libelle="Valeur" alignement="droite" class="font-semibold">{{ format_ar($p->valeur) }}</x-tableau.cellule>
                        <x-tableau.cellule libelle="État">
                            <x-badge :couleur="['normal' => 'succes', 'faible' => 'alerte', 'rupture' => 'danger'][$p->etat]">{{ ['normal' => 'En stock', 'faible' => 'Stock faible', 'rupture' => 'Rupture'][$p->etat] }}</x-badge>
                        </x-tableau.cellule>
                    </x-tableau.ligne>
                @endforeach
            </x-tableau>
        </x-carte>
    </div>
@endsection
