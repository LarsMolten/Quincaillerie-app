{{--
    Interrupteur marche/arrêt (role="switch"), envoyé au serveur via un champ caché (1 ou 0).

    Props : nom, label, aide, actif (état initial, remplacé par old())
    Exemple : <x-interrupteur nom="actif" label="Client actif" :actif="$client->actif" />
--}}
@props(['nom', 'label', 'actif' => false, 'aide' => null, 'id' => null])

@php
    use App\Support\Champ;

    $id ??= Champ::id($nom);
    $erreur = Champ::erreur($errors ?? null, $nom);
    $estActif = (bool) old(Champ::cle($nom), $actif);
@endphp

<div x-data="{ actif: @js($estActif) }" {{ $attributes->class('space-y-1') }}>
    <div class="flex min-h-11 items-center justify-between gap-4">
        <span id="{{ $id }}-libelle" class="text-sm font-medium text-texte">{{ $label }}</span>
        <input type="hidden" name="{{ $nom }}" x-bind:value="actif ? 1 : 0" value="{{ $estActif ? 1 : 0 }}">
        <button
            id="{{ $id }}"
            type="button"
            role="switch"
            aria-checked="{{ $estActif ? 'true' : 'false' }}"
            x-bind:aria-checked="actif.toString()"
            aria-labelledby="{{ $id }}-libelle"
            @if ($decrit = Champ::decritPar($id, $aide, $erreur)) aria-describedby="{{ $decrit }}" @endif
            x-on:click="actif = ! actif"
            class="relative inline-flex h-7 w-12 shrink-0 cursor-pointer items-center rounded-full border border-bordure-forte bg-neutre-doux transition-colors duration-150 aria-checked:border-primaire aria-checked:bg-primaire"
        >
            <span
                class="inline-block size-5 translate-x-0.5 rounded-full bg-surface shadow-doux ring-1 ring-bordure-forte transition-transform duration-150"
                x-bind:class="actif ? 'translate-x-[1.375rem]' : 'translate-x-0.5'"
            ></span>
        </button>
    </div>
    @if ($aide)
        <p id="{{ $id }}-aide" class="text-sm text-texte-doux">{{ $aide }}</p>
    @endif
    @if ($erreur)
        <p id="{{ $id }}-erreur" class="text-sm font-medium text-danger-texte">{{ $erreur }}</p>
    @endif
</div>
