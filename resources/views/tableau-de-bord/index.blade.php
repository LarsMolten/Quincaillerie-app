{{--
    Tableau de bord (bento grid) : bandeau d'accueil, statistiques du jour avec tendance (rendues avec la page),
    puis cartes lourdes chargées à la demande avec squelette. Chaque élément n'apparaît qu'avec son droit.
--}}
@extends('layouts.app')

@section('titre', 'Tableau de bord')

@php
    $utilisateur = auth()->user();
    $classePuce = 'h-9 rounded-full border border-bordure-forte px-3.5 text-sm font-medium text-texte-doux transition-colors duration-150 hover:text-texte aria-pressed:border-primaire aria-pressed:bg-primaire-doux aria-pressed:text-lien pointer-coarse:h-11';
    $nouveaux = fn (int $n) => $n > 0 ? "+{$n} ce mois-ci" : 'aucun nouveau ce mois-ci';
@endphp

@section('page')
    {{-- Bandeau d'accueil --}}
    <section class="mb-6 flex flex-col gap-4 rounded-carte border border-bordure bg-surface p-carte shadow-doux sm:flex-row sm:items-center sm:justify-between"
             aria-labelledby="titre-accueil">
        <div>
            <h1 id="titre-accueil" class="text-2xl font-semibold tracking-tight">Bonjour, {{ $utilisateur->prenom }}</h1>
            <p class="text-texte-doux">{{ ucfirst(now()->translatedFormat('l j F Y')) }} · {{ $utilisateur->role->nom }}</p>
        </div>
        @droit('ventes.creer')
            <x-bouton :href="route('ventes.create')" icone="shopping-cart" class="sm:h-12 sm:px-6">Nouvelle vente</x-bouton>
        @enddroit
    </section>

    {{-- Statistiques du jour --}}
    <section aria-label="Chiffres du jour" class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @if ($voir['ventes'])
            <x-carte-stat libelle="Ventes du jour" :valeur="format_ar($jour['ventes']['valeur'])" icone="shopping-cart"
                          :tendance="$jour['ventes']['tendance']" :serie="$jour['serie']['ventes']">
                <span class="chiffres">{{ $jour['nombre_ventes'] }} vente(s), retours déduits</span>
            </x-carte-stat>
        @endif
        @if ($voir['finances'])
            <x-carte-stat libelle="Achats du jour" :valeur="format_ar($jour['achats']['valeur'])" icone="truck"
                          :tendance="$jour['achats']['tendance']" :serie="$jour['serie']['achats']" />
            <x-carte-stat libelle="Bénéfice du jour" :valeur="format_ar($jour['benefice']['valeur'])" icone="trending-up"
                          :tendance="$jour['benefice']['tendance']" :serie="$jour['serie']['benefice']">
                <span class="chiffres">Dépenses du jour : {{ format_ar($jour['depenses']) }}</span>
            </x-carte-stat>
        @endif
        @if ($voir['produits'])
            <x-carte-stat libelle="Produits actifs" :valeur="$compteurs['produits']['total']" icone="package">{{ $nouveaux($compteurs['produits']['nouveaux']) }}</x-carte-stat>
        @endif
        @if ($voir['clients'])
            <x-carte-stat libelle="Clients" :valeur="$compteurs['clients']['total']" icone="users">{{ $nouveaux($compteurs['clients']['nouveaux']) }}</x-carte-stat>
        @endif
        @if ($voir['fournisseurs'])
            <x-carte-stat libelle="Fournisseurs" :valeur="$compteurs['fournisseurs']['total']" icone="store">{{ $nouveaux($compteurs['fournisseurs']['nouveaux']) }}</x-carte-stat>
        @endif
    </section>

    {{-- Cartes chargées à la demande --}}
    <div class="grid gap-4 lg:grid-cols-3">
        @if ($voir['ventes'])
            <x-carte class="lg:col-span-2 lg:row-span-2" x-data="graphiqueVentes({ url: {{ \Illuminate\Support\Js::from(route('tableau-de-bord.carte', 'ventes')) }} })">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold">Ventes</h2>
                        <p class="text-sm text-texte-doux" x-show="donnees" x-cloak>
                            <span class="chiffres text-lg font-semibold text-texte" x-text="ar(donnees?.total)"></span>
                            <span x-show="donnees?.tendance !== null" x-bind:class="(donnees?.tendance ?? 0) >= 0 ? 'text-succes-texte' : 'text-danger-texte'"
                                  class="chiffres ml-1 font-medium" x-text="(donnees?.tendance > 0 ? '+' : '') + String(donnees?.tendance).replace('.', ',') + ' %'"></span>
                            <span x-text="'sur ' + jours + ' jours, par rapport aux ' + jours + ' précédents'"></span>
                        </p>
                    </div>
                    <div role="group" aria-label="Période du graphique" class="flex gap-2">
                        @foreach (\App\Rapports\TableauDeBord::PERIODES as $periode)
                            <button type="button" class="{{ $classePuce }}" x-on:click="charger({{ $periode }})" x-bind:aria-pressed="(jours === {{ $periode }}).toString()">{{ $periode }} j</button>
                        @endforeach
                    </div>
                </div>
                <div class="relative h-72">
                    <div x-show="chargement" class="absolute inset-0"><div class="h-full animate-squelette rounded-controle bg-neutre-doux" role="status" aria-label="Chargement du graphique…"></div></div>
                    <p x-show="erreur" x-cloak class="absolute inset-0 grid place-items-center text-sm text-danger-texte">
                        <span>Le graphique n'a pas pu être chargé. <button type="button" class="font-medium underline" x-on:click="charger(jours)">Réessayer</button></span>
                    </p>
                    <canvas x-ref="canvas" x-show="! chargement && ! erreur" role="img" aria-label="Courbe des ventes nettes sur la période choisie (valeurs dans le tableau suivant)"></canvas>
                </div>
                <table class="sr-only">
                    <caption>Ventes nettes par période</caption>
                    <template x-for="(valeur, i) in donnees?.valeurs ?? []" x-bind:key="i">
                        <tr><th scope="row" x-text="donnees.libelles[i]"></th><td x-text="ar(valeur)"></td></tr>
                    </template>
                </table>
            </x-carte>
        @endif

        @foreach ([
            'stock' => ['Stock faible', 'produits', 'Produits au minimum ou en dessous', route('produits.index', ['tri' => 'stock_actuel', 'ordre' => 'asc'])],
            'top' => ['Top 5 produits du mois', 'ventes', 'Chiffre d’affaires du mois en cours', null],
            'creances' => ['Créances en cours', 'creances', 'Sommes dues par les clients', route('creances.index')],
            'dernieres' => ['Dernières ventes', 'ventes', null, route('ventes.index')],
        ] as $carte => [$titre, $droit, $description, $lien])
            @continue(! $voir[$droit])
            <x-carte :titre="$titre" :description="$description" x-data="carteDifferee({ url: {{ \Illuminate\Support\Js::from(route('tableau-de-bord.carte', $carte)) }} })">
                @if ($lien)
                    <x-slot:actions><x-bouton variante="fantome" taille="sm" :href="$lien">Tout voir</x-bouton></x-slot:actions>
                @endif
                <div x-show="chargement"><x-squelette type="ligne" :lignes="4" :libelle="'Chargement : '.mb_strtolower($titre).'…'" /></div>
                <p x-show="erreur" x-cloak class="py-6 text-center text-sm text-danger-texte">
                    Chargement impossible. <button type="button" class="font-medium underline" x-on:click="charger()">Réessayer</button>
                </p>
                <div x-ref="contenu" x-show="! chargement && ! erreur" x-cloak></div>
            </x-carte>
        @endforeach
    </div>
@endsection
