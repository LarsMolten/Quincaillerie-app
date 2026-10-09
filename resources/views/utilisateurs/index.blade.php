{{-- Gestion des utilisateurs : page provisoire (implémentée au prompt 22) --}}
@extends('layouts.base')

@section('titre', 'Utilisateurs')

@section('contenu')
    <main class="mx-auto max-w-5xl px-4 py-8 sm:px-page">
        <x-entete-page titre="Utilisateurs" :fil="['Accueil' => route('accueil'), 'Administration' => null, 'Utilisateurs' => null]" />
        <x-carte>
            <x-etat-vide icone="users" titre="Bientôt disponible"
                         texte="La gestion des utilisateurs, des rôles et des droits arrive dans une prochaine version.">
                <x-bouton :href="route('accueil')" variante="secondaire" icone="house">Retour à l'accueil</x-bouton>
            </x-etat-vide>
        </x-carte>
    </main>
@endsection
