{{--
    Menu de navigation (barre latérale et tiroir mobile).
    $sections : App\Support\Navigation::pour($utilisateur) ; $tiroir : true dans le tiroir mobile.
    En barre repliée (grand écran), les libellés passent en sr-only (le nom accessible reste)
    et les titres de section deviennent de simples séparateurs.
--}}
<ul class="space-y-0.5" role="list">
    @foreach ($sections as $section)
        @if ($section['titre'])
            <li class="pt-4 first:pt-0" role="presentation">
                <p @class([
                    'px-3 pb-1.5 text-[0.6875rem] font-semibold uppercase tracking-wider text-texte-doux',
                    'lg:barre-repliee:sr-only' => ! $tiroir,
                ])>{{ $section['titre'] }}</p>
                @unless ($tiroir)
                    <hr class="mx-3 mb-2 hidden border-bordure lg:barre-repliee:block" aria-hidden="true">
                @endunless
            </li>
        @endif

        @foreach ($section['entrees'] as $entree)
            <li>
                <a
                    href="{{ route($entree['route']) }}"
                    title="{{ $entree['libelle'] }}"
                    @if ($entree['active']) aria-current="page" @endif
                    @class([
                        'relative flex h-10 items-center gap-3 rounded-controle px-3 text-sm font-medium transition-colors duration-150 pointer-coarse:h-11',
                        'lg:barre-repliee:justify-center lg:barre-repliee:px-0' => ! $tiroir,
                        'bg-primaire-doux text-lien' => $entree['active'],
                        'text-texte-doux hover:bg-neutre-doux hover:text-texte' => ! $entree['active'],
                    ])
                >
                    @if ($entree['active'])
                        <span class="absolute inset-y-2 left-0 w-1 rounded-r-full bg-primaire" aria-hidden="true"></span>
                    @endif
                    <x-icone :nom="$entree['icone']" taille="size-[1.125rem]" />
                    <span @class(['truncate', 'lg:barre-repliee:sr-only' => ! $tiroir])>{{ $entree['libelle'] }}</span>
                </a>
            </li>
        @endforeach
    @endforeach
</ul>
