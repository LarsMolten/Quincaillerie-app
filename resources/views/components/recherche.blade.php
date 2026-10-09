{{--
    Champ de recherche avec icône et raccourci clavier affiché (Ctrl K ou « / » pour y aller).

    Props :
    - nom (défaut "recherche"), valeur (défaut : la valeur courante de l'URL)
    - label (lu par les lecteurs d'écran), placeholder
    - raccourci : true (défaut) pour activer et afficher le raccourci

    Exemple : <form method="GET"><x-recherche placeholder="Nom, référence ou code-barres…" /></form>
--}}
@props([
    'nom' => 'recherche',
    'valeur' => null,
    'label' => 'Rechercher',
    'placeholder' => 'Rechercher…',
    'raccourci' => true,
    'id' => null,
])

@php
    $id ??= 'recherche-'.\Illuminate\Support\Str::slug($nom);
    $valeur ??= request($nom);
@endphp

<div @if ($raccourci) x-data="recherche" @endif {{ $attributes->only('class')->class('relative') }}>
    <label for="{{ $id }}" class="sr-only">{{ $label }}</label>
    <x-icone nom="search" taille="size-4" class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-texte-doux" />
    <input
        id="{{ $id }}"
        name="{{ $nom }}"
        type="search"
        value="{{ $valeur }}"
        placeholder="{{ $placeholder }}"
        autocomplete="off"
        @if ($raccourci) x-ref="champ" aria-keyshortcuts="Control+K /" @endif
        {{ $attributes->except('class')->class(['h-11 w-full rounded-controle border border-bordure-forte bg-surface pl-10 pr-4 text-sm text-texte placeholder:text-texte-doux transition-colors duration-150', 'sm:pr-20' => $raccourci]) }}
    >
    @if ($raccourci)
        <kbd class="pointer-events-none absolute right-3 top-1/2 hidden -translate-y-1/2 items-center gap-0.5 rounded-md border border-bordure bg-fond px-1.5 py-0.5 font-sans text-xs font-medium text-texte-doux sm:inline-flex">
            Ctrl K
        </kbd>
    @endif
</div>
