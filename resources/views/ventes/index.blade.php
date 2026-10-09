{{-- Liste des ventes : synthèse de la période, puces (période, statut), client, vendeur, tableau --}}
@extends('layouts.app')

@section('titre', 'Ventes')

@php
    $classePuce = 'h-9 rounded-full border border-bordure-forte px-3.5 text-sm font-medium whitespace-nowrap text-texte-doux transition-colors duration-150 hover:text-texte aria-pressed:border-primaire aria-pressed:bg-primaire-doux aria-pressed:text-lien pointer-coarse:h-11';
    $classeSelect = 'h-11 w-full appearance-none rounded-controle border border-bordure-forte bg-surface pl-3 pr-10 text-sm text-texte';
@endphp

@section('page')
    <x-entete-page titre="Ventes" description="Tickets de caisse, règlements et annulations."
                   :fil="['Tableau de bord' => route('accueil'), 'Ventes' => null]">
        <x-slot:actions>
            @droit('ventes.creer')
                <x-bouton :href="route('ventes.create')" icone="shopping-cart">Nouvelle vente</x-bouton>
            @enddroit
        </x-slot:actions>
    </x-entete-page>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-carte-stat libelle="Chiffre d'affaires de la période" :valeur="format_ar($synthese['chiffre'])" icone="banknote" />
        <x-carte-stat libelle="Nombre de ventes" :valeur="$synthese['nombre']" icone="receipt" />
        <x-carte-stat libelle="Panier moyen" :valeur="format_ar($synthese['panier'])" icone="shopping-basket" />
    </div>

    <div x-data="listeDynamique">
        <form x-ref="filtres" method="GET" action="{{ route('ventes.index') }}" role="search">
            {{-- x-data sur un élément interne : x-ref="filtres" doit appartenir au composant listeDynamique --}}
            <div class="mb-5 space-y-3" x-data="{ periode: @js($filtres['periode']), statut: @js((string) $filtres['statut']) }">
                <input type="hidden" name="periode" value="{{ $filtres['periode'] }}">
                <input type="hidden" name="statut" value="{{ $filtres['statut'] }}">

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[18rem_1fr_1fr]">
                    <x-recherche id="recherche-ventes" :valeur="$filtres['recherche']" :raccourci="false" label="Rechercher une vente par numéro"
                                 placeholder="N° de vente (VTE-…)" class="sm:col-span-2 lg:col-span-1" />
                    <div>
                        <label for="filtre-client" class="sr-only">Client</label>
                        <div class="relative">
                            <select id="filtre-client" name="client" x-on:change="chargerDepuisFiltres()" class="{{ $classeSelect }}">
                                <option value="">Tous les clients</option>
                                @foreach ($clients as $client)
                                    <option value="{{ $client->id }}" @selected($filtres['client'] === $client->id)>{{ $client->nom }}</option>
                                @endforeach
                            </select>
                            <x-icone nom="chevron-down" taille="size-4" class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-texte-doux" />
                        </div>
                    </div>
                    <div>
                        <label for="filtre-vendeur" class="sr-only">Vendeur</label>
                        <div class="relative">
                            <select id="filtre-vendeur" name="vendeur" x-on:change="chargerDepuisFiltres()" class="{{ $classeSelect }}">
                                <option value="">Tous les vendeurs</option>
                                @foreach ($vendeurs as $vendeur)
                                    <option value="{{ $vendeur->id }}" @selected($filtres['vendeur'] === $vendeur->id)>{{ $vendeur->nom }}</option>
                                @endforeach
                            </select>
                            <x-icone nom="chevron-down" taille="size-4" class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-texte-doux" />
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <div role="group" aria-label="Période" class="flex flex-wrap gap-2">
                        @foreach (\App\Http\Controllers\VenteController::PERIODES as $valeur => $libelle)
                            <button type="button" class="{{ $classePuce }}" x-on:click="periode = '{{ $valeur }}'; filtrer('periode', periode)"
                                    aria-pressed="{{ $filtres['periode'] === $valeur ? 'true' : 'false' }}" x-bind:aria-pressed="(periode === '{{ $valeur }}').toString()">{{ $libelle }}</button>
                        @endforeach
                    </div>
                    <span class="mx-1 hidden h-6 w-px bg-bordure sm:block" aria-hidden="true"></span>
                    <div role="group" aria-label="Statut" class="flex flex-wrap gap-2">
                        @foreach (['' => ['Toutes', null], 'validees' => ['Validées', 'bg-succes'], 'credit' => ['Reste à payer', 'bg-alerte'], 'annulees' => ['Annulées', 'bg-danger']] as $valeur => [$libelle, $pastille])
                            <button type="button" class="{{ $classePuce }} inline-flex items-center gap-2" x-on:click="statut = '{{ $valeur }}'; filtrer('statut', statut)"
                                    aria-pressed="{{ (string) $filtres['statut'] === $valeur ? 'true' : 'false' }}" x-bind:aria-pressed="(statut === '{{ $valeur }}').toString()">
                                @if ($pastille)<span class="size-2 rounded-full {{ $pastille }}" aria-hidden="true"></span>@endif
                                {{ $libelle }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </form>

        <div x-ref="liste">@include('ventes._liste')</div>

        <template x-ref="squelette"><x-squelette type="ligne" :lignes="6" libelle="Chargement des ventes…" /></template>
    </div>
@endsection
