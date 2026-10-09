{{-- Catalogue des produits : synthèse, recherche instantanée, puces de filtre, tableau ou grille --}}
@extends('layouts.app')

@section('titre', 'Produits')

@section('page')
    <x-entete-page titre="Produits" description="Catalogue, prix et niveau de stock de chaque article."
                   :fil="['Tableau de bord' => route('accueil'), 'Stock' => null, 'Produits' => null]">
        <x-slot:actions>
            @droit('produits.creer')
                <x-bouton type="button" icone="plus" x-data x-on:click="$dispatch('nouveau-produit')">Nouveau produit</x-bouton>
            @enddroit
        </x-slot:actions>
    </x-entete-page>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-carte-stat libelle="Produits actifs" :valeur="$synthese['actifs']" icone="package" />
        <x-carte-stat libelle="Stock faible" :valeur="$synthese['faibles']" icone="triangle-alert" />
        <x-carte-stat libelle="En rupture" :valeur="$synthese['ruptures']" icone="ban" />
        <x-carte-stat libelle="Valeur du stock (achat)" :valeur="format_ar($synthese['valeur'])" icone="wallet" />
    </div>

    {{-- selection : produits cochés (vue tableau) pour l'impression d'étiquettes ; remise à zéro à chaque rechargement --}}
    <div x-data="{ selection: [] }"
         x-on:change="if ($event.target.matches('[data-selection]')) selection = [...$el.querySelectorAll('[data-selection]:checked')].map(c => c.value)"
         x-on:liste-chargee="selection = []">
    <div x-data="listeDynamique">
        <form x-ref="filtres" method="GET" action="{{ route('produits.index') }}" role="search" class="mb-5 space-y-3">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <x-recherche id="recherche-produits" :valeur="$filtres['recherche']" :raccourci="false"
                             label="Rechercher un produit (nom, référence ou code-barres)"
                             placeholder="Nom, référence ou code-barres (scanner)…" class="lg:w-96" />

                <div class="flex flex-wrap items-center gap-2">
                    {{-- Étiquettes pour la sélection (vue tableau) --}}
                    <x-bouton type="button" variante="secondaire" taille="sm" icone="printer"
                              x-show="selection.length > 0" x-cloak
                              x-on:click="$dispatch('etiquettes-produits', { produits: selection })">
                        <span x-text="'Étiquettes (' + selection.length + ')'">Étiquettes</span>
                    </x-bouton>

                    {{-- Bascule tableau / grille --}}
                    <div x-data="{ vue: @js($vue) }" role="group" aria-label="Affichage" class="inline-flex rounded-controle border border-bordure-forte p-0.5">
                        @foreach (['tableau' => ['Tableau', 'clipboard-list'], 'grille' => ['Grille', 'boxes']] as $valeur => [$libelle, $icone])
                            <button type="button" x-on:click="vue = '{{ $valeur }}'; filtrer('vue', vue)"
                                    aria-pressed="{{ $vue === $valeur ? 'true' : 'false' }}" x-bind:aria-pressed="(vue === '{{ $valeur }}').toString()"
                                    class="inline-flex h-9 items-center gap-1.5 rounded-lg px-3 text-sm font-medium text-texte-doux transition-colors duration-150 hover:text-texte aria-pressed:bg-primaire-doux aria-pressed:text-lien pointer-coarse:h-11">
                                <x-icone :nom="$icone" taille="size-4" /> {{ $libelle }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            <input type="hidden" name="vue" value="{{ $vue }}">
            <input type="hidden" name="categorie" value="{{ $filtres['categorie'] }}">
            <input type="hidden" name="statut" value="{{ $filtres['statut'] === 'actifs' ? '' : $filtres['statut'] }}">
            <input type="hidden" name="stock" value="{{ $filtres['stock'] }}">
            <input type="hidden" name="tri" value="{{ request('tri') }}">
            <input type="hidden" name="ordre" value="{{ request('ordre') }}">

            @php
                $classePuce = 'h-9 shrink-0 rounded-full border border-bordure-forte px-3.5 text-sm font-medium whitespace-nowrap text-texte-doux transition-colors duration-150 hover:text-texte aria-pressed:border-primaire aria-pressed:bg-primaire-doux aria-pressed:text-lien pointer-coarse:h-11';
            @endphp
            <div x-data="{ categorie: @js((string) $filtres['categorie']), statut: @js($filtres['statut'] === 'actifs' ? '' : $filtres['statut']), stock: @js((string) $filtres['stock']) }"
                 class="space-y-2">
                {{-- Puces de catégorie (défilement horizontal sur petit écran) --}}
                <div role="group" aria-label="Filtrer par catégorie" class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1 [scrollbar-width:thin]">
                    @foreach (['' => 'Toutes les catégories', ...$categoriesFiltre->pluck('nom', 'id')->all()] as $valeur => $libelle)
                        <button type="button" class="{{ $classePuce }}"
                                x-on:click="categorie = '{{ $valeur }}'; filtrer('categorie', categorie)"
                                aria-pressed="{{ (string) $filtres['categorie'] === (string) $valeur ? 'true' : 'false' }}"
                                x-bind:aria-pressed="(categorie === '{{ $valeur }}').toString()">{{ $libelle }}</button>
                    @endforeach
                </div>

                <div class="flex flex-wrap gap-2">
                    <div role="group" aria-label="Filtrer par statut" class="flex flex-wrap gap-2">
                        @foreach (['' => 'Actifs', 'inactifs' => 'Inactifs', 'tous' => 'Tous'] as $valeur => $libelle)
                            <button type="button" class="{{ $classePuce }}"
                                    x-on:click="statut = '{{ $valeur }}'; filtrer('statut', statut)"
                                    aria-pressed="{{ ($filtres['statut'] === 'actifs' ? '' : $filtres['statut']) === $valeur ? 'true' : 'false' }}"
                                    x-bind:aria-pressed="(statut === '{{ $valeur }}').toString()">{{ $libelle }}</button>
                        @endforeach
                    </div>
                    <span class="mx-1 hidden w-px bg-bordure sm:block" aria-hidden="true"></span>
                    <div role="group" aria-label="Filtrer par état du stock" class="flex flex-wrap gap-2">
                        @foreach (['' => ['Tout stock', null], 'normal' => ['Stock normal', 'bg-succes'], 'faible' => ['Stock faible', 'bg-alerte'], 'rupture' => ['Rupture', 'bg-danger']] as $valeur => [$libelle, $pastille])
                            <button type="button" class="{{ $classePuce }} inline-flex items-center gap-2"
                                    x-on:click="stock = '{{ $valeur }}'; filtrer('stock', stock)"
                                    aria-pressed="{{ (string) $filtres['stock'] === $valeur ? 'true' : 'false' }}"
                                    x-bind:aria-pressed="(stock === '{{ $valeur }}').toString()">
                                @if ($pastille)<span class="size-2 rounded-full {{ $pastille }}" aria-hidden="true"></span>@endif
                                {{ $libelle }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </form>

        <div x-ref="liste">
            @include('produits._liste')
        </div>

        <template x-ref="squelette">
            @if ($vue === 'grille')
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @for ($i = 0; $i < 8; $i++)
                        <x-squelette type="carte" libelle="Chargement des produits…" />
                    @endfor
                </div>
            @else
                <x-squelette type="ligne" :lignes="8" libelle="Chargement des produits…" />
            @endif
        </template>
    </div>
    </div>

    {{-- Panneau de saisie : seulement pour qui peut créer ou modifier --}}
    @if (auth()->user()->can('produits.creer') || auth()->user()->can('produits.modifier'))
        @include('produits._formulaire')
    @endif
    @include('produits._etiquettes')
@endsection
