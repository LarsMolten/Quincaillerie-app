{{--
    Liste déroulante native (accessible et adaptée au mobile).

    Props :
    - nom, label, aide, requis, valeur (valeur sélectionnée, remplacée par old())
    - options : [valeur => libellé] ; ou des <option> dans le slot
    - vide    : texte de l'option vide (ex. "Choisir une catégorie"), null pour aucune

    Exemple : <x-select nom="categorie_id" label="Catégorie" :options="$categories" vide="Choisir…" requis />
--}}
@props([
    'nom',
    'label' => null,
    'options' => [],
    'valeur' => null,
    'vide' => null,
    'aide' => null,
    'requis' => false,
    'id' => null,
])

@php
    use App\Support\Champ;

    $id ??= Champ::id($nom);
    $erreur = Champ::erreur($errors ?? null, $nom);
    $selection = (string) old(Champ::cle($nom), $valeur instanceof \BackedEnum ? $valeur->value : $valeur);
@endphp

<x-formulaire.groupe :id="$id" :label="$label" :aide="$aide" :erreur="$erreur" :requis="$requis">
    <div class="relative">
        <select
            id="{{ $id }}"
            name="{{ $nom }}"
            @required($requis)
            @if ($erreur) aria-invalid="true" @endif
            @if ($decrit = Champ::decritPar($id, $aide, $erreur)) aria-describedby="{{ $decrit }}" @endif
            {{ $attributes->class([
                'h-11 w-full appearance-none rounded-controle border bg-surface pl-3 pr-10 text-sm text-texte transition-colors duration-150',
                'border-danger' => $erreur,
                'border-bordure-forte' => ! $erreur,
            ]) }}
        >
            @if (! is_null($vide))
                <option value="">{{ $vide }}</option>
            @endif
            @foreach ($options as $valeurOption => $libelle)
                <option value="{{ $valeurOption }}" @selected((string) $valeurOption === $selection)>{{ $libelle }}</option>
            @endforeach
            {{ $slot }}
        </select>
        <x-icone nom="chevron-down" taille="size-4" class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-texte-doux" />
    </div>
</x-formulaire.groupe>
