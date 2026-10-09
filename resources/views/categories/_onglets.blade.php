{{-- Onglets communs aux pages Catégories et Unités --}}
<nav aria-label="Catégories et unités" class="-mt-2 mb-6 flex gap-1 overflow-x-auto border-b border-bordure [scrollbar-width:none]">
    @foreach ([
        ['categories.index', 'tags', 'Catégories'],
        ['unites.index', 'ruler', 'Unités'],
    ] as [$route, $icone, $libelle])
        @php($actif = request()->routeIs($route))
        <a href="{{ route($route) }}" @if ($actif) aria-current="page" @endif
           @class([
               '-mb-px inline-flex h-11 items-center gap-2 border-b-2 px-4 text-sm font-medium whitespace-nowrap transition-colors duration-150',
               'border-primaire text-texte' => $actif,
               'border-transparent text-texte-doux hover:text-texte' => ! $actif,
           ])>
            <x-icone :nom="$icone" taille="size-4" />
            {{ $libelle }}
        </a>
    @endforeach
</nav>
