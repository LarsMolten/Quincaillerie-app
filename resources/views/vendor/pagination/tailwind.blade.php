{{-- Pagination au design du thème (vue par défaut, voir AppServiceProvider) --}}
@if ($paginator->hasPages())
    @php
        $classeLien = 'inline-grid min-w-10 h-10 place-items-center rounded-controle px-3 text-sm font-medium transition-colors duration-150 pointer-coarse:h-11 pointer-coarse:min-w-11';
    @endphp
    <nav role="navigation" aria-label="Pagination" class="flex flex-col items-center justify-between gap-3 sm:flex-row">
        <p class="text-sm text-texte-doux">
            @if ($paginator->firstItem())
                Affichage de <span class="chiffres font-medium text-texte">{{ $paginator->firstItem() }}</span>
                à <span class="chiffres font-medium text-texte">{{ $paginator->lastItem() }}</span>
                sur <span class="chiffres font-medium text-texte">{{ $paginator->total() }}</span> résultats
            @else
                {{ $paginator->count() }} résultat(s)
            @endif
        </p>

        <ul class="flex flex-wrap items-center gap-1">
            {{-- Précédent --}}
            <li>
                @if ($paginator->onFirstPage())
                    <span class="{{ $classeLien }} cursor-not-allowed text-texte-doux opacity-50" aria-disabled="true">
                        <x-icone nom="chevron-left" taille="size-4" titre="Page précédente" />
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $classeLien }} text-texte hover:bg-neutre-doux">
                        <x-icone nom="chevron-left" taille="size-4" titre="Page précédente" />
                    </a>
                @endif
            </li>

            {{-- Pages (masquées sur mobile, sauf la page courante) --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="hidden sm:block"><span class="{{ $classeLien }} text-texte-doux" aria-hidden="true">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li>
                                <span aria-current="page" class="{{ $classeLien }} chiffres bg-primaire text-primaire-texte">
                                    <span class="sr-only">Page</span> {{ $page }}
                                </span>
                            </li>
                        @else
                            <li class="hidden sm:block">
                                <a href="{{ $url }}" class="{{ $classeLien }} chiffres text-texte hover:bg-neutre-doux">
                                    <span class="sr-only">Page</span> {{ $page }}
                                </a>
                            </li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Suivant --}}
            <li>
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $classeLien }} text-texte hover:bg-neutre-doux">
                        <x-icone nom="chevron-right" taille="size-4" titre="Page suivante" />
                    </a>
                @else
                    <span class="{{ $classeLien }} cursor-not-allowed text-texte-doux opacity-50" aria-disabled="true">
                        <x-icone nom="chevron-right" taille="size-4" titre="Page suivante" />
                    </span>
                @endif
            </li>
        </ul>
    </nav>
@endif
