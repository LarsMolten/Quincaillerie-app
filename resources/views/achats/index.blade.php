{{-- Liste des achats : synthèse de la période, puces (période, paiement), fournisseur, tableau --}}
@extends('layouts.app')

@section('titre', 'Achats')

@php
    $classePuce = 'h-9 rounded-full border border-bordure-forte px-3.5 text-sm font-medium whitespace-nowrap text-texte-doux transition-colors duration-150 hover:text-texte aria-pressed:border-primaire aria-pressed:bg-primaire-doux aria-pressed:text-lien pointer-coarse:h-11';
@endphp

@section('page')
    <x-entete-page titre="Achats" description="Approvisionnements, règlements et dettes fournisseurs."
                   :fil="['Tableau de bord' => route('accueil'), 'Achats' => null]">
        <x-slot:actions>
            @droit('achats.creer')
                <x-bouton :href="route('achats.create')" icone="plus">Nouvel achat</x-bouton>
            @enddroit
        </x-slot:actions>
    </x-entete-page>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-carte-stat libelle="Achats de la période" :valeur="format_ar($synthese['total'])" icone="truck" />
        <x-carte-stat libelle="Nombre d'achats" :valeur="$synthese['nombre']" icone="receipt" />
        <x-carte-stat libelle="Dette fournisseurs (total)" :valeur="format_ar($synthese['dette'])" icone="wallet" />
    </div>

    <div x-data="listeDynamique">
        <form x-ref="filtres" method="GET" action="{{ route('achats.index') }}" role="search">
            {{-- x-data sur un élément interne : x-ref="filtres" doit appartenir au composant listeDynamique --}}
            <div class="mb-5 space-y-3" x-data="{ periode: @js($filtres['periode']), paiement: @js((string) $filtres['paiement']) }">
            <input type="hidden" name="periode" value="{{ $filtres['periode'] }}">
            <input type="hidden" name="paiement" value="{{ $filtres['paiement'] }}">

            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <x-recherche id="recherche-achats" :valeur="$filtres['recherche']" :raccourci="false" label="Rechercher un achat par numéro"
                             placeholder="N° d'achat (ACH-…)" class="lg:w-72" />
                <div class="w-full lg:w-72">
                    <label for="filtre-fournisseur" class="sr-only">Fournisseur</label>
                    <div class="relative">
                        <select id="filtre-fournisseur" name="fournisseur" x-on:change="chargerDepuisFiltres()"
                                class="h-11 w-full appearance-none rounded-controle border border-bordure-forte bg-surface pl-3 pr-10 text-sm text-texte">
                            <option value="">Tous les fournisseurs</option>
                            @foreach ($fournisseurs as $fournisseur)
                                <option value="{{ $fournisseur->id }}" @selected($filtres['fournisseur'] === $fournisseur->id)>{{ $fournisseur->nom }}</option>
                            @endforeach
                        </select>
                        <x-icone nom="chevron-down" taille="size-4" class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-texte-doux" />
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div role="group" aria-label="Période" class="flex flex-wrap gap-2">
                    @foreach (\App\Http\Controllers\AchatController::PERIODES as $valeur => $libelle)
                        <button type="button" class="{{ $classePuce }}" x-on:click="periode = '{{ $valeur }}'; filtrer('periode', periode)"
                                aria-pressed="{{ $filtres['periode'] === $valeur ? 'true' : 'false' }}" x-bind:aria-pressed="(periode === '{{ $valeur }}').toString()">{{ $libelle }}</button>
                    @endforeach
                </div>
                <span class="mx-1 hidden h-6 w-px bg-bordure sm:block" aria-hidden="true"></span>
                <div role="group" aria-label="Paiement" class="flex flex-wrap gap-2">
                    @foreach (['' => ['Tous', null], 'paye' => ['Payé', 'bg-succes'], 'partiel' => ['Partiel', 'bg-alerte'], 'credit' => ['À crédit', 'bg-danger'], 'annules' => ['Annulés', 'bg-neutre']] as $valeur => [$libelle, $pastille])
                        <button type="button" class="{{ $classePuce }} inline-flex items-center gap-2" x-on:click="paiement = '{{ $valeur }}'; filtrer('paiement', paiement)"
                                aria-pressed="{{ (string) $filtres['paiement'] === $valeur ? 'true' : 'false' }}" x-bind:aria-pressed="(paiement === '{{ $valeur }}').toString()">
                            @if ($pastille)<span class="size-2 rounded-full {{ $pastille }}" aria-hidden="true"></span>@endif
                            {{ $libelle }}
                        </button>
                    @endforeach
                </div>
            </div>
            </div>
        </form>

        <div x-ref="liste">@include('achats._liste')</div>

        <template x-ref="squelette"><x-squelette type="ligne" :lignes="6" libelle="Chargement des achats…" /></template>
    </div>
@endsection
