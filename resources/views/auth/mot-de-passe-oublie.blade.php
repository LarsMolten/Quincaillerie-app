{{-- Mot de passe oublié : la réinitialisation est faite par un administrateur (pas d'envoi d'e-mail) --}}
@extends('layouts.base')

@section('titre', 'Mot de passe oublié')

@section('contenu')
    <main class="flex min-h-dvh items-center justify-center px-4 py-10">
        <x-carte class="w-full max-w-md animate-apparition">
            <x-etat-vide icone="key-round" titre="Mot de passe oublié ?"
                         texte="Pour des raisons de sécurité, seul un administrateur peut réinitialiser votre mot de passe. Contactez-le : il vous communiquera un nouveau mot de passe provisoire.">
                <x-bouton :href="route('connexion')" variante="secondaire" icone="chevron-left">Retour à la connexion</x-bouton>
            </x-etat-vide>
        </x-carte>
    </main>
@endsection
