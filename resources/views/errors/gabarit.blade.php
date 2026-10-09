{{--
    Gabarit commun des pages d'erreur (403, 404, 419, 500) au design du thème.
    Variables : $code, $icone, $titre, $message, et facultativement $actionRecharger (bouton « Recharger »).
    Volontairement autonome (layouts/base, sans la coquille) : il doit s'afficher même si la base est indisponible.
--}}
@extends('layouts.base')

@section('titre', $titre)

@section('contenu')
    <main class="flex min-h-dvh items-center justify-center px-4 py-10">
        <x-carte class="w-full max-w-md animate-apparition">
            <div class="flex flex-col items-center px-2 py-6 text-center">
                <span class="grid size-16 place-items-center rounded-full bg-primaire-doux text-lien">
                    <x-icone :nom="$icone" taille="size-8" />
                </span>
                <p class="chiffres mt-5 text-sm font-semibold tracking-widest text-texte-doux">ERREUR {{ $code }}</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-texte">{{ $titre }}</h1>
                <p class="mt-2 max-w-sm text-sm text-texte-doux">{{ $message }}</p>

                <div class="mt-8 flex flex-wrap justify-center gap-3">
                    @if (! empty($actionRecharger))
                        <x-bouton type="button" icone="refresh-cw" x-data x-on:click="location.reload()">Recharger la page</x-bouton>
                    @endif
                    <x-bouton type="button" variante="secondaire" icone="chevron-left" x-data x-on:click="history.length > 1 ? history.back() : (location.href = '{{ url('/') }}')">
                        Retour
                    </x-bouton>
                    @if (empty($actionRecharger))
                        <x-bouton :href="url('/')" icone="house">Accueil</x-bouton>
                    @endif
                </div>
            </div>
        </x-carte>
    </main>
@endsection
