{{-- Catégories : synthèse, recherche instantanée, filtres, cartes, création et modification en modale --}}
@extends('layouts.app')

@section('titre', 'Catégories')

@section('page')
    <x-entete-page titre="Catégories" description="Classez vos produits pour les retrouver vite, en caisse comme en stock."
                   :fil="['Tableau de bord' => route('accueil'), 'Stock' => null, 'Catégories' => null]">
        <x-slot:actions>
            <x-bouton type="button" icone="plus" x-data x-on:click="$dispatch('nouvelle-categorie')">Nouvelle catégorie</x-bouton>
        </x-slot:actions>
    </x-entete-page>

    @include('categories._onglets')

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-carte-stat libelle="Catégories actives" :valeur="$synthese['actives'].' / '.($synthese['actives'] + $synthese['inactives'])" icone="tags" />
        <x-carte-stat libelle="Produits classés" :valeur="$synthese['produits']" icone="package" />
        <x-carte-stat libelle="Catégories sans produit" :valeur="$synthese['vides']" icone="inbox" />
    </div>

    <div x-data="listeDynamique">
        <form x-ref="filtres" method="GET" action="{{ route('categories.index') }}" role="search"
              class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <x-recherche id="recherche-categories" :valeur="$recherche" :raccourci="false" label="Rechercher une catégorie"
                         placeholder="Rechercher une catégorie…" class="sm:w-80" />

            <input type="hidden" name="statut" value="{{ $statut }}">
            <div x-data="{ statut: @js((string) $statut) }" role="group" aria-label="Filtrer par statut" class="flex flex-wrap gap-2">
                @foreach (['' => 'Toutes', 'actives' => 'Actives', 'inactives' => 'Inactives'] as $valeur => $libelle)
                    <button type="button"
                            x-on:click="statut = '{{ $valeur }}'; filtrer('statut', statut)"
                            aria-pressed="{{ (string) $statut === $valeur ? 'true' : 'false' }}"
                            x-bind:aria-pressed="(statut === '{{ $valeur }}').toString()"
                            class="h-9 rounded-full border border-bordure-forte px-4 text-sm font-medium text-texte-doux transition-colors duration-150 hover:text-texte aria-pressed:border-primaire aria-pressed:bg-primaire-doux aria-pressed:text-lien pointer-coarse:h-11">
                        {{ $libelle }}
                    </button>
                @endforeach
            </div>
        </form>

        <div x-ref="liste">
            @include('categories._liste')
        </div>

        {{-- Squelette affiché pendant le rechargement de la liste --}}
        <template x-ref="squelette">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @for ($i = 0; $i < 6; $i++)
                    <x-squelette type="stat" libelle="Chargement des catégories…" />
                @endfor
            </div>
        </template>
    </div>

    @include('categories._formulaire')
@endsection
