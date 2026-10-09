{{--
    Bouton (ou lien si "href" est fourni).

    Props :
    - variante   : primaire | secondaire | fantome | danger (défaut primaire)
    - taille     : sm | md | lg — md et lg font au moins 44 px ; sm passe à 44 px sur écran tactile
    - icone      : icône Lucide avant le texte ; iconeFin : après le texte
    - chargement : true pour afficher le spinner et bloquer le bouton (aussi piloté par x-chargement-envoi)
    - href       : rend un lien <a> stylé en bouton
    - type       : submit (défaut) | button | reset

    Un bouton sans texte (icône seule) doit recevoir un aria-label.
    Exemple : <x-bouton icone="plus" href="{{ route('accueil') }}">Nouveau produit</x-bouton>
--}}
@props([
    'variante' => 'primaire',
    'taille' => 'md',
    'icone' => null,
    'iconeFin' => null,
    'chargement' => false,
    'href' => null,
    'type' => 'submit',
])

@php
    $variantes = [
        'primaire' => 'bg-primaire text-primaire-texte shadow-doux hover:bg-primaire-survol',
        'secondaire' => 'border border-bordure-forte bg-surface text-texte hover:bg-fond',
        'fantome' => 'text-texte hover:bg-neutre-doux',
        'danger' => 'bg-danger text-sur-danger shadow-doux hover:bg-danger-survol',
    ];

    // Bouton icône seule (sans texte) : carré ; il doit alors recevoir un aria-label
    $iconeSeule = $slot->isEmpty();

    $tailles = $iconeSeule ? [
        'sm' => 'size-9 pointer-coarse:size-11',
        'md' => 'size-11',
        'lg' => 'size-12',
    ] : [
        'sm' => 'h-9 gap-1.5 px-3 text-sm pointer-coarse:h-11',
        'md' => 'h-11 gap-2 px-4 text-sm',
        'lg' => 'h-12 gap-2 px-5 text-base',
    ];

    $classes = [
        'group/bouton relative inline-flex select-none items-center justify-center rounded-controle font-medium whitespace-nowrap',
        'transition-colors duration-150 disabled:cursor-not-allowed disabled:opacity-60 aria-busy:cursor-wait',
        $variantes[$variante] ?? $variantes['primaire'],
        $tailles[$taille] ?? $tailles['md'],
    ];

    $tailleIcone = $taille === 'lg' ? 'size-5' : 'size-4';
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
@else
    <button
        type="{{ $type }}"
        {{ $attributes->class($classes)->merge(array_filter([
            'disabled' => $chargement ?: null,
            'aria-busy' => $chargement ? 'true' : null,
            'data-chargement' => $chargement ? 'true' : null,
        ])) }}
    >
@endif
    {{-- Spinner visible pendant le chargement (prop ou attribut data-chargement posé en JS) --}}
    <x-icone nom="loader-circle" :taille="$tailleIcone" class="hidden animate-spin group-data-[chargement=true]/bouton:inline" />
    @if ($icone)
        <x-icone :nom="$icone" :taille="$tailleIcone" class="group-data-[chargement=true]/bouton:hidden" />
    @endif
    {{ $slot }}
    @if ($iconeFin)
        <x-icone :nom="$iconeFin" :taille="$tailleIcone" />
    @endif
@if ($href)
    </a>
@else
    </button>
@endif
