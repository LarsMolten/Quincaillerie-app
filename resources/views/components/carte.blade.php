{{--
    Carte : surface arrondie à ombre douce, élément de base des pages et du « bento grid ».

    Props :
    - titre, description : en-tête optionnel
    - padding : false pour un contenu bord à bord (tableau, liste)
    Slot nommé : actions (boutons alignés à droite de l'en-tête)

    Exemple :
    <x-carte titre="Stock faible">
        <x-slot:actions><x-bouton variante="fantome" taille="sm">Tout voir</x-bouton></x-slot:actions>
        …
    </x-carte>
--}}
@props(['titre' => null, 'description' => null, 'padding' => true])

<section {{ $attributes->class('rounded-carte border border-bordure bg-surface shadow-doux') }}>
    @if ($titre || isset($actions))
        <header class="flex items-start justify-between gap-4 px-carte pt-carte">
            <div class="min-w-0">
                @if ($titre)
                    <h2 class="text-base font-semibold text-texte">{{ $titre }}</h2>
                @endif
                @if ($description)
                    <p class="mt-0.5 text-sm text-texte-doux">{{ $description }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div @class(['p-carte' => $padding, 'pt-4' => $padding && ($titre || isset($actions)), 'mt-4' => ! $padding && ($titre || isset($actions))])>
        {{ $slot }}
    </div>
</section>
