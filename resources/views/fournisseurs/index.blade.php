{{-- Fournisseurs : synthèse des dettes, recherche instantanée, puces de statut, tableau --}}
@extends('layouts.app')

@section('titre', 'Fournisseurs')

@section('page')
    <x-entete-page titre="Fournisseurs" description="Vos partenaires d'approvisionnement et ce que vous leur devez."
                   :fil="['Tableau de bord' => route('accueil'), 'Achats' => null, 'Fournisseurs' => null]">
        <x-slot:actions>
            <x-bouton type="button" icone="plus" x-data x-on:click="$dispatch('nouveau-fournisseur')">Nouveau fournisseur</x-bouton>
        </x-slot:actions>
    </x-entete-page>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-carte-stat libelle="Fournisseurs actifs" :valeur="$synthese['actifs']" icone="store" />
        <x-carte-stat libelle="Dette totale" :valeur="format_ar($synthese['dette'])" icone="wallet" />
        <x-carte-stat libelle="Fournisseurs à régler" :valeur="$synthese['aRegler']" icone="hand-coins" />
    </div>

    <div x-data="listeDynamique">
        <form x-ref="filtres" method="GET" action="{{ route('fournisseurs.index') }}" role="search"
              class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <x-recherche id="recherche-fournisseurs" :valeur="$recherche" :raccourci="false" label="Rechercher un fournisseur"
                         placeholder="Nom, contact, téléphone ou email…" class="sm:w-96" />
            @include('partials.puces-statut', ['statut' => $statut, 'masculin' => true])
            <input type="hidden" name="tri" value="{{ request('tri') }}">
            <input type="hidden" name="ordre" value="{{ request('ordre') }}">
        </form>

        <div x-ref="liste">@include('fournisseurs._liste')</div>

        <template x-ref="squelette"><x-squelette type="ligne" :lignes="6" libelle="Chargement des fournisseurs…" /></template>
    </div>

    @include('fournisseurs._formulaire')
@endsection
