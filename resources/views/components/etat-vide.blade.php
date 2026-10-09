{{--
    État vide : affiché à la place d'une liste sans résultat.

    Props : icone (défaut "inbox"), titre, texte
    Slot : bouton d'action (ex. « Ajouter un produit »)

    Exemple :
    <x-etat-vide icone="package" titre="Aucun produit" texte="Commencez par ajouter votre premier article.">
        <x-bouton icone="plus" href="…">Ajouter un produit</x-bouton>
    </x-etat-vide>
--}}
@props(['icone' => 'inbox', 'titre', 'texte' => null])

<div {{ $attributes->class('flex flex-col items-center px-6 py-12 text-center') }}>
    <span class="grid size-14 place-items-center rounded-full bg-primaire-doux text-lien">
        <x-icone :nom="$icone" taille="size-7" />
    </span>
    <h3 class="mt-4 text-base font-semibold text-texte">{{ $titre }}</h3>
    @if ($texte)
        <p class="mt-1 max-w-sm text-sm text-texte-doux">{{ $texte }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-6 flex flex-wrap justify-center gap-3">{{ $slot }}</div>
    @endif
</div>
