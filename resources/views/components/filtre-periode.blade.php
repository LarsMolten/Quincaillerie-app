{{--
    Sélecteur de période des rapports : puces Aujourd'hui / 7 jours / Ce mois / Mois dernier / Personnalisé.
    Un raccourci recharge immédiatement la page ; « Personnalisé » affiche deux dates et le bouton « Appliquer ».
    À placer dans un <form method="GET"> (les autres filtres du formulaire sont conservés).

    Props : periode (App\Rapports\Periode)
--}}
@props(['periode'])

@php
    $classePuce = 'h-9 rounded-full border border-bordure-forte px-3.5 text-sm font-medium whitespace-nowrap text-texte-doux transition-colors duration-150 hover:text-texte aria-pressed:border-primaire aria-pressed:bg-primaire-doux aria-pressed:text-lien pointer-coarse:h-11';
    $classeDate = 'h-9 rounded-controle border border-bordure-forte bg-surface px-2.5 text-sm text-texte pointer-coarse:h-11';
@endphp

<div x-data="{ choix: @js($periode->choix) }" {{ $attributes->class('flex flex-wrap items-center gap-2') }}>
    <input type="hidden" name="periode" x-bind:value="choix" value="{{ $periode->choix }}">
    <div role="group" aria-label="Période" class="flex flex-wrap gap-2">
        @foreach (\App\Rapports\Periode::RACCOURCIS as $valeur => $libelle)
            <button type="button" class="{{ $classePuce }}" aria-pressed="{{ $periode->choix === $valeur ? 'true' : 'false' }}"
                    x-bind:aria-pressed="(choix === '{{ $valeur }}').toString()"
                    x-on:click="choix = '{{ $valeur }}'; if (choix !== 'personnalise') $nextTick(() => $el.closest('form').requestSubmit())">
                {{ $libelle }}
            </button>
        @endforeach
    </div>
    <div x-show="choix === 'personnalise'" x-cloak class="flex flex-wrap items-center gap-2 text-sm text-texte-doux">
        <label for="periode-du">Du</label>
        <input id="periode-du" type="date" name="du" value="{{ $periode->debut->toDateString() }}" max="{{ today()->toDateString() }}" class="{{ $classeDate }}"
               x-bind:disabled="choix !== 'personnalise'">
        <label for="periode-au">au</label>
        <input id="periode-au" type="date" name="au" value="{{ $periode->fin->toDateString() }}" max="{{ today()->toDateString() }}" class="{{ $classeDate }}"
               x-bind:disabled="choix !== 'personnalise'">
        <x-bouton taille="sm" icone="check">Appliquer</x-bouton>
    </div>
</div>
