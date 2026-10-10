{{-- Retours clients ou fournisseurs : synthèse, recherche, puces (période, statut), tableau --}}
@extends('layouts.app')

@php
    $client = $type === \App\Enums\TypeRetour::Client;
    $titre = $client ? 'Retours clients' : 'Retours fournisseurs';
    $route = $client ? 'retours.clients' : 'retours.fournisseurs';
    $classePuce = 'h-9 rounded-full border border-bordure-forte px-3.5 text-sm font-medium whitespace-nowrap text-texte-doux transition-colors duration-150 hover:text-texte aria-pressed:border-primaire aria-pressed:bg-primaire-doux aria-pressed:text-lien pointer-coarse:h-11';
@endphp

@section('titre', $titre)

@section('page')
    <x-entete-page :titre="$titre"
                   :description="$client ? 'Produits rapportés par les clients : remis en stock et déduits de la créance ou remboursés.' : 'Produits renvoyés aux fournisseurs : retirés du stock et déduits de la dette ou remboursés.'"
                   :fil="['Tableau de bord' => route('accueil'), $titre => null]">
        <x-slot:actions>
            <x-bouton :href="route('retours.create', ['type' => $type->value])" icone="plus">Nouveau retour</x-bouton>
        </x-slot:actions>
    </x-entete-page>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-carte-stat libelle="Retours validés" :valeur="$synthese['nombre']" icone="undo-2" />
        <x-carte-stat libelle="Montant retourné" :valeur="format_ar($synthese['total'])" icone="receipt" />
        <x-carte-stat :libelle="$client ? 'Remboursé aux clients' : 'Remboursé par les fournisseurs'" :valeur="format_ar($synthese['rembourse'])" icone="banknote">
            <span class="chiffres">{{ format_ar($synthese['avoir']) }} déduits {{ $client ? 'des créances' : 'des dettes' }}</span>
        </x-carte-stat>
    </div>

    <div x-data="listeDynamique">
        <form x-ref="filtres" method="GET" action="{{ route($route) }}" role="search">
            <div class="mb-5 space-y-3" x-data="{ periode: @js($filtres['periode']), statut: @js((string) $filtres['statut']) }">
                <input type="hidden" name="periode" value="{{ $filtres['periode'] }}">
                <input type="hidden" name="statut" value="{{ $filtres['statut'] }}">

                <x-recherche id="recherche-retours" :valeur="$filtres['recherche']" :raccourci="false" label="Rechercher un retour"
                             :placeholder="$client ? 'N° de retour, de vente, de facture, client, motif…' : 'N° de retour, d’achat, fournisseur, motif…'" class="lg:w-96" />

                <div class="flex flex-wrap items-center gap-2">
                    <div role="group" aria-label="Période" class="flex flex-wrap gap-2">
                        @foreach (\App\Http\Controllers\RetourController::PERIODES as $valeur => $libelle)
                            <button type="button" class="{{ $classePuce }}" x-on:click="periode = '{{ $valeur }}'; filtrer('periode', periode)"
                                    aria-pressed="{{ $filtres['periode'] === $valeur ? 'true' : 'false' }}" x-bind:aria-pressed="(periode === '{{ $valeur }}').toString()">{{ $libelle }}</button>
                        @endforeach
                    </div>
                    <span class="mx-1 hidden h-6 w-px bg-bordure sm:block" aria-hidden="true"></span>
                    <div role="group" aria-label="Statut" class="flex flex-wrap gap-2">
                        @foreach (['' => ['Tous', null], 'valides' => ['Validés', 'bg-succes'], 'annules' => ['Annulés', 'bg-danger']] as $valeur => [$libelle, $pastille])
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

        <div x-ref="liste">@include('retours._liste')</div>

        <template x-ref="squelette"><x-squelette type="ligne" :lignes="6" libelle="Chargement des retours…" /></template>
    </div>
@endsection
