{{--
    En-tête commun des rapports : titre, période en clair, exports PDF / Excel, filtres (période + filtres du rapport).
    Variables : $titre, $periode, $exports, $rapport ; $champs : HTML des filtres propres au rapport (selects), facultatif.
--}}
@php($classeChamp = 'h-9 rounded-controle border border-bordure-forte bg-surface px-2.5 text-sm text-texte pointer-coarse:h-11')

<x-entete-page :titre="$titre" :description="'Période : '.$periode->libelle()"
               :fil="['Tableau de bord' => route('accueil'), 'Rapports' => null, $titre => null]">
    <x-slot:actions>
        <x-bouton :href="$exports['pdf']" target="_blank" x-data x-ouvrir-pdf variante="secondaire" icone="printer">PDF</x-bouton>
        <x-bouton :href="$exports['excel']" variante="secondaire" icone="download">Excel</x-bouton>
    </x-slot:actions>
</x-entete-page>

<form method="GET" action="{{ route('rapports.'.$rapport) }}" class="mb-6 flex flex-col gap-3 rounded-carte border border-bordure bg-surface p-4 shadow-doux lg:flex-row lg:items-center lg:justify-between"
      x-data x-on:change="$event.target.tagName === 'SELECT' && $el.requestSubmit()" aria-label="Filtres du rapport">
    <x-filtre-periode :periode="$periode" />
    @isset($champs)
        <div class="flex flex-wrap items-center gap-2">{!! $champs !!}</div>
    @endisset
</form>
