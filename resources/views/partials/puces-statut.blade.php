{{--
    Puces de filtre par statut (Actifs par défaut, Inactifs, Tous) pour une liste listeDynamique.
    Variables : $statut ('actifs' | 'inactifs' | 'tous'), $masculin (accord des libellés, défaut true)
--}}
@php
    $courant = $statut === 'actifs' ? '' : $statut;
    $libelles = ($masculin ?? true)
        ? ['' => 'Actifs', 'inactifs' => 'Inactifs', 'tous' => 'Tous']
        : ['' => 'Actives', 'inactifs' => 'Inactives', 'tous' => 'Toutes'];
@endphp
<input type="hidden" name="statut" value="{{ $courant }}">
<div x-data="{ statut: @js($courant) }" role="group" aria-label="Filtrer par statut" class="flex flex-wrap gap-2">
    @foreach ($libelles as $valeur => $libelle)
        <button type="button" x-on:click="statut = '{{ $valeur }}'; filtrer('statut', statut)"
                aria-pressed="{{ $courant === $valeur ? 'true' : 'false' }}" x-bind:aria-pressed="(statut === '{{ $valeur }}').toString()"
                class="h-9 rounded-full border border-bordure-forte px-4 text-sm font-medium text-texte-doux transition-colors duration-150 hover:text-texte aria-pressed:border-primaire aria-pressed:bg-primaire-doux aria-pressed:text-lien pointer-coarse:h-11">
            {{ $libelle }}
        </button>
    @endforeach
</div>
