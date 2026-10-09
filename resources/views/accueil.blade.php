{{-- Page provisoire : remplacée par le tableau de bord (prompt 20) --}}
@extends('layouts.base')

@section('contenu')
    <main class="mx-auto max-w-xl px-4 py-16">
        <x-carte>
            <x-etat-vide icone="store" :titre="config('app.name')" texte="Installation réussie. Le tableau de bord arrive bientôt.">
                @if (app()->environment('local'))
                    <x-bouton :href="route('design-systeme')" icone="layout-dashboard">Voir le design system</x-bouton>
                @endif
            </x-etat-vide>
            <p class="text-center text-sm text-texte-doux">Exemple de montant : <span class="chiffres font-medium text-texte">{{ format_ar(35000) }}</span></p>
        </x-carte>
    </main>
@endsection
