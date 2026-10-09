{{--
    En-tête de page : fil d'Ariane, titre, description et boutons d'action.

    Props :
    - titre, description
    - fil : ['Tableau de bord' => route('accueil'), 'Produits' => null] (le dernier élément est la page courante)
    Slot nommé : actions

    Exemple :
    <x-entete-page titre="Produits" :fil="['Stock' => null, 'Produits' => null]">
        <x-slot:actions><x-bouton icone="plus">Nouveau produit</x-bouton></x-slot:actions>
    </x-entete-page>
--}}
@props(['titre', 'description' => null, 'fil' => []])

<header {{ $attributes->class('mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between') }}>
    <div class="min-w-0">
        @if ($fil)
            <nav aria-label="Fil d'Ariane" class="mb-2">
                <ol class="flex flex-wrap items-center gap-1.5 text-sm text-texte-doux">
                    @foreach ($fil as $libelle => $lien)
                        <li class="inline-flex items-center gap-1.5">
                            @if (! $loop->first)
                                <x-icone nom="chevron-right" taille="size-3.5" />
                            @endif
                            @if ($lien && ! $loop->last)
                                <a href="{{ $lien }}" class="rounded hover:text-texte hover:underline">{{ $libelle }}</a>
                            @else
                                <span @if ($loop->last) aria-current="page" class="font-medium text-texte" @endif>{{ $libelle }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif
        <h1 class="text-2xl font-semibold tracking-tight text-texte">{{ $titre }}</h1>
        @if ($description)
            <p class="mt-1 text-sm text-texte-doux">{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</header>
