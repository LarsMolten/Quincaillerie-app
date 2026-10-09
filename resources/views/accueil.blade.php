{{-- Tableau de bord provisoire : remplacé au prompt 20 --}}
@extends('layouts.app')

@section('titre', 'Tableau de bord')

@section('page')
    <x-entete-page
        :titre="'Bonjour, '.auth()->user()->prenom"
        :description="ucfirst(now()->translatedFormat('l j F Y'))"
    >
        <x-slot:actions>
            @droit('ventes.creer')
                <x-bouton :href="route('ventes.create')" icone="shopping-cart">Nouvelle vente</x-bouton>
            @enddroit
        </x-slot:actions>
    </x-entete-page>

    <x-carte>
        <x-etat-vide icone="layout-dashboard" titre="Tableau de bord bientôt disponible"
                     :texte="'Connecté en tant que '.auth()->user()->role->nom.'. Les indicateurs de ventes, de stock et de finances arrivent prochainement.'" />
        <p class="text-center text-sm text-texte-doux">Exemple de montant : <span class="chiffres font-medium text-texte">{{ format_ar(35000) }}</span></p>
    </x-carte>
@endsection
