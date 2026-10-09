{{--
    Avatar à initiales, coloré de façon stable selon le nom (jetons sémantiques).
    Décoratif (aria-hidden) : le nom doit toujours être écrit à côté.

    Props : nom, taille (sm | md | lg, défaut md)
    Exemple : <x-avatar :nom="$client->nom" />
--}}
@props(['nom', 'taille' => 'md'])

@php
    use App\Support\Initiales;

    // Classes complètes (Tailwind ne détecte pas les noms construits dynamiquement)
    $couleurs = [
        'info' => 'bg-info-doux text-info-texte',
        'succes' => 'bg-succes-doux text-succes-texte',
        'alerte' => 'bg-alerte-doux text-alerte-texte',
        'neutre' => 'bg-neutre-doux text-neutre-texte',
        'primaire' => 'bg-primaire-doux text-lien',
    ];
    $tailles = ['sm' => 'size-8 text-xs', 'md' => 'size-10 text-sm', 'lg' => 'size-16 text-xl'];
@endphp

<span aria-hidden="true" {{ $attributes->class([
    'grid shrink-0 place-items-center rounded-full font-semibold select-none',
    $couleurs[Initiales::couleur($nom)],
    $tailles[$taille] ?? $tailles['md'],
]) }}>{{ Initiales::de($nom) ?: '?' }}</span>
