{{--
    Cellule de x-tableau.

    Props :
    - libelle    : nom de la colonne, affiché devant la valeur sur mobile
    - alignement : gauche (défaut) | droite (montants et quantités, en chiffres tabulaires)
    - principale : valeur mise en avant comme titre de la carte sur mobile (sans libellé)
--}}
@props(['libelle' => null, 'alignement' => 'gauche', 'principale' => false])

<td data-libelle="{{ $libelle }}" {{ $attributes->class([
    'border-b border-bordure px-4 py-3 align-middle text-texte group-last/ligne:border-b-0',
    'chiffres text-right' => $alignement === 'droite',
    'max-md:flex max-md:items-center max-md:justify-between max-md:gap-4 max-md:border-0 max-md:px-0 max-md:py-1.5',
    'max-md:before:shrink-0 max-md:before:text-left max-md:before:font-medium max-md:before:text-texte-doux max-md:before:content-[attr(data-libelle)]' => ! $principale,
    'max-md:pb-2 max-md:text-base max-md:font-semibold' => $principale,
]) }}>
    {{ $slot }}
</td>
