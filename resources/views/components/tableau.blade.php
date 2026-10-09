{{--
    Tableau de données : en-tête collant, survol de ligne, tri par colonne, pagination.
    Sous 768 px, chaque ligne devient une carte (libellés tirés de x-tableau.cellule).

    Props :
    - colonnes   : [clé => "Libellé"] ou [clé => ['libelle' => …, 'triable' => true, 'alignement' => 'droite', 'masque' => true]]
                   ("masque" : libellé lu par les lecteurs d'écran seulement, ex. colonne d'actions)
    - pagination : paginateur Laravel (liens affichés sous le tableau)
    - legende    : description du tableau pour les lecteurs d'écran
    Le tri est transmis dans l'URL (?tri=cle&ordre=asc|desc), à appliquer côté contrôleur.

    Exemple :
    <x-tableau :colonnes="['nom' => ['libelle' => 'Produit', 'triable' => true], 'actions' => ['libelle' => 'Actions', 'masque' => true]]" :pagination="$produits">
        @foreach ($produits as $produit)
            <x-tableau.ligne>
                <x-tableau.cellule libelle="Produit" principale>{{ $produit->nom }}</x-tableau.cellule>
                <x-tableau.cellule libelle="Actions"><x-menu-actions>…</x-menu-actions></x-tableau.cellule>
            </x-tableau.ligne>
        @endforeach
    </x-tableau>
--}}
@props(['colonnes' => [], 'pagination' => null, 'legende' => null])

@php
    $triActuel = request('tri');
    $ordreActuel = request('ordre') === 'desc' ? 'desc' : 'asc';
@endphp

<div {{ $attributes->class('rounded-carte border border-bordure bg-surface shadow-doux max-md:border-0 max-md:bg-transparent max-md:shadow-none') }}>
    <div class="max-h-[70vh] overflow-auto rounded-carte max-md:max-h-none max-md:overflow-visible">
        <table class="w-full border-separate border-spacing-0 text-sm max-md:block">
            @if ($legende)
                <caption class="sr-only">{{ $legende }}</caption>
            @endif
            <thead class="max-md:sr-only">
                <tr>
                    @foreach ($colonnes as $cle => $colonne)
                        @php
                            $colonne = is_array($colonne) ? $colonne : ['libelle' => $colonne];
                            $triee = $triActuel === $cle;
                            $prochainOrdre = $triee && $ordreActuel === 'asc' ? 'desc' : 'asc';
                        @endphp
                        <th
                            scope="col"
                            @if (! empty($colonne['triable'])) aria-sort="{{ $triee ? ($ordreActuel === 'asc' ? 'ascending' : 'descending') : 'none' }}" @endif
                            @class([
                                'sticky top-0 z-10 border-b border-bordure bg-surface-elevee px-4 py-3 font-medium text-texte-doux whitespace-nowrap',
                                'text-right' => ($colonne['alignement'] ?? null) === 'droite',
                                'text-left' => ($colonne['alignement'] ?? null) !== 'droite',
                            ])
                        >
                            @if (! empty($colonne['masque']))
                                <span class="sr-only">{{ $colonne['libelle'] }}</span>
                            @elseif (! empty($colonne['triable']))
                                <a href="{{ request()->fullUrlWithQuery(['tri' => $cle, 'ordre' => $prochainOrdre, 'page' => null]) }}"
                                   class="inline-flex items-center gap-1 rounded hover:text-texte {{ $triee ? 'text-texte' : '' }}">
                                    {{ $colonne['libelle'] }}
                                    <x-icone :nom="$triee ? ($ordreActuel === 'asc' ? 'arrow-up' : 'arrow-down') : 'chevrons-up-down'" taille="size-3.5" />
                                </a>
                            @else
                                {{ $colonne['libelle'] }}
                            @endif
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="max-md:block max-md:space-y-3">
                {{ $slot }}
            </tbody>
        </table>
    </div>

    @if ($pagination && $pagination->hasPages())
        <div class="border-t border-bordure px-4 py-3 max-md:mt-3 max-md:rounded-carte max-md:border max-md:bg-surface">
            {{ $pagination->links() }}
        </div>
    @endif
</div>
