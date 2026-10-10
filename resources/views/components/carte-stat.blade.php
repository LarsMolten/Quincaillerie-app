{{--
    Carte de statistique : libellé, valeur, tendance et mini-graphique optionnel.

    Props :
    - libelle   : ex. "Ventes du jour"
    - valeur    : texte déjà formaté (ex. format_ar($total) ou "128")
    - tendance  : variation en % par rapport à la période précédente (ex. 12.5 ou -3), null pour masquer
    - periode   : texte de comparaison (défaut "par rapport à hier")
    - inverse   : true si une baisse est une bonne nouvelle (ex. dépenses)
    - serie     : liste de nombres pour le mini-graphique (sparkline), vide pour masquer
    - icone     : icône Lucide
    Slot (facultatif) : précision affichée sous la valeur (ex. « 12 paiement(s) »).

    Exemple : <x-carte-stat libelle="Ventes du jour" :valeur="format_ar(1250000)" :tendance="8.4" :serie="$serie" icone="shopping-cart" />
--}}
@props([
    'libelle',
    'valeur',
    'tendance' => null,
    'periode' => 'par rapport à hier',
    'inverse' => false,
    'serie' => [],
    'icone' => null,
])

@php
    // Couleur de la tendance : hausse = succès, baisse = danger (inversé si besoin)
    $sens = $tendance === null ? null : ($tendance > 0 ? 'hausse' : ($tendance < 0 ? 'baisse' : 'stable'));
    $positif = $sens === 'stable' ? null : (($sens === 'hausse') xor $inverse);
    $pourcentage = $tendance === null ? null : rtrim(rtrim(number_format(abs($tendance), 1, ',', "\u{00A0}"), '0'), ',')."\u{00A0}%";

    // Mini-graphique : points normalisés dans un repère 100 × 32
    $points = '';
    if (count($serie) > 1) {
        $min = min($serie);
        $ecart = max(max($serie) - $min, 1);
        $pas = 100 / (count($serie) - 1);
        $points = collect($serie)
            ->map(fn ($v, $i) => round($i * $pas, 2).','.round(30 - (($v - $min) / $ecart) * 28, 2))
            ->implode(' ');
    }
@endphp

<article {{ $attributes->class('flex flex-col gap-3 rounded-carte border border-bordure bg-surface p-carte shadow-doux') }}>
    <div class="flex items-center justify-between gap-3">
        <p class="text-sm font-medium text-texte-doux">{{ $libelle }}</p>
        @if ($icone)
            <span class="grid size-9 place-items-center rounded-controle bg-primaire-doux text-lien">
                <x-icone :nom="$icone" taille="size-[1.125rem]" />
            </span>
        @endif
    </div>

    <div class="flex items-end justify-between gap-4">
        <p class="chiffres shrink-0 text-2xl font-semibold tracking-tight text-texte">{{ $valeur }}</p>

        @if ($points)
            <svg viewBox="0 0 100 32" preserveAspectRatio="none" class="h-8 min-w-0 max-w-24 flex-1 text-primaire" aria-hidden="true" focusable="false">
                <polyline points="{{ $points }}" fill="none" stroke="currentColor" stroke-width="2"
                          stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
            </svg>
        @endif
    </div>

    @if ($sens)
        <p @class([
            'flex min-w-0 flex-wrap items-center gap-x-1 gap-y-0.5 text-sm font-medium',
            'text-succes-texte' => $positif === true,
            'text-danger-texte' => $positif === false,
            'text-texte-doux' => $positif === null,
        ])>
            <span class="inline-flex items-center gap-1 whitespace-nowrap" aria-hidden="true">
                <x-icone :nom="match ($sens) { 'hausse' => 'arrow-up-right', 'baisse' => 'arrow-down-right', default => 'arrow-up' }"
                         taille="size-4" @class(['rotate-45' => $sens === 'stable']) />
                <span class="chiffres">{{ $sens === 'baisse' ? '−' : ($sens === 'hausse' ? '+' : '') }}{{ $pourcentage }}</span>
            </span>
            <span class="sr-only">{{ ['hausse' => 'En hausse de', 'baisse' => 'En baisse de', 'stable' => 'Stable,'][$sens] }} {{ $pourcentage }}</span>
            <span class="whitespace-nowrap font-normal text-texte-doux">{{ $periode }}</span>
        </p>
    @endif

    @if ($slot->isNotEmpty())
        <div class="-mt-2 text-sm text-texte-doux">{{ $slot }}</div>
    @endif
</article>
