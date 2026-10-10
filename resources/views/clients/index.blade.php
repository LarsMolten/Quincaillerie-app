{{-- Clients : synthèse des créances, recherche instantanée, puces de statut, tableau (Client comptoir en tête) --}}
@extends('layouts.app')

@section('titre', 'Clients')

@section('page')
    <x-entete-page titre="Clients" description="Votre clientèle, ses achats et ses crédits."
                   :fil="['Tableau de bord' => route('accueil'), 'Clients' => null]">
        <x-slot:actions>
            @droit('paiements.gerer')
                <x-bouton :href="route('creances.index')" variante="secondaire" icone="hand-coins">Créances</x-bouton>
            @enddroit
            <x-bouton type="button" icone="plus" x-data x-on:click="$dispatch('nouveau-client')">Nouveau client</x-bouton>
        </x-slot:actions>
    </x-entete-page>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-carte-stat libelle="Clients actifs" :valeur="$synthese['actifs']" icone="users" />
        <x-carte-stat libelle="Créances en cours" :valeur="format_ar($synthese['creance'])" icone="banknote" />
        <x-carte-stat libelle="Clients débiteurs" :valeur="$synthese['debiteurs']" icone="hand-coins" />
    </div>

    <div x-data="listeDynamique">
        <form x-ref="filtres" method="GET" action="{{ route('clients.index') }}" role="search"
              class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <x-recherche id="recherche-clients" :valeur="$recherche" :raccourci="false" label="Rechercher un client"
                         placeholder="Nom, téléphone ou email…" class="sm:w-96" />
            @include('partials.puces-statut', ['statut' => $statut, 'masculin' => true])
            <input type="hidden" name="tri" value="{{ request('tri') }}">
            <input type="hidden" name="ordre" value="{{ request('ordre') }}">
        </form>

        <div x-ref="liste">@include('clients._liste')</div>

        <template x-ref="squelette"><x-squelette type="ligne" :lignes="6" libelle="Chargement des clients…" /></template>
    </div>

    @include('clients._formulaire')
@endsection
