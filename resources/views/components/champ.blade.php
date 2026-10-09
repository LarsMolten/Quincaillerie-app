{{--
    Champ de saisie avec libellé, aide, erreur de validation et préfixe/suffixe optionnels.

    Props :
    - nom     : attribut name (accepte la notation tableau "lignes[0][prix]")
    - label, aide, requis
    - type    : text (défaut), email, number, password, date, tel…
    - valeur  : valeur initiale (remplacée par old() après une erreur de validation)
    - prefixe / suffixe : texte affiché dans le champ (ex. suffixe="Ar")
    - icone   : icône Lucide à gauche
    - montant : true pour aligner à droite en chiffres tabulaires
    Les autres attributs (placeholder, min, step, inputmode, autofocus…) vont sur l'<input>.

    Exemple : <x-champ nom="prix_vente" label="Prix de vente" type="number" suffixe="Ar" montant requis />
--}}
@props([
    'nom',
    'label' => null,
    'type' => 'text',
    'valeur' => null,
    'aide' => null,
    'prefixe' => null,
    'suffixe' => null,
    'icone' => null,
    'montant' => false,
    'requis' => false,
    'id' => null,
])

@php
    use App\Support\Champ;

    $id ??= Champ::id($nom);
    $erreur = Champ::erreur($errors ?? null, $nom);
    $valeurAffichee = $type === 'password' ? null : old(Champ::cle($nom), $valeur);
@endphp

<x-formulaire.groupe :id="$id" :label="$label" :aide="$aide" :erreur="$erreur" :requis="$requis">
    <div @class([
        'flex h-11 items-stretch overflow-hidden rounded-controle border bg-surface transition-colors duration-150',
        'focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-anneau',
        'border-danger' => $erreur,
        'border-bordure-forte' => ! $erreur,
    ])>
        @if ($prefixe || $icone)
            <span class="flex items-center border-r border-bordure bg-fond px-3 text-sm text-texte-doux" aria-hidden="true">
                @if ($icone)
                    <x-icone :nom="$icone" taille="size-4" />
                @endif
                {{ $prefixe }}
            </span>
        @endif

        <input
            id="{{ $id }}"
            name="{{ $nom }}"
            type="{{ $type }}"
            @if (! is_null($valeurAffichee)) value="{{ $valeurAffichee }}" @endif
            @required($requis)
            @if ($erreur) aria-invalid="true" @endif
            @if ($decrit = Champ::decritPar($id, $aide, $erreur)) aria-describedby="{{ $decrit }}" @endif
            {{ $attributes->class([
                'min-w-0 flex-1 bg-transparent px-3 text-sm text-texte placeholder:text-texte-doux focus:outline-none',
                'chiffres text-right' => $montant,
            ]) }}
        >

        @if ($suffixe)
            <span class="flex items-center border-l border-bordure bg-fond px-3 text-sm font-medium text-texte-doux" aria-hidden="true">
                {{ $suffixe }}
            </span>
        @endif
    </div>
</x-formulaire.groupe>
