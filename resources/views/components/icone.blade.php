{{--
    Icône Lucide en SVG en ligne (fichiers générés dans resources/icones par « npm run icones »).

    Props :
    - nom    : nom Lucide en kebab-case (ex. "shopping-cart")
    - taille : classes de taille (défaut "size-5")
    - titre  : texte annoncé aux lecteurs d'écran ; sans titre, l'icône est décorative

    Exemple : <x-icone nom="package" class="text-texte-doux" />
--}}
@props(['nom', 'taille' => 'size-5', 'titre' => null])

{!! \App\Support\Icone::svg($nom, $attributes->class(['shrink-0', $taille]), $titre) !!}
