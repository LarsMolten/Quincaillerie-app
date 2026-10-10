{{--
    Administration > Paramètres : onglets (liens, un formulaire par onglet) Entreprise, Facturation, Ventes, Apparence.
    Chaque modification est enregistrée dans le journal d'activité.
--}}
@extends('layouts.app')

@section('titre', 'Paramètres')

@section('page')
    <x-entete-page titre="Paramètres" description="Réglages de l'entreprise, des documents, de la caisse et de l'apparence."
                   :fil="['Tableau de bord' => route('accueil'), 'Paramètres' => null]" />

    <div class="grid gap-6 lg:grid-cols-[15rem_minmax(0,1fr)]">
        {{-- Onglets : verticaux sur grand écran, défilants sur mobile --}}
        <nav aria-label="Rubriques des paramètres" class="-mx-4 overflow-x-auto px-4 lg:mx-0 lg:px-0">
            <ul class="flex gap-2 lg:flex-col" role="list">
                @foreach ($onglets as $code => [$libelle, $icone])
                    <li class="shrink-0">
                        <a href="{{ route('parametres.index', $code) }}" @if ($code === $onglet) aria-current="page" @endif
                           @class([
                               'flex h-11 items-center gap-3 rounded-controle px-3.5 text-sm font-medium transition-colors duration-150',
                               'bg-primaire-doux text-lien' => $code === $onglet,
                               'text-texte-doux hover:bg-neutre-doux hover:text-texte' => $code !== $onglet,
                           ])>
                            <x-icone :nom="$icone" taille="size-[1.125rem]" />
                            {{ $libelle }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <section aria-labelledby="titre-onglet">
            <x-carte>
                <header class="mb-6 border-b border-bordure pb-4">
                    <h2 id="titre-onglet" class="text-lg font-semibold text-texte">{{ $onglets[$onglet][0] }}</h2>
                    <p class="mt-0.5 text-sm text-texte-doux">{{ $onglets[$onglet][2] }}</p>
                </header>

                @include('parametres._'.$onglet)
            </x-carte>
        </section>
    </div>
@endsection
