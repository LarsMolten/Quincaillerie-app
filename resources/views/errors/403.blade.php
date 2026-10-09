{{-- Accès refusé (droit manquant), au design du thème --}}
@extends('layouts.base')

@section('titre', 'Accès refusé')

@section('contenu')
    <main class="flex min-h-dvh items-center justify-center px-4 py-10">
        <x-carte class="w-full max-w-md animate-apparition">
            <x-etat-vide icone="shield" titre="Accès refusé"
                         :texte="$exception->getMessage() ?: 'Vous n\'avez pas le droit d\'accéder à cette page.'">
                <x-bouton type="button" variante="secondaire" icone="chevron-left" x-data x-on:click="history.back()">Retour</x-bouton>
                @auth
                    <x-bouton :href="route('accueil')" icone="house">Accueil</x-bouton>
                @endauth
            </x-etat-vide>
            <p class="text-center text-xs text-texte-doux">Erreur 403 · Si vous pensez qu'il s'agit d'une erreur, contactez l'administrateur.</p>
        </x-carte>
    </main>
@endsection
