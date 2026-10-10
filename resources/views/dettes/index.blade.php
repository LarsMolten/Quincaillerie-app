{{-- Dettes fournisseurs : ce que nous devons à chaque fournisseur, détail par achat et règlement (modale) --}}
@extends('layouts.app')

@section('titre', 'Dettes fournisseurs')

@php
    $classePuce = 'inline-flex h-9 items-center gap-2 rounded-full border border-bordure-forte px-4 text-sm font-medium text-texte-doux transition-colors duration-150 hover:text-texte aria-pressed:border-primaire aria-pressed:bg-primaire-doux aria-pressed:text-lien pointer-coarse:h-11';
@endphp

@section('page')
    <div x-data="reglements">
        <x-entete-page titre="Dettes fournisseurs" description="Sommes dues aux fournisseurs, de la plus élevée à la plus faible."
                       :fil="['Tableau de bord' => route('accueil'), 'Dettes fournisseurs' => null]" />

        <div x-data="listeDynamique" x-on:reglement-enregistre.window="charger(window.location.href)">
            <form x-ref="filtres" method="GET" action="{{ route('dettes.index') }}" role="search"
                  class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <x-recherche id="recherche-dettes" :valeur="$recherche" :raccourci="false" label="Rechercher un fournisseur"
                             placeholder="Nom ou téléphone…" class="sm:w-80" />
                <input type="hidden" name="anciennete" value="{{ $anciennete }}">
                <div x-data="{ anciennete: @js((string) $anciennete) }" role="group" aria-label="Filtrer par ancienneté" class="flex flex-wrap gap-2">
                    @foreach (['' => ['Toutes', null], 'recente' => ['< 30 jours', 'bg-info'], 'moyenne' => ['30–60 jours', 'bg-alerte'], 'ancienne' => ['> 60 jours', 'bg-danger']] as $valeur => [$libelle, $pastille])
                        <button type="button" class="{{ $classePuce }}" x-on:click="anciennete = '{{ $valeur }}'; filtrer('anciennete', anciennete)"
                                aria-pressed="{{ (string) $anciennete === $valeur ? 'true' : 'false' }}" x-bind:aria-pressed="(anciennete === '{{ $valeur }}').toString()">
                            @if ($pastille)<span class="size-2 rounded-full {{ $pastille }}" aria-hidden="true"></span>@endif
                            {{ $libelle }}
                        </button>
                    @endforeach
                </div>
            </form>

            <div x-ref="liste">@include('dettes._liste')</div>

            <template x-ref="squelette"><x-squelette type="ligne" :lignes="6" libelle="Chargement des dettes…" /></template>
        </div>

        @include('paiements._modale-reglement', ['verbe' => 'Régler'])
    </div>
@endsection
