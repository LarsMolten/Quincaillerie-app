{{-- Paiements : totaux par mode, recherche, puces (période, mode, sens), historique avec reçus PDF --}}
@extends('layouts.app')

@section('titre', 'Paiements')

@php
    $classePuce = 'h-9 rounded-full border border-bordure-forte px-3.5 text-sm font-medium whitespace-nowrap text-texte-doux transition-colors duration-150 hover:text-texte aria-pressed:border-primaire aria-pressed:bg-primaire-doux aria-pressed:text-lien pointer-coarse:h-11';
    $icones = ['especes' => 'banknote', 'mobile_money' => 'smartphone', 'virement' => 'landmark', 'cheque' => 'receipt'];
@endphp

@section('page')
    <x-entete-page titre="Paiements" :description="$voitAchats ? 'Encaissements clients et règlements fournisseurs, avec leur reçu.' : 'Encaissements clients, avec leur reçu.'"
                   :fil="['Tableau de bord' => route('accueil'), 'Paiements' => null]">
        <x-slot:actions>
            <x-bouton :href="route('creances.index')" variante="secondaire" icone="hand-coins">Créances</x-bouton>
            @if ($voitAchats)
                <x-bouton :href="route('dettes.index')" variante="secondaire" icone="banknote">Dettes fournisseurs</x-bouton>
            @endif
        </x-slot:actions>
    </x-entete-page>

    {{-- Totaux par mode sur la période et la recherche --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($parMode as $total)
            <x-carte-stat :libelle="$total['mode']->libelle()" :valeur="format_ar($total['encaisse'])" :icone="$icones[$total['mode']->value] ?? 'credit-card'">
                <span class="chiffres">{{ $total['nombre'] }} paiement(s)@if ($voitAchats && $total['decaisse'] > 0) · décaissé {{ format_ar($total['decaisse']) }}@endif</span>
            </x-carte-stat>
        @endforeach
    </div>

    <div x-data="listeDynamique">
        <form x-ref="filtres" method="GET" action="{{ route('paiements.index') }}" role="search">
            <div class="mb-5 space-y-3" x-data="{ periode: @js($filtres['periode']), mode: @js((string) $filtres['mode']), sens: @js((string) $filtres['sens']) }">
                <input type="hidden" name="periode" value="{{ $filtres['periode'] }}">
                <input type="hidden" name="mode" value="{{ $filtres['mode'] }}">
                <input type="hidden" name="sens" value="{{ $filtres['sens'] }}">

                <x-recherche id="recherche-paiements" :valeur="$filtres['recherche']" :raccourci="false" label="Rechercher un paiement"
                             :placeholder="$voitAchats ? 'N° de reçu, client, fournisseur, document…' : 'N° de reçu, client, facture…'" class="lg:w-96" />

                <div class="flex flex-wrap items-center gap-2">
                    <div role="group" aria-label="Période" class="flex flex-wrap gap-2">
                        @foreach (\App\Http\Controllers\PaiementController::PERIODES as $valeur => $libelle)
                            <button type="button" class="{{ $classePuce }}" x-on:click="periode = '{{ $valeur }}'; filtrer('periode', periode)"
                                    aria-pressed="{{ $filtres['periode'] === $valeur ? 'true' : 'false' }}" x-bind:aria-pressed="(periode === '{{ $valeur }}').toString()">{{ $libelle }}</button>
                        @endforeach
                    </div>
                    <span class="mx-1 hidden h-6 w-px bg-bordure sm:block" aria-hidden="true"></span>
                    <div role="group" aria-label="Mode de paiement" class="flex flex-wrap gap-2">
                        @foreach (\App\Enums\ModePaiement::encaissements() as $mode)
                            <button type="button" class="{{ $classePuce }}"
                                    x-on:click="mode = mode === '{{ $mode->value }}' ? '' : '{{ $mode->value }}'; filtrer('mode', mode)"
                                    aria-pressed="{{ (string) $filtres['mode'] === $mode->value ? 'true' : 'false' }}" x-bind:aria-pressed="(mode === '{{ $mode->value }}').toString()">{{ $mode->libelle() }}</button>
                        @endforeach
                    </div>
                    @if ($voitAchats)
                        <span class="mx-1 hidden h-6 w-px bg-bordure sm:block" aria-hidden="true"></span>
                        <div role="group" aria-label="Sens" class="flex flex-wrap gap-2">
                            @foreach (['encaissements' => ['Encaissements', 'bg-succes'], 'decaissements' => ['Décaissements', 'bg-alerte']] as $valeur => [$libelle, $pastille])
                                <button type="button" class="{{ $classePuce }} inline-flex items-center gap-2"
                                        x-on:click="sens = sens === '{{ $valeur }}' ? '' : '{{ $valeur }}'; filtrer('sens', sens)"
                                        aria-pressed="{{ (string) $filtres['sens'] === $valeur ? 'true' : 'false' }}" x-bind:aria-pressed="(sens === '{{ $valeur }}').toString()">
                                    <span class="size-2 rounded-full {{ $pastille }}" aria-hidden="true"></span>
                                    {{ $libelle }}
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </form>

        <div x-ref="liste">@include('paiements._liste')</div>

        <template x-ref="squelette"><x-squelette type="ligne" :lignes="6" libelle="Chargement des paiements…" /></template>
    </div>
@endsection
