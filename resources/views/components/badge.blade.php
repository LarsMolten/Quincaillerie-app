{{--
    Badge de statut en pilule, coloré par jeton sémantique.

    Props :
    - couleur : succes | alerte | danger | info | neutre (défaut neutre)
    - statut  : enum exposant libelle() et couleur() (StatutVente, EtatStock…) ; remplace couleur et texte
    - point   : affiche la pastille colorée (défaut true)

    Exemples :
    <x-badge :statut="$vente->statut" />
    <x-badge couleur="info">Nouveau</x-badge>
--}}
@props(['couleur' => 'neutre', 'statut' => null, 'point' => true])

@php
    if ($statut) {
        $couleur = $statut->couleur();
    }

    // Classes complètes (Tailwind ne détecte pas les noms construits dynamiquement)
    $styles = [
        'succes' => ['bg-succes-doux text-succes-texte', 'bg-succes'],
        'alerte' => ['bg-alerte-doux text-alerte-texte', 'bg-alerte'],
        'danger' => ['bg-danger-doux text-danger-texte', 'bg-danger'],
        'info' => ['bg-info-doux text-info-texte', 'bg-info'],
        'neutre' => ['bg-neutre-doux text-neutre-texte', 'bg-neutre'],
    ];
    [$fond, $pastille] = $styles[$couleur] ?? $styles['neutre'];
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap', $fond]) }}>
    @if ($point)
        <span class="size-1.5 rounded-full {{ $pastille }}" aria-hidden="true"></span>
    @endif
    {{ $slot->isEmpty() && $statut ? $statut->libelle() : $slot }}
</span>
