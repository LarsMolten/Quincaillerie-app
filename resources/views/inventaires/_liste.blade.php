{{-- Tableau des inventaires (rechargé seul lors d'un filtrage : en-tête X-Fragment) --}}
@if ($inventaires->isEmpty())
    <x-carte>
        <x-etat-vide icone="clipboard-list" titre="Aucun inventaire" texte="Ouvrez un inventaire pour compter le stock réel et corriger les écarts.">
            <x-bouton type="button" icone="plus" x-data x-on:click="$dispatch('ouvrir-modal', 'nouvel-inventaire')">Nouvel inventaire</x-bouton>
        </x-etat-vide>
    </x-carte>
@else
    <x-tableau
        legende="Liste des inventaires"
        :pagination="$inventaires"
        :colonnes="[
            'numero' => 'Inventaire',
            'progression' => 'Comptage',
            'notes' => 'Notes',
            'statut' => 'Statut',
            'actions' => ['libelle' => 'Actions', 'masque' => true, 'alignement' => 'droite'],
        ]"
    >
        @foreach ($inventaires as $inventaire)
            @php($pourcentage = $inventaire->lignes_count > 0 ? round($inventaire->lignes_comptees_count / $inventaire->lignes_count * 100) : 0)
            <x-tableau.ligne>
                <x-tableau.cellule libelle="Inventaire" principale>
                    <a href="{{ route('inventaires.show', $inventaire) }}" class="font-medium text-texte hover:underline">{{ $inventaire->numero }}</a>
                    <span class="block text-xs font-normal text-texte-doux">{{ $inventaire->date_inventaire->translatedFormat('j M Y') }} · {{ $inventaire->utilisateur->nom }}</span>
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Comptage">
                    <div class="flex min-w-40 items-center gap-3">
                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-neutre-doux" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $pourcentage }}"
                             aria-label="Produits comptés">
                            <div class="h-full rounded-full bg-primaire" style="width: {{ $pourcentage }}%"></div>
                        </div>
                        <span class="chiffres text-xs text-texte-doux">{{ $inventaire->lignes_comptees_count }}/{{ $inventaire->lignes_count }}</span>
                    </div>
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Notes" class="max-w-64 truncate text-texte-doux">{{ $inventaire->notes ?? '—' }}</x-tableau.cellule>
                <x-tableau.cellule libelle="Statut"><x-badge :statut="$inventaire->statut" /></x-tableau.cellule>
                <x-tableau.cellule libelle="Actions" alignement="droite">
                    <x-menu-actions :libelle="'Actions pour l\'inventaire '.$inventaire->numero">
                        <x-menu-actions.element icone="clipboard-list" :href="route('inventaires.show', $inventaire)">{{ $inventaire->statut === \App\Enums\StatutInventaire::Valide ? 'Voir' : 'Compter' }}</x-menu-actions.element>
                        <x-menu-actions.element icone="printer" :href="route('inventaires.pdf', [$inventaire, 'feuille'])" target="_blank" x-ouvrir-pdf>Feuille de comptage</x-menu-actions.element>
                        <x-menu-actions.element icone="file-text" :href="route('inventaires.pdf', [$inventaire, 'rapport'])" target="_blank" x-ouvrir-pdf>Rapport d'écarts</x-menu-actions.element>
                    </x-menu-actions>
                </x-tableau.cellule>
            </x-tableau.ligne>
        @endforeach
    </x-tableau>
@endif
