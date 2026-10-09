{{--
    Panneau latéral coulissant (slide-over) sur <dialog> : mêmes comportements que x-modal
    (focus piégé, Échap, clic sur le voile, retour du focus), adapté aux longs formulaires.

    Props :
    - id, titre, description, titreDynamique (expression Alpine)
    - largeur : classe de largeur maximale (défaut "max-w-xl")
    Slot nommé : pied (barre d'actions collée en bas)

    Exemple :
    <x-panneau id="panneau-produit" titre="Nouveau produit">…<x-slot:pied>…</x-slot:pied></x-panneau>
--}}
@props(['id', 'titre', 'description' => null, 'titreDynamique' => null, 'largeur' => 'max-w-xl'])

<dialog
    id="{{ $id }}"
    x-data="modal(@js($id))"
    x-on:click="clicFond($event)"
    aria-labelledby="{{ $id }}-titre"
    {{ $attributes->class([
        'my-0 ml-auto mr-0 h-dvh max-h-dvh w-full border-l border-bordure bg-surface p-0 text-texte shadow-eleve',
        'open:flex open:flex-col open:animate-panneau',
        $largeur,
    ]) }}
>
    <header class="flex shrink-0 items-start justify-between gap-4 border-b border-bordure px-6 py-4">
        <div class="min-w-0">
            <h2 id="{{ $id }}-titre" class="text-lg font-semibold text-texte" @if ($titreDynamique) x-text="{{ $titreDynamique }}" @endif>{{ $titre }}</h2>
            @if ($description)
                <p class="mt-0.5 text-sm text-texte-doux">{{ $description }}</p>
            @endif
        </div>
        <button type="button" x-on:click="fermer()" aria-label="Fermer le panneau"
                class="-m-2 grid size-11 shrink-0 place-items-center rounded-controle text-texte-doux transition-colors duration-150 hover:bg-neutre-doux hover:text-texte">
            <x-icone nom="x" />
        </button>
    </header>

    <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5">
        {{ $slot }}
    </div>

    @isset($pied)
        <footer class="flex shrink-0 flex-col-reverse gap-3 border-t border-bordure bg-surface px-6 py-4 sm:flex-row sm:justify-end">
            {{ $pied }}
        </footer>
    @endisset
</dialog>
