{{-- Tableau des unités (rechargé seul lors d'une recherche : en-tête X-Fragment) --}}
@if ($unites->isEmpty())
    <x-carte>
        @if ($recherche !== '')
            <x-etat-vide icone="search" titre="Aucune unité trouvée" :texte="'Aucun résultat pour « '.$recherche.' ».'">
                <x-bouton :href="route('unites.index')" variante="secondaire" icone="x" data-liste-lien>Effacer la recherche</x-bouton>
            </x-etat-vide>
        @else
            <x-etat-vide icone="ruler" titre="Aucune unité" texte="Ajoutez les unités dans lesquelles vous vendez vos produits.">
                <x-bouton type="button" icone="plus" x-data x-on:click="$dispatch('nouvelle-unite')">Ajouter une unité</x-bouton>
            </x-etat-vide>
        @endif
    </x-carte>
@else
    <x-tableau
        legende="Unités de vente"
        :pagination="$unites"
        :colonnes="[
            'nom' => ['libelle' => 'Nom', 'triable' => true],
            'abreviation' => 'Abréviation',
            'produits' => ['libelle' => 'Produits', 'triable' => true, 'alignement' => 'droite'],
            'actions' => ['libelle' => 'Actions', 'masque' => true, 'alignement' => 'droite'],
        ]"
    >
        @foreach ($unites as $unite)
            <x-tableau.ligne>
                <x-tableau.cellule libelle="Nom" principale class="font-medium">{{ $unite->nom }}</x-tableau.cellule>
                <x-tableau.cellule libelle="Abréviation">
                    <span class="rounded-md bg-neutre-doux px-2 py-0.5 font-mono text-xs text-neutre-texte">{{ $unite->abreviation }}</span>
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Produits" alignement="droite">
                    {{ $unite->produits_count }}
                    @if ($unite->produits_count > 0)
                        <span class="sr-only">(unité utilisée : suppression impossible)</span>
                    @endif
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Actions" alignement="droite">
                    <x-menu-actions :libelle="'Actions pour l\'unité '.$unite->nom">
                        <x-menu-actions.element icone="pencil"
                            x-on:click="$dispatch('editer-unite', {{ \Illuminate\Support\Js::from(['id' => $unite->id, 'nom' => $unite->nom, 'abreviation' => $unite->abreviation]) }})">
                            Modifier
                        </x-menu-actions.element>
                        <x-menu-actions.element icone="trash-2" danger
                            x-on:click="$dispatch('ouvrir-modal', 'supprimer-unite-{{ $unite->id }}')">
                            Supprimer
                        </x-menu-actions.element>
                    </x-menu-actions>
                    <x-confirmation :id="'supprimer-unite-'.$unite->id" :action="route('unites.destroy', $unite)"
                                    :titre="'Supprimer l\'unité « '.$unite->nom.' » ?'"
                                    :message="$unite->produits_count > 0
                                        ? 'Cette unité est utilisée par '.$unite->produits_count.' produit(s) : la suppression sera refusée. Modifiez d\'abord ces produits.'
                                        : 'L\'unité n\'est utilisée par aucun produit. Elle sera retirée de la liste (opération enregistrée dans le journal).'" />
                </x-tableau.cellule>
            </x-tableau.ligne>
        @endforeach
    </x-tableau>
@endif
