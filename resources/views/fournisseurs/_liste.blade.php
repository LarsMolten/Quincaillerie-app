{{-- Tableau des fournisseurs (rechargé seul lors d'une recherche : en-tête X-Fragment) --}}
@if ($fournisseurs->isEmpty())
    <x-carte>
        @if ($recherche !== '' || $statut !== 'actifs')
            <x-etat-vide icone="search" titre="Aucun fournisseur trouvé"
                         :texte="$recherche !== '' ? 'Aucun résultat pour « '.$recherche.' ».' : 'Aucun fournisseur ne correspond à ce filtre.'">
                <x-bouton :href="route('fournisseurs.index')" variante="secondaire" icone="x" data-liste-lien>Effacer la recherche</x-bouton>
            </x-etat-vide>
        @else
            <x-etat-vide icone="store" titre="Aucun fournisseur" texte="Enregistrez vos fournisseurs pour suivre vos achats et vos dettes.">
                <x-bouton type="button" icone="plus" x-data x-on:click="$dispatch('nouveau-fournisseur')">Ajouter un fournisseur</x-bouton>
            </x-etat-vide>
        @endif
    </x-carte>
@else
    <x-tableau
        legende="Liste des fournisseurs"
        :pagination="$fournisseurs"
        :colonnes="[
            'nom' => ['libelle' => 'Fournisseur', 'triable' => true],
            'telephone' => 'Téléphone',
            'email' => 'Email',
            'dette' => ['libelle' => 'Dette', 'triable' => true, 'alignement' => 'droite'],
            'statut' => 'Statut',
            'actions' => ['libelle' => 'Actions', 'masque' => true, 'alignement' => 'droite'],
        ]"
    >
        @foreach ($fournisseurs as $fournisseur)
            <x-tableau.ligne @class(['opacity-75' => ! $fournisseur->actif])>
                <x-tableau.cellule libelle="Fournisseur" principale>
                    <div class="flex items-center gap-3">
                        <x-avatar :nom="$fournisseur->nom" />
                        <span class="min-w-0">
                            <a href="{{ route('fournisseurs.show', $fournisseur) }}" class="block truncate font-medium text-texte hover:underline">{{ $fournisseur->nom }}</a>
                            @if ($fournisseur->contact)<span class="block text-xs font-normal text-texte-doux">{{ $fournisseur->contact }}</span>@endif
                        </span>
                    </div>
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Téléphone" class="chiffres whitespace-nowrap">{{ $fournisseur->telephone }}</x-tableau.cellule>
                <x-tableau.cellule libelle="Email" class="text-texte-doux">{{ $fournisseur->email ?? '—' }}</x-tableau.cellule>
                <x-tableau.cellule libelle="Dette" alignement="droite">
                    <span @class(['font-semibold text-danger-texte' => $fournisseur->dette > 0, 'text-texte-doux' => ! $fournisseur->dette])>
                        {{ format_ar($fournisseur->dette ?? 0) }}
                    </span>
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Statut">
                    <x-badge :couleur="$fournisseur->actif ? 'succes' : 'neutre'">{{ $fournisseur->actif ? 'Actif' : 'Inactif' }}</x-badge>
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Actions" alignement="droite">@include('fournisseurs._actions')</x-tableau.cellule>
            </x-tableau.ligne>
        @endforeach
    </x-tableau>
@endif
