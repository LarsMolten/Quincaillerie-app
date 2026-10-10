{{-- Administration > Utilisateurs : synthèse, recherche instantanée, filtres statut et rôle, tableau des comptes --}}
@extends('layouts.app')

@section('titre', 'Utilisateurs')

@section('page')
    <x-entete-page titre="Utilisateurs" description="Les comptes qui accèdent à l'application et leur rôle."
                   :fil="['Tableau de bord' => route('accueil'), 'Utilisateurs' => null]">
        <x-slot:actions>
            @droit('roles.gerer')
                <x-bouton :href="route('roles.index')" variante="secondaire" icone="shield">Rôles et droits</x-bouton>
            @enddroit
            <x-bouton type="button" icone="user-plus" x-data x-on:click="$dispatch('nouvel-utilisateur')">Nouveau compte</x-bouton>
        </x-slot:actions>
    </x-entete-page>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-carte-stat libelle="Comptes actifs" :valeur="$synthese['actifs']" icone="users" />
        <x-carte-stat libelle="Administrateurs actifs" :valeur="$synthese['administrateurs']" icone="shield-check" />
        <x-carte-stat libelle="Comptes désactivés" :valeur="$synthese['inactifs']" icone="user-x" />
    </div>

    <div x-data="listeDynamique">
        <form x-ref="filtres" method="GET" action="{{ route('utilisateurs.index') }}" role="search"
              class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <x-recherche id="recherche-utilisateurs" :valeur="$recherche" :raccourci="false" label="Rechercher un compte"
                             placeholder="Nom, email ou téléphone…" class="sm:w-80" />
                <label for="filtre-role" class="sr-only">Rôle</label>
                <select id="filtre-role" name="role" x-on:change="chargerDepuisFiltres()"
                        class="h-11 rounded-controle border border-bordure-forte bg-surface px-3 text-sm text-texte sm:w-52">
                    <option value="">Tous les rôles</option>
                    @foreach ($roles as $id => $nom)
                        <option value="{{ $id }}" @selected($roleId === $id)>{{ $nom }}</option>
                    @endforeach
                </select>
            </div>
            @include('partials.puces-statut', ['statut' => $statut, 'masculin' => true])
        </form>

        <div x-ref="liste">@include('utilisateurs._liste')</div>

        <template x-ref="squelette"><x-squelette type="ligne" :lignes="6" libelle="Chargement des comptes…" /></template>
    </div>

    @include('utilisateurs._formulaire')
    @include('utilisateurs._mot-de-passe')
@endsection
