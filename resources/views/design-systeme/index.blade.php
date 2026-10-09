{{-- Vitrine du design system (environnement local) : chaque composant en clair et en sombre --}}
@extends('layouts.base')

@section('titre', 'Design system')

@section('contenu')
    <div class="mx-auto max-w-[110rem] px-4 py-8 sm:px-page">
        <x-entete-page
            titre="Design system « moderne 2026 »"
            description="Tous les composants, côte à côte en mode clair et en mode sombre. Page visible uniquement en environnement local."
            :fil="['Accueil' => route('accueil'), 'Design system' => null]"
        >
            <x-slot:actions>
                {{-- Bascule de thème de la page entière (clair / sombre / auto) --}}
                <div x-data="theme" role="group" aria-label="Thème de l'application" class="inline-flex rounded-controle border border-bordure bg-surface p-1 shadow-doux">
                    @foreach (\App\Enums\PreferenceTheme::cases() as $mode)
                        <button type="button"
                                x-on:click="choisir('{{ $mode->value }}')"
                                x-bind:aria-pressed="(mode === '{{ $mode->value }}').toString()"
                                aria-pressed="false"
                                class="inline-flex h-9 items-center gap-1.5 rounded-lg px-3 text-sm font-medium text-texte-doux transition-colors duration-150 hover:text-texte aria-pressed:bg-primaire aria-pressed:text-primaire-texte pointer-coarse:h-11">
                            <x-icone :nom="$mode->icone()" taille="size-4" />
                            {{ $mode->libelle() }}
                        </button>
                    @endforeach
                </div>
            </x-slot:actions>
        </x-entete-page>

        <div class="grid gap-6 2xl:grid-cols-2">
            @foreach (['clair' => 'Mode clair', 'dark' => 'Mode sombre'] as $classe => $titreMode)
                <div class="{{ $classe }} rounded-carte bg-fond p-4 text-texte ring-1 ring-bordure sm:p-6">
                    <h2 class="mb-6 flex items-center gap-2 text-sm font-semibold uppercase tracking-wider text-texte-doux">
                        <x-icone :nom="$classe === 'dark' ? 'moon' : 'sun'" taille="size-4" />
                        {{ $titreMode }}
                    </h2>
                    @include('design-systeme._vitrine', ['p' => $classe])
                </div>
            @endforeach
        </div>
    </div>
@endsection
