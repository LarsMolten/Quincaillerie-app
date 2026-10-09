{{-- Connexion : panneau de marque à gauche (grand écran), formulaire à droite --}}
@extends('layouts.base')

@section('titre', 'Connexion')

@section('contenu')
    <main class="grid min-h-dvh lg:grid-cols-2">
        {{-- Panneau de marque (masqué sur mobile et tablette portrait) --}}
        <section class="relative hidden overflow-hidden border-r border-bordure bg-linear-to-br from-primaire-doux via-fond to-fond lg:flex lg:flex-col lg:justify-between lg:p-12">
            {{-- Halo orange discret --}}
            <div class="pointer-events-none absolute -left-32 -top-32 size-[28rem] rounded-full bg-primaire opacity-15 blur-3xl" aria-hidden="true"></div>

            {{-- Motif géométrique léger : écrous hexagonaux --}}
            <svg class="pointer-events-none absolute inset-0 size-full text-primaire opacity-[0.07]" aria-hidden="true" focusable="false">
                <defs>
                    <pattern id="motif-ecrous" width="56" height="48" patternUnits="userSpaceOnUse">
                        <polygon points="14,2 42,2 56,24 42,46 14,46 0,24" fill="none" stroke="currentColor" stroke-width="1.5" />
                        <circle cx="28" cy="24" r="8" fill="none" stroke="currentColor" stroke-width="1.5" />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#motif-ecrous)" />
            </svg>

            <div class="relative flex items-center gap-3">
                <span class="grid size-11 place-items-center rounded-controle bg-primaire text-primaire-texte shadow-doux">
                    <x-icone nom="hammer" taille="size-6" />
                </span>
                <span class="text-xl font-semibold tracking-tight text-texte">{{ config('app.name') }}</span>
            </div>

            <div class="relative max-w-md space-y-8">
                <p class="text-3xl font-semibold leading-tight tracking-tight text-texte">
                    Votre quincaillerie, sous contrôle : stock, caisse et finances en un coup d'œil.
                </p>
                <ul class="space-y-4 text-texte-doux">
                    @foreach ([
                        ['shopping-cart', 'Caisse rapide, au clavier comme sur tablette'],
                        ['package', 'Stock suivi mouvement par mouvement, alertes de rupture'],
                        ['chart-column', 'Ventes, marges et créances toujours à jour'],
                    ] as [$icone, $atout])
                        <li class="flex items-center gap-3">
                            <span class="grid size-9 shrink-0 place-items-center rounded-controle bg-surface text-lien shadow-doux">
                                <x-icone :nom="$icone" taille="size-[1.125rem]" />
                            </span>
                            {{ $atout }}
                        </li>
                    @endforeach
                </ul>
            </div>

            <p class="relative text-sm text-texte-doux">Montants en Ariary · Antananarivo</p>
        </section>

        {{-- Formulaire --}}
        <section class="flex items-center justify-center px-4 py-10 sm:px-8">
            <div class="w-full max-w-sm">
                {{-- Logo seul sur petit écran --}}
                <div class="mb-8 flex items-center justify-center gap-3 lg:hidden">
                    <span class="grid size-11 place-items-center rounded-controle bg-primaire text-primaire-texte shadow-doux">
                        <x-icone nom="hammer" taille="size-6" />
                    </span>
                    <span class="text-xl font-semibold tracking-tight">{{ config('app.name') }}</span>
                </div>

                <x-carte class="animate-apparition">
                    <div class="mb-6">
                        <h1 class="text-2xl font-semibold tracking-tight">Connexion</h1>
                        <p class="mt-1 text-sm text-texte-doux">Identifiez-vous pour accéder à l'application.</p>
                    </div>

                    <form method="POST" action="{{ route('connexion.valider') }}" class="space-y-5" x-chargement-envoi novalidate>
                        @csrf

                        <x-champ nom="email" type="email" label="Adresse email" icone="mail" autocomplete="username"
                                 inputmode="email" placeholder="vous@exemple.mg" required autofocus />

                        <div x-data="{ visible: false }">
                            <x-champ nom="password" type="password" label="Mot de passe" icone="lock" id="champ-mot-de-passe"
                                     autocomplete="current-password" required x-bind:type="visible ? 'text' : 'password'">
                                <x-slot:action>
                                    <button type="button" x-on:click="visible = ! visible"
                                            x-bind:aria-pressed="visible.toString()" aria-pressed="false"
                                            x-bind:aria-label="visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
                                            aria-label="Afficher le mot de passe" aria-controls="champ-mot-de-passe"
                                            class="grid w-11 shrink-0 place-items-center text-texte-doux transition-colors duration-150 hover:text-texte focus-visible:-outline-offset-2">
                                        <span x-show="! visible"><x-icone nom="eye" taille="size-[1.125rem]" /></span>
                                        <span x-show="visible" x-cloak><x-icone nom="eye-off" taille="size-[1.125rem]" /></span>
                                    </button>
                                </x-slot:action>
                            </x-champ>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <x-case nom="souvenir" label="Se souvenir de moi" />
                            <a href="{{ route('mot-de-passe.oublie') }}"
                               class="inline-flex min-h-11 items-center rounded text-sm font-medium text-lien underline-offset-4 hover:underline">
                                Mot de passe oublié ?
                            </a>
                        </div>

                        <x-bouton icone="log-in" class="w-full">Se connecter</x-bouton>
                    </form>
                </x-carte>
            </div>
        </section>
    </main>
@endsection
