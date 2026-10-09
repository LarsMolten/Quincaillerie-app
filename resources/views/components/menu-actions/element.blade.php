{{--
    Élément d'un x-menu-actions : lien (href) ou bouton.

    Props : href, icone, danger (texte rouge pour les actions destructrices)
    Le menu se ferme (focus rendu au bouton « ⋯ », en phase de capture) avant l'action de l'appelant :
    son propre x-on:click reste utilisable, et une modale ouverte ensuite
    rendra le focus au bon endroit.
--}}
@props(['href' => null, 'icone' => null, 'danger' => false])

@php
    $classes = [
        'flex min-h-10 w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm transition-colors duration-100',
        'hover:bg-neutre-doux focus:bg-neutre-doux focus:outline-none pointer-coarse:min-h-11',
        'text-danger-texte' => $danger,
        'text-texte' => ! $danger,
    ];
@endphp

@if ($href)
    <a href="{{ $href }}" role="menuitem" tabindex="-1" x-on:click.capture="fermer()" {{ $attributes->class($classes) }}>
@else
    <button type="button" role="menuitem" tabindex="-1" x-on:click.capture="fermer()" {{ $attributes->class($classes) }}>
@endif
    @if ($icone)
        <x-icone :nom="$icone" taille="size-4" @class(['text-texte-doux' => ! $danger]) />
    @endif
    {{ $slot }}
@if ($href)
    </a>
@else
    </button>
@endif
