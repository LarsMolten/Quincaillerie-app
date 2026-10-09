{{-- Unités de vente : recherche instantanée, tableau trié, création et modification en modale --}}
@extends('layouts.app')

@section('titre', 'Unités')

@section('page')
    <x-entete-page titre="Unités" description="Unités dans lesquelles vos produits sont vendus : pièce, kilogramme, mètre, sac…"
                   :fil="['Tableau de bord' => route('accueil'), 'Stock' => null, 'Unités' => null]">
        <x-slot:actions>
            <x-bouton type="button" icone="plus" x-data x-on:click="$dispatch('nouvelle-unite')">Nouvelle unité</x-bouton>
        </x-slot:actions>
    </x-entete-page>

    @include('categories._onglets')

    <div x-data="listeDynamique">
        <form x-ref="filtres" method="GET" action="{{ route('unites.index') }}" role="search" class="mb-5">
            <x-recherche id="recherche-unites" :valeur="$recherche" :raccourci="false" label="Rechercher une unité"
                         placeholder="Rechercher par nom ou abréviation…" class="sm:w-80" />
            {{-- Le tri courant est conservé lors d'une recherche --}}
            <input type="hidden" name="tri" value="{{ request('tri') }}">
            <input type="hidden" name="ordre" value="{{ request('ordre') }}">
        </form>

        <div x-ref="liste">
            @include('unites._liste')
        </div>

        <template x-ref="squelette">
            <x-squelette type="ligne" :lignes="6" libelle="Chargement des unités…" />
        </template>
    </div>

    @include('unites._formulaire')
@endsection
