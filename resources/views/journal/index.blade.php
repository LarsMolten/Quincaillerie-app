{{-- Administration > Journal d'activité : filtres (période, utilisateur, module, type, recherche) et chronologie en lecture seule --}}
@extends('layouts.app')

@section('titre', 'Journal d\'activité')

@section('page')
    <x-entete-page titre="Journal d'activité" description="Qui a fait quoi, et quand. Le journal ne peut être ni modifié ni effacé."
                   :fil="['Tableau de bord' => route('accueil'), 'Journal d\'activité' => null]" />

    @php
        $classeSelect = 'h-11 w-full rounded-controle border border-bordure-forte bg-surface px-3 text-sm text-texte';
    @endphp

    <div x-data="listeDynamique">
        <form x-ref="filtres" method="GET" action="{{ route('journal.index') }}" role="search" class="mb-6 space-y-4">
            <x-filtre-periode :periode="$periode" />
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <x-recherche id="recherche-journal" :valeur="$filtres['recherche']" :raccourci="false" label="Rechercher dans le journal"
                             placeholder="Objet, action, adresse IP…" />
                <div>
                    <label for="filtre-utilisateur" class="sr-only">Utilisateur</label>
                    <select id="filtre-utilisateur" name="utilisateur" x-on:change="chargerDepuisFiltres()" class="{{ $classeSelect }}">
                        <option value="">Tous les utilisateurs</option>
                        @foreach ($utilisateurs as $id => $nom)
                            <option value="{{ $id }}" @selected($filtres['utilisateur'] === $id)>{{ $nom }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="filtre-module" class="sr-only">Module</label>
                    <select id="filtre-module" name="module" x-on:change="chargerDepuisFiltres()" class="{{ $classeSelect }}">
                        <option value="">Tous les modules</option>
                        @foreach (\App\Support\LibelleJournal::MODULES as $code => [, $libelle])
                            <option value="{{ $code }}" @selected($filtres['module'] === $code)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="filtre-type" class="sr-only">Type d'action</label>
                    <select id="filtre-type" name="type" x-on:change="chargerDepuisFiltres()" class="{{ $classeSelect }}">
                        <option value="">Toutes les actions</option>
                        @foreach (\App\Support\LibelleJournal::TYPES as $code => [$libelle])
                            <option value="{{ $code }}" @selected($filtres['type'] === $code)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </form>

        <div x-ref="liste">@include('journal._liste')</div>

        <template x-ref="squelette"><x-squelette type="ligne" :lignes="8" libelle="Chargement du journal…" /></template>
    </div>
@endsection
