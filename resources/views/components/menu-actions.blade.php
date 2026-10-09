{{--
    Menu d'actions « ⋯ » (verre dépoli), accessible au clavier :
    Entrée/Espace/Flèche bas pour ouvrir, flèches haut/bas, Début/Fin, Échap pour fermer.
    Positionné en « fixed » pour ne pas être coupé par le défilement d'un tableau (recalculé au défilement).

    Props :
    - libelle      : nom annoncé du bouton (défaut "Actions")
    - classeBouton : classes du bouton déclencheur (défaut : bouton carré « ⋯ »)
    - largeur      : classe de largeur minimale du panneau (défaut "min-w-52")
    Slots : declencheur (contenu du bouton, défaut icône « ⋯ »), entete (bloc non interactif en haut du panneau)
    Contenu : des x-menu-actions.element

    Exemple :
    <x-menu-actions libelle="Actions pour Ciment 50 kg">
        <x-menu-actions.element icone="pencil" href="…">Modifier</x-menu-actions.element>
        <x-menu-actions.element icone="trash-2" danger x-on:click="$dispatch('ouvrir-modal', 'supprimer-1')">Supprimer</x-menu-actions.element>
    </x-menu-actions>
--}}
@props([
    'libelle' => 'Actions',
    'classeBouton' => 'grid size-9 place-items-center rounded-controle text-texte-doux hover:bg-neutre-doux hover:text-texte pointer-coarse:size-11',
    'largeur' => 'min-w-52',
])

<div
    x-data="menuActions"
    x-on:keydown.escape.prevent.stop="fermer()"
    x-on:click.outside="fermer(false)"
    x-on:scroll.window.capture="ouvert && positionner()"
    x-on:resize.window="ouvert && positionner()"
    {{ $attributes->class('relative inline-block') }}
>
    <button
        x-ref="bouton"
        type="button"
        aria-haspopup="menu"
        aria-expanded="false"
        x-bind:aria-expanded="ouvert.toString()"
        aria-label="{{ $libelle }}"
        x-on:click="basculer()"
        x-on:keydown.arrow-down.prevent="ouvrir()"
        x-on:keydown.arrow-up.prevent="ouvrir('dernier')"
        class="{{ $classeBouton }} transition-colors duration-150"
    >
        @isset($declencheur)
            {{ $declencheur }}
        @else
            <x-icone nom="ellipsis" />
        @endisset
    </button>

    <div
        x-ref="liste"
        x-show="ouvert"
        x-cloak
        x-transition:enter="transition duration-150 ease-out"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:leave="transition duration-100 ease-in"
        x-transition:leave-end="opacity-0 scale-95"
        x-bind:style="position"
        role="menu"
        tabindex="-1"
        aria-label="{{ $libelle }}"
        x-on:keydown.arrow-down.prevent="deplacer(1)"
        x-on:keydown.arrow-up.prevent="deplacer(-1)"
        x-on:keydown.home.prevent="extremite('debut')"
        x-on:keydown.end.prevent="extremite('fin')"
        x-on:keydown.tab="fermer(false)"
        class="verre fixed z-40 {{ $largeur }} rounded-controle border border-bordure p-1 shadow-eleve"
    >
        @isset($entete)
            <div class="border-b border-bordure px-3 pb-2 pt-1.5 mb-1">{{ $entete }}</div>
        @endisset
        {{ $slot }}
    </div>
</div>
