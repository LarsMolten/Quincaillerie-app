{{-- Page provisoire : remplacée par le tableau de bord (prompt 20) et le layout complet (prompt 8) --}}
@extends('layouts.base')

@section('contenu')
    <main class="mx-auto max-w-xl px-4 py-16">
        <x-carte>
            <x-etat-vide icone="store" :titre="'Bonjour, '.auth()->user()->nom"
                         :texte="'Connecté en tant que '.auth()->user()->role->nom.'. Le tableau de bord arrive bientôt.'">
                @droit('utilisateurs.gerer')
                    <x-bouton :href="route('utilisateurs.index')" variante="secondaire" icone="users">Utilisateurs</x-bouton>
                @enddroit
                @if (app()->environment('local'))
                    <x-bouton :href="route('design-systeme')" variante="secondaire" icone="layout-dashboard">Design system</x-bouton>
                @endif
                <form method="POST" action="{{ route('deconnexion') }}">
                    @csrf
                    <x-bouton variante="fantome" icone="log-out">Se déconnecter</x-bouton>
                </form>
            </x-etat-vide>
            <p class="text-center text-sm text-texte-doux">Exemple de montant : <span class="chiffres font-medium text-texte">{{ format_ar(35000) }}</span></p>
        </x-carte>
    </main>
@endsection
