{{-- Mouvements de stock (et vues Entrées / Sorties) : filtres, tableau, export Excel, ajustement manuel --}}
@extends('layouts.app')

@php
    $titre = match ($sens) {
        \App\Enums\SensMouvement::Entree => 'Entrées de stock',
        \App\Enums\SensMouvement::Sortie => 'Sorties de stock',
        default => 'Mouvements de stock',
    };
    $route = match ($sens) {
        \App\Enums\SensMouvement::Entree => 'stock.entrees',
        \App\Enums\SensMouvement::Sortie => 'stock.sorties',
        default => 'stock.mouvements',
    };
    $classePuce = 'h-9 rounded-full border border-bordure-forte px-3.5 text-sm font-medium whitespace-nowrap text-texte-doux transition-colors duration-150 hover:text-texte aria-pressed:border-primaire aria-pressed:bg-primaire-doux aria-pressed:text-lien pointer-coarse:h-11';
    $classeChamp = 'h-11 rounded-controle border border-bordure-forte bg-surface px-3 text-sm text-texte';
    $peutAjuster = auth()->user()->can('stock.ajuster') && $sens !== null;
    $typesAjustement = $sens === \App\Enums\SensMouvement::Entree
        ? [\App\Enums\TypeMouvementStock::AjustementPositif]
        : [\App\Enums\TypeMouvementStock::Perte, \App\Enums\TypeMouvementStock::AjustementNegatif];
@endphp

@section('titre', $titre)

@section('page')
    <x-entete-page :titre="$titre"
                   :description="match ($sens) {
                       \App\Enums\SensMouvement::Entree => 'Achats, retours clients et ajustements positifs.',
                       \App\Enums\SensMouvement::Sortie => 'Ventes, retours fournisseurs, pertes et ajustements négatifs.',
                       default => 'Historique complet des entrées et sorties, avec le document d’origine.',
                   }"
                   :fil="['Tableau de bord' => route('accueil'), $titre => null]">
        <x-slot:actions>
            <x-bouton :href="route('stock.mouvements.export')" variante="secondaire" icone="download"
                      x-data x-on:click="$el.href = {{ \Illuminate\Support\Js::from(route('stock.mouvements.export')) }} + (location.search || '?') + {{ \Illuminate\Support\Js::from($sens ? '&sens='.$sens->value : '') }}">
                Exporter (Excel)
            </x-bouton>
            @if ($peutAjuster)
                <x-bouton type="button" :icone="$sens === \App\Enums\SensMouvement::Entree ? 'circle-plus' : 'triangle-alert'"
                          x-data x-on:click="$dispatch('ouvrir-modal', 'modale-ajustement')">
                    {{ $sens === \App\Enums\SensMouvement::Entree ? 'Ajustement d’entrée' : 'Perte ou ajustement' }}
                </x-bouton>
            @endif
        </x-slot:actions>
    </x-entete-page>

    <div x-data="listeDynamique" x-on:ajustement-enregistre.window="charger(window.location.href)">
        <form x-ref="filtres" method="GET" action="{{ route($route) }}" role="search" x-on:change="$event.target.type !== 'search' && chargerDepuisFiltres()">
            <div class="mb-5 space-y-3" x-data="{ periode: @js($filtres['periode']), sens: @js((string) $filtres['sens']) }">
                <input type="hidden" name="periode" value="{{ $filtres['periode'] }}">
                @unless ($sens)<input type="hidden" name="sens" value="{{ $filtres['sens'] }}">@endunless
                @if ($produit)<input type="hidden" name="produit" value="{{ $produit->id }}">@endif

                <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                    <x-recherche id="recherche-mouvements" :valeur="$filtres['recherche']" :raccourci="false" label="Rechercher un produit"
                                 placeholder="Produit : nom, référence, code-barres…" class="lg:w-80" />
                    <label class="sr-only" for="filtre-type">Type de mouvement</label>
                    <select id="filtre-type" name="type" class="{{ $classeChamp }} lg:w-52">
                        <option value="">Tous les types</option>
                        @foreach ($types as $type)
                            <option value="{{ $type->value }}" @selected($filtres['type'] === $type->value)>{{ $type->libelle() }}</option>
                        @endforeach
                    </select>
                    <label class="sr-only" for="filtre-utilisateur">Utilisateur</label>
                    <select id="filtre-utilisateur" name="utilisateur" class="{{ $classeChamp }} lg:w-52">
                        <option value="">Tous les utilisateurs</option>
                        @foreach ($utilisateurs as $utilisateur)
                            <option value="{{ $utilisateur->id }}" @selected($filtres['utilisateur'] === $utilisateur->id)>{{ $utilisateur->nom }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <div role="group" aria-label="Période" class="flex flex-wrap gap-2">
                        @foreach (\App\Rapports\FiltresMouvements::PERIODES as $valeur => $libelle)
                            <button type="button" class="{{ $classePuce }}" x-on:click="periode = '{{ $valeur }}'; $refs.du.value = ''; $refs.au.value = ''; filtrer('periode', periode)"
                                    aria-pressed="{{ $filtres['periode'] === $valeur ? 'true' : 'false' }}" x-bind:aria-pressed="(periode === '{{ $valeur }}').toString()">{{ $libelle }}</button>
                        @endforeach
                    </div>
                    <div class="flex items-center gap-2 text-sm text-texte-doux">
                        <label for="filtre-du">Du</label>
                        <input id="filtre-du" x-ref="du" type="date" name="du" value="{{ $filtres['du'] }}" max="{{ today()->toDateString() }}" class="{{ $classeChamp }} h-9 pointer-coarse:h-11">
                        <label for="filtre-au">au</label>
                        <input id="filtre-au" x-ref="au" type="date" name="au" value="{{ $filtres['au'] }}" max="{{ today()->toDateString() }}" class="{{ $classeChamp }} h-9 pointer-coarse:h-11">
                    </div>
                    @unless ($sens)
                        <span class="mx-1 hidden h-6 w-px bg-bordure sm:block" aria-hidden="true"></span>
                        <div role="group" aria-label="Sens" class="flex flex-wrap gap-2">
                            @foreach (['entree' => ['Entrées', 'bg-succes'], 'sortie' => ['Sorties', 'bg-danger']] as $valeur => [$libelle, $pastille])
                                <button type="button" class="{{ $classePuce }} inline-flex items-center gap-2"
                                        x-on:click="sens = sens === '{{ $valeur }}' ? '' : '{{ $valeur }}'; filtrer('sens', sens)"
                                        aria-pressed="{{ (string) $filtres['sens'] === $valeur ? 'true' : 'false' }}" x-bind:aria-pressed="(sens === '{{ $valeur }}').toString()">
                                    <span class="size-2 rounded-full {{ $pastille }}" aria-hidden="true"></span>
                                    {{ $libelle }}
                                </button>
                            @endforeach
                        </div>
                    @endunless
                    @if ($produit)
                        <a href="{{ route($route) }}" class="inline-flex h-9 items-center gap-2 rounded-full border border-primaire bg-primaire-doux px-3.5 text-sm font-medium text-lien pointer-coarse:h-11">
                            Produit : {{ $produit->nom }} <x-icone nom="x" taille="size-4" /><span class="sr-only">Retirer ce filtre</span>
                        </a>
                    @endif
                </div>
            </div>
        </form>

        <div x-ref="liste">@include('stock._liste')</div>

        <template x-ref="squelette"><x-squelette type="ligne" :lignes="8" libelle="Chargement des mouvements…" /></template>
    </div>

    @if ($peutAjuster)
        @include('stock._modale-ajustement', ['types' => $typesAjustement])
    @endif
@endsection
