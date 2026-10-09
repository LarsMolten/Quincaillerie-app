{{-- Pagination simple (précédent / suivant) au design du thème --}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="flex justify-between gap-3">
        @if ($paginator->onFirstPage())
            <x-bouton variante="secondaire" taille="sm" icone="chevron-left" type="button" disabled>Précédent</x-bouton>
        @else
            <x-bouton variante="secondaire" taille="sm" icone="chevron-left" :href="$paginator->previousPageUrl()" rel="prev">Précédent</x-bouton>
        @endif

        @if ($paginator->hasMorePages())
            <x-bouton variante="secondaire" taille="sm" icone-fin="chevron-right" :href="$paginator->nextPageUrl()" rel="next">Suivant</x-bouton>
        @else
            <x-bouton variante="secondaire" taille="sm" icone-fin="chevron-right" type="button" disabled>Suivant</x-bouton>
        @endif
    </nav>
@endif
