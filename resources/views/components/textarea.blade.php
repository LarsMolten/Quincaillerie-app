{{--
    Zone de texte multiligne.

    Props : nom, label, aide, requis, valeur (remplacée par old()), lignes (défaut 4)
    Exemple : <x-textarea nom="notes" label="Notes" aide="Visible uniquement en interne." />
--}}
@props([
    'nom',
    'label' => null,
    'valeur' => null,
    'aide' => null,
    'lignes' => 4,
    'requis' => false,
    'id' => null,
])

@php
    use App\Support\Champ;

    $id ??= Champ::id($nom);
    $erreur = Champ::erreur($errors ?? null, $nom);
@endphp

<x-formulaire.groupe :id="$id" :label="$label" :aide="$aide" :erreur="$erreur" :requis="$requis">
    <textarea
        id="{{ $id }}"
        name="{{ $nom }}"
        rows="{{ $lignes }}"
        @required($requis)
        @if ($erreur) aria-invalid="true" @endif
        @if ($decrit = Champ::decritPar($id, $aide, $erreur)) aria-describedby="{{ $decrit }}" @endif
        {{ $attributes->class([
            'block w-full rounded-controle border bg-surface px-3 py-2.5 text-sm text-texte placeholder:text-texte-doux transition-colors duration-150',
            'border-danger' => $erreur,
            'border-bordure-forte' => ! $erreur,
        ]) }}
    >{{ old(App\Support\Champ::cle($nom), $valeur) }}</textarea>
</x-formulaire.groupe>
