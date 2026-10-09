{{--
    Case à cocher avec libellé cliquable (cible tactile de 44 px).

    Props :
    - nom, label, aide
    - valeur : valeur envoyée si cochée (défaut "1")
    - coche  : état initial (remplacé par old() après une erreur de validation)

    Exemple : <x-case nom="actif" label="Produit actif" :coche="$produit->actif" />
--}}
@props(['nom', 'label', 'valeur' => '1', 'coche' => false, 'aide' => null, 'id' => null])

@php
    use App\Support\Champ;

    $id ??= Champ::id($nom);
    $erreur = Champ::erreur($errors ?? null, $nom);
    $estCoche = old() ? (string) old(Champ::cle($nom)) === (string) $valeur : (bool) $coche;
@endphp

<div {{ $attributes->only('class')->class('space-y-1') }}>
    <label for="{{ $id }}" class="inline-flex min-h-11 cursor-pointer items-center gap-3 text-sm text-texte">
        <input
            id="{{ $id }}"
            name="{{ $nom }}"
            type="checkbox"
            value="{{ $valeur }}"
            @checked($estCoche)
            @if ($erreur) aria-invalid="true" @endif
            @if ($decrit = Champ::decritPar($id, $aide, $erreur)) aria-describedby="{{ $decrit }}" @endif
            {{ $attributes->except('class')->class('size-5 cursor-pointer rounded accent-primaire') }}
        >
        {{ $label }}
    </label>
    @if ($aide)
        <p id="{{ $id }}-aide" class="pl-8 text-sm text-texte-doux">{{ $aide }}</p>
    @endif
    @if ($erreur)
        <p id="{{ $id }}-erreur" class="pl-8 text-sm font-medium text-danger-texte">{{ $erreur }}</p>
    @endif
</div>
