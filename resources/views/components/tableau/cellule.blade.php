{{--
    Cellule de x-tableau.

    Props :
    - libelle    : nom de la colonne, affiché devant la valeur sur mobile
    - alignement : gauche (défaut) | droite (montants et quantités, en chiffres tabulaires)
    - principale : valeur mise en avant comme titre de la carte sur mobile (sans libellé), toujours placée
                   en tête de carte, son contenu empilé (titre puis sous-texte) quelle que soit la colonne
--}}
@props(['libelle' => null, 'alignement' => 'gauche', 'principale' => false])

<td data-libelle="{{ $libelle }}" {{ $attributes->class([
    'border-b border-bordure px-4 py-3 align-middle text-texte group-last/ligne:border-b-0',
    'chiffres text-right' => $alignement === 'droite',
    'max-md:flex max-md:border-0 max-md:px-0',
    'max-md:items-center max-md:justify-between max-md:gap-4 max-md:py-1.5' => ! $principale,
    'max-md:before:shrink-0 max-md:before:text-left max-md:before:font-medium max-md:before:text-texte-doux max-md:before:content-[attr(data-libelle)]' => ! $principale,
    'max-md:order-first max-md:flex-col max-md:items-start max-md:gap-0.5 max-md:pt-0 max-md:pb-2 max-md:text-left max-md:text-base max-md:font-semibold' => $principale,
]) }}>
    {{-- Contenu groupé : sur mobile, plusieurs éléments (ex. « 8 → 10 », lien + motif) restent ensemble à droite
         du libellé au lieu d'être répartis sur la largeur ; « contents » sur ordinateur : aucun effet sur le tableau --}}
    <div @class(['contents', 'max-md:block max-md:min-w-0 max-md:text-right' => ! $principale])>{{ $slot }}</div>
</td>
