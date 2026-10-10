{{-- Inventaires physiques : liste (progression du comptage), ouverture d'un nouvel inventaire --}}
@extends('layouts.app')

@section('titre', 'Inventaires')

@php
    $classePuce = 'h-9 rounded-full border border-bordure-forte px-3.5 text-sm font-medium text-texte-doux transition-colors duration-150 hover:text-texte aria-pressed:border-primaire aria-pressed:bg-primaire-doux aria-pressed:text-lien pointer-coarse:h-11';
@endphp

@section('page')
    <x-entete-page titre="Inventaires" description="Comptage physique du stock : les écarts validés corrigent le stock par des ajustements."
                   :fil="['Tableau de bord' => route('accueil'), 'Inventaires' => null]">
        <x-slot:actions>
            <x-bouton type="button" icone="plus" x-data x-on:click="$dispatch('ouvrir-modal', 'nouvel-inventaire')">Nouvel inventaire</x-bouton>
        </x-slot:actions>
    </x-entete-page>

    <div x-data="listeDynamique">
        <form x-ref="filtres" method="GET" action="{{ route('inventaires.index') }}" class="mb-5">
            <input type="hidden" name="statut" value="{{ $statut }}">
            <div x-data="{ statut: @js((string) $statut) }" role="group" aria-label="Statut" class="flex flex-wrap gap-2">
                @foreach (['' => 'Tous', 'en_cours' => 'En cours', 'valide' => 'Validés'] as $valeur => $libelle)
                    <button type="button" class="{{ $classePuce }}" x-on:click="statut = '{{ $valeur }}'; filtrer('statut', statut)"
                            aria-pressed="{{ (string) $statut === $valeur ? 'true' : 'false' }}" x-bind:aria-pressed="(statut === '{{ $valeur }}').toString()">{{ $libelle }}</button>
                @endforeach
            </div>
        </form>

        <div x-ref="liste">@include('inventaires._liste')</div>

        <template x-ref="squelette"><x-squelette type="ligne" :lignes="5" libelle="Chargement des inventaires…" /></template>
    </div>

    {{-- Ouverture d'un inventaire --}}
    <x-modal id="nouvel-inventaire" titre="Nouvel inventaire" taille="md"
             description="Le stock actuel de chaque produit est relevé ; l'écart sera calculé sur le stock au moment de la validation.">
        <form method="POST" action="{{ route('inventaires.store') }}" x-chargement-envoi novalidate class="space-y-4"
              x-data="{ perimetre: @js(old('perimetre', 'tous')) }">
            @csrf
            <fieldset>
                <legend class="mb-2 text-sm font-medium">Produits à compter</legend>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach (['tous' => 'Tous les produits actifs', 'categories' => 'Certaines catégories'] as $valeur => $libelle)
                        <label class="flex h-11 cursor-pointer items-center gap-2 rounded-controle border border-bordure-forte px-3 text-sm has-checked:border-primaire has-checked:bg-primaire-doux">
                            <input type="radio" name="perimetre" value="{{ $valeur }}" x-model="perimetre" class="accent-primaire"> {{ $libelle }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <fieldset x-show="perimetre === 'categories'" x-cloak>
                <legend class="mb-2 text-sm font-medium">Catégories</legend>
                <div class="grid max-h-60 gap-1 overflow-auto rounded-controle border border-bordure p-2 sm:grid-cols-2">
                    @foreach ($categories as $categorie)
                        <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-controle px-2 text-sm hover:bg-fond">
                            <input type="checkbox" name="categories[]" value="{{ $categorie->id }}" class="size-4 accent-primaire" @checked(in_array($categorie->id, old('categories', [])))>
                            <span class="flex-1">{{ $categorie->nom }}</span>
                            <span class="chiffres text-xs text-texte-doux">{{ $categorie->produits_count }}</span>
                        </label>
                    @endforeach
                </div>
                @error('categories')<p class="mt-1.5 text-sm text-danger-texte">{{ $message }}</p>@enderror
            </fieldset>
            <x-textarea nom="notes" label="Notes" :lignes="2" maxlength="500" placeholder="Ex. inventaire de fin de mois, rayon ciment…" />
            <div class="-mx-6 -mb-6 flex flex-col-reverse gap-3 border-t border-bordure px-6 py-4 sm:flex-row sm:justify-end">
                <x-bouton type="button" variante="secondaire" x-on:click="$dispatch('fermer-modal', 'nouvel-inventaire')">Annuler</x-bouton>
                <x-bouton icone="clipboard-list">Ouvrir l'inventaire</x-bouton>
            </div>
        </form>
    </x-modal>
    @if ($errors->any())
        <div x-data x-init="$nextTick(() => $dispatch('ouvrir-modal', 'nouvel-inventaire'))"></div>
    @endif
@endsection
