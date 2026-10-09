{{--
    Modale sur <dialog> natif, en verre dépoli : focus piégé (arrière-plan inerte), fermeture
    par Échap, par le bouton ✕ ou par un clic sur le voile ; le focus revient ensuite à l'élément déclencheur.

    Props :
    - id     : identifiant unique (ouverture : $dispatch('ouvrir-modal', 'id'), fermeture : $dispatch('fermer-modal', 'id'))
    - titre, description
    - taille : sm | md (défaut) | lg | xl
    - role   : dialog (défaut) | alertdialog (confirmation d'une action risquée)
    Slot nommé : pied (boutons d'action, alignés à droite)

    Exemple :
    <x-bouton type="button" x-on:click="$dispatch('ouvrir-modal', 'nouvelle-categorie')">Ajouter</x-bouton>
    <x-modal id="nouvelle-categorie" titre="Nouvelle catégorie">…<x-slot:pied>…</x-slot:pied></x-modal>
--}}
@props(['id', 'titre', 'description' => null, 'taille' => 'md', 'role' => 'dialog'])

@php
    $largeurs = ['sm' => 'max-w-sm', 'md' => 'max-w-lg', 'lg' => 'max-w-2xl', 'xl' => 'max-w-4xl'];
@endphp

<dialog
    id="{{ $id }}"
    x-data="modal(@js($id))"
    x-on:click="clicFond($event)"
    @if ($role === 'alertdialog') role="alertdialog" @endif
    aria-labelledby="{{ $id }}-titre"
    @if ($description) aria-describedby="{{ $id }}-description" @endif
    {{ $attributes->class([
        'verre m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] overflow-y-auto rounded-carte border border-bordure p-0 text-texte shadow-eleve',
        'open:animate-apparition',
        $largeurs[$taille] ?? $largeurs['md'],
    ]) }}
>
    <div class="p-6">
        <header class="mb-4 flex items-start justify-between gap-4">
            <div>
                <h2 id="{{ $id }}-titre" class="text-lg font-semibold text-texte">{{ $titre }}</h2>
                @if ($description)
                    <p id="{{ $id }}-description" class="mt-1 text-sm text-texte-doux">{{ $description }}</p>
                @endif
            </div>
            <button type="button" x-on:click="fermer()" aria-label="Fermer"
                    class="-m-2 grid size-11 shrink-0 place-items-center rounded-controle text-texte-doux transition-colors duration-150 hover:bg-neutre-doux hover:text-texte">
                <x-icone nom="x" />
            </button>
        </header>

        {{ $slot }}
    </div>

    @isset($pied)
        <footer class="flex flex-col-reverse gap-3 border-t border-bordure px-6 py-4 sm:flex-row sm:justify-end">
            {{ $pied }}
        </footer>
    @endisset
</dialog>
