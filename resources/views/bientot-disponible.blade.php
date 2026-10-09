{{-- Page générique des modules pas encore implémentés (routes déclarées depuis App\Support\Navigation) --}}
@extends('layouts.app')

@php($entree = \App\Support\Navigation::courante())

@section('titre', $entree['libelle'] ?? 'Bientôt disponible')

@section('page')
    <x-entete-page
        :titre="$entree['libelle'] ?? 'Bientôt disponible'"
        :fil="array_filter(['Tableau de bord' => route('accueil'), ($entree['section'] ?? '') => null, ($entree['libelle'] ?? '') => null], fn ($cle) => $cle !== '', ARRAY_FILTER_USE_KEY)"
    />

    <x-carte>
        <x-etat-vide :icone="$entree['icone'] ?? 'wrench'" titre="Bientôt disponible"
                     texte="Ce module est en cours de réalisation. Il sera disponible dans une prochaine version de l'application.">
            <x-bouton :href="route('accueil')" variante="secondaire" icone="layout-dashboard">Retour au tableau de bord</x-bouton>
        </x-etat-vide>
    </x-carte>
@endsection
