{{--
    Coquille de l'application (utilisateur connecté) : barre latérale repliable, tiroir mobile,
    en-tête collant en verre, contenu et barre de navigation inférieure (mobile).
    Utilisation : @extends('layouts.app'), @section('titre', '…') et @section('page') … @endsection
--}}
@extends('layouts.base')

@php
    use App\Support\Navigation;

    $utilisateur = auth()->user();
    $sections = Navigation::pour($utilisateur);
    $navigationMobile = Navigation::mobile($utilisateur);
@endphp

@section('contenu')
    <a href="#contenu"
       class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-controle focus:bg-surface focus:px-4 focus:py-2.5 focus:text-sm focus:font-medium focus:shadow-eleve">
        Aller au contenu
    </a>

    <div x-data="coquille">
        {{-- Barre latérale (grand écran) --}}
        <aside id="barre-laterale"
               class="transition-barre fixed inset-y-0 left-0 z-30 hidden w-64 flex-col border-r border-bordure bg-surface transition-[width] duration-200 lg:flex lg:barre-repliee:w-18">
            <a href="{{ route('accueil') }}" class="flex h-16 shrink-0 items-center gap-3 px-5 lg:barre-repliee:justify-center lg:barre-repliee:px-0">
                <span class="grid size-9 shrink-0 place-items-center rounded-controle bg-primaire text-primaire-texte shadow-doux">
                    <x-icone nom="hammer" taille="size-5" />
                </span>
                <span class="truncate text-base font-semibold tracking-tight text-texte lg:barre-repliee:sr-only">{{ config('app.name') }}</span>
            </a>

            <nav aria-label="Navigation principale" class="flex-1 overflow-y-auto px-3 pb-4 pt-2 [scrollbar-color:var(--bordure)_transparent] [scrollbar-width:thin]">
                @include('layouts.partials.menu-lateral', ['sections' => $sections, 'tiroir' => false])
            </nav>

            <div class="border-t border-bordure p-3">
                <button type="button" x-on:click="basculerBarre()"
                        x-bind:aria-expanded="(! replie).toString()" aria-controls="barre-laterale"
                        class="flex h-10 w-full items-center gap-3 rounded-controle px-3 text-sm text-texte-doux transition-colors duration-150 hover:bg-neutre-doux hover:text-texte lg:barre-repliee:justify-center lg:barre-repliee:px-0">
                    <x-icone nom="panel-left" taille="size-[1.125rem]" />
                    <span class="lg:barre-repliee:sr-only" x-text="replie ? 'Déplier le menu' : 'Réduire le menu'">Réduire le menu</span>
                </button>
            </div>
        </aside>

        {{-- Tiroir de navigation (mobile et tablette) --}}
        <dialog id="tiroir-navigation" x-data="modal('tiroir-navigation')" x-on:click="clicFond($event)"
                aria-label="Menu de navigation"
                class="m-0 h-dvh max-h-dvh w-72 max-w-[85vw] border-r border-bordure bg-surface p-0 text-texte shadow-eleve open:animate-tiroir lg:hidden">
            <div class="flex h-16 items-center justify-between gap-3 border-b border-bordure px-4">
                <span class="flex items-center gap-3">
                    <span class="grid size-9 place-items-center rounded-controle bg-primaire text-primaire-texte">
                        <x-icone nom="hammer" taille="size-5" />
                    </span>
                    <span class="font-semibold tracking-tight">{{ config('app.name') }}</span>
                </span>
                <button type="button" x-on:click="fermer()" aria-label="Fermer le menu"
                        class="grid size-11 place-items-center rounded-controle text-texte-doux hover:bg-neutre-doux hover:text-texte">
                    <x-icone nom="x" />
                </button>
            </div>
            <nav aria-label="Navigation principale (mobile)" class="overflow-y-auto px-3 py-4">
                @include('layouts.partials.menu-lateral', ['sections' => $sections, 'tiroir' => true])
            </nav>
        </dialog>

        <div class="flex min-h-dvh flex-col transition-[padding] duration-200 lg:pl-64 lg:barre-repliee:pl-18">
            {{-- En-tête collant en verre dépoli --}}
            <header class="transition-entete verre sticky top-0 z-20 flex h-16 items-center gap-2 border-b border-bordure px-4 sm:gap-3 sm:px-page">
                <button type="button" x-on:click="basculerMenu()" aria-label="Menu"
                        x-bind:aria-expanded="menuDeplie.toString()" x-bind:aria-controls="estGrand ? 'barre-laterale' : 'tiroir-navigation'"
                        class="grid size-11 shrink-0 place-items-center rounded-controle text-texte-doux transition-colors duration-150 hover:bg-neutre-doux hover:text-texte">
                    <x-icone nom="menu" />
                </button>

                <a href="{{ route('accueil') }}" class="flex items-center gap-2 lg:hidden" aria-label="{{ config('app.name') }} — tableau de bord">
                    <span class="grid size-8 place-items-center rounded-lg bg-primaire text-primaire-texte">
                        <x-icone nom="hammer" taille="size-4" />
                    </span>
                </a>

                @can('produits.voir')
                    <form method="GET" action="{{ route('produits.index') }}" role="search" class="hidden max-w-md flex-1 sm:block">
                        <x-recherche label="Rechercher un produit" placeholder="Rechercher un produit, une référence…" />
                    </form>
                @endcan

                <div class="ml-auto flex items-center gap-1 sm:gap-2">
                    {{-- Notifications (alertes branchées au prompt 23) --}}
                    <x-menu-actions libelle="Notifications" largeur="w-72"
                                    classe-bouton="grid size-11 place-items-center rounded-controle text-texte-doux hover:bg-neutre-doux hover:text-texte">
                        <x-slot:declencheur><x-icone nom="bell" /></x-slot:declencheur>
                        <x-slot:entete>
                            <p class="text-sm font-semibold text-texte">Notifications</p>
                        </x-slot:entete>
                        <div class="flex flex-col items-center gap-2 px-3 py-6 text-center">
                            <x-icone nom="bell" taille="size-6" class="text-texte-doux" />
                            <p class="text-sm text-texte-doux">Aucune notification pour le moment.</p>
                        </div>
                    </x-menu-actions>

                    {{-- Bascule de thème : clair → sombre → auto --}}
                    <button type="button" x-data="theme" x-on:click="suivant()"
                            x-bind:aria-label="libelle + ' (cliquer pour changer)'" aria-label="Changer de thème"
                            x-bind:title="libelle"
                            class="grid size-11 place-items-center rounded-controle text-texte-doux transition-colors duration-150 hover:bg-neutre-doux hover:text-texte">
                        <span x-show="mode === 'clair'" x-cloak><x-icone nom="sun" /></span>
                        <span x-show="mode === 'sombre'" x-cloak><x-icone nom="moon" /></span>
                        <span x-show="mode === 'auto'"><x-icone nom="monitor" /></span>
                    </button>

                    {{-- Menu utilisateur --}}
                    <x-menu-actions :libelle="'Menu utilisateur : '.$utilisateur->nom" largeur="w-64"
                                    classe-bouton="flex items-center gap-2.5 rounded-controle p-1 hover:bg-neutre-doux md:pr-3">
                        <x-slot:declencheur>
                            <span class="grid size-9 shrink-0 place-items-center rounded-full bg-primaire-doux text-sm font-semibold text-lien" aria-hidden="true">
                                {{ $utilisateur->initiales }}
                            </span>
                            <span class="hidden text-left leading-tight md:block">
                                <span class="block max-w-40 truncate text-sm font-medium text-texte">{{ $utilisateur->nom }}</span>
                                <span class="block text-xs text-texte-doux">{{ $utilisateur->role->nom }}</span>
                            </span>
                        </x-slot:declencheur>
                        <x-slot:entete>
                            <p class="truncate text-sm font-semibold text-texte">{{ $utilisateur->nom }}</p>
                            <p class="truncate text-xs text-texte-doux">{{ $utilisateur->email }}</p>
                            <p class="mt-1.5"><x-badge couleur="info" :point="false">{{ $utilisateur->role->nom }}</x-badge></p>
                        </x-slot:entete>
                        <x-menu-actions.element icone="log-out" danger
                                                x-on:click="document.getElementById('formulaire-deconnexion').requestSubmit()">
                            Déconnexion
                        </x-menu-actions.element>
                    </x-menu-actions>
                    <form id="formulaire-deconnexion" method="POST" action="{{ route('deconnexion') }}" class="hidden">
                        @csrf
                    </form>
                </div>
            </header>

            <main id="contenu" tabindex="-1" class="mx-auto w-full max-w-7xl flex-1 px-4 pb-28 pt-6 focus:outline-none sm:px-page lg:pb-10">
                @yield('page')
            </main>
        </div>

        {{-- Navigation inférieure (mobile) --}}
        <nav aria-label="Navigation rapide"
             class="verre fixed inset-x-0 bottom-0 z-20 border-t border-bordure pb-[env(safe-area-inset-bottom)] lg:hidden">
            <ul class="flex" role="list">
                @foreach ($navigationMobile as $action)
                    <li class="flex-1">
                        <a href="{{ route($action['route']) }}" @if ($action['active']) aria-current="page" @endif
                           @class([
                               'flex h-16 flex-col items-center justify-center gap-1 text-xs font-medium transition-colors duration-150',
                               'text-lien' => $action['active'],
                               'text-texte-doux hover:text-texte' => ! $action['active'],
                           ])>
                            <x-icone :nom="$action['icone']" />
                            {{ $action['libelle'] }}
                        </a>
                    </li>
                @endforeach
                <li class="flex-1">
                    <button type="button" x-on:click="ouvrirTiroir()" aria-controls="tiroir-navigation"
                            x-bind:aria-expanded="tiroirOuvert.toString()" aria-expanded="false"
                            class="flex h-16 w-full flex-col items-center justify-center gap-1 text-xs font-medium text-texte-doux transition-colors duration-150 hover:text-texte">
                        <x-icone nom="menu" />
                        Menu
                    </button>
                </li>
            </ul>
        </nav>
    </div>
@endsection
