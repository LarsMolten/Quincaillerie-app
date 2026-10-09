{{-- Crédits : clients ayant une créance, du plus gros montant au plus petit, avec l'ancienneté de la dette --}}
@extends('layouts.app')

@section('titre', 'Crédits clients')

@section('page')
    <x-entete-page titre="Crédits clients" description="Sommes restant dues, de la plus élevée à la plus faible."
                   :fil="['Tableau de bord' => route('accueil'), 'Clients' => route('clients.index'), 'Crédits' => null]" />

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-carte-stat libelle="Créances totales" :valeur="format_ar($synthese['total'])" icone="banknote" />
        <x-carte-stat libelle="Clients débiteurs" :valeur="$synthese['debiteurs']" icone="users" />
        <x-carte-stat libelle="Dues depuis plus de 60 jours" :valeur="format_ar($synthese['plusDe60'])" icone="triangle-alert" />
    </div>

    <div x-data="listeDynamique">
        <form x-ref="filtres" method="GET" action="{{ route('clients.credits') }}" role="search"
              class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <x-recherche id="recherche-credits" :valeur="$recherche" :raccourci="false" label="Rechercher un client débiteur"
                         placeholder="Nom ou téléphone…" class="sm:w-80" />
            <input type="hidden" name="anciennete" value="{{ $anciennete }}">
            <div x-data="{ anciennete: @js((string) $anciennete) }" role="group" aria-label="Filtrer par ancienneté" class="flex flex-wrap gap-2">
                @foreach (['' => ['Toutes', null], 'recente' => ['< 30 jours', 'bg-info'], 'moyenne' => ['30–60 jours', 'bg-alerte'], 'ancienne' => ['> 60 jours', 'bg-danger']] as $valeur => [$libelle, $pastille])
                    <button type="button" x-on:click="anciennete = '{{ $valeur }}'; filtrer('anciennete', anciennete)"
                            aria-pressed="{{ (string) $anciennete === $valeur ? 'true' : 'false' }}" x-bind:aria-pressed="(anciennete === '{{ $valeur }}').toString()"
                            class="inline-flex h-9 items-center gap-2 rounded-full border border-bordure-forte px-4 text-sm font-medium text-texte-doux transition-colors duration-150 hover:text-texte aria-pressed:border-primaire aria-pressed:bg-primaire-doux aria-pressed:text-lien pointer-coarse:h-11">
                        @if ($pastille)<span class="size-2 rounded-full {{ $pastille }}" aria-hidden="true"></span>@endif
                        {{ $libelle }}
                    </button>
                @endforeach
            </div>
        </form>

        <div x-ref="liste">@include('clients._liste-credits')</div>

        <template x-ref="squelette"><x-squelette type="ligne" :lignes="6" libelle="Chargement des crédits…" /></template>
    </div>
@endsection
