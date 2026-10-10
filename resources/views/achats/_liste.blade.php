{{-- Tableau des achats (rechargé seul lors d'un filtrage : en-tête X-Fragment) --}}
@if ($achats->isEmpty())
    <x-carte>
        @if ($filtres['periode'] !== '' || $filtres['fournisseur'] || $filtres['paiement'] || $filtres['recherche'] !== '')
            <x-etat-vide icone="search" titre="Aucun achat trouvé" texte="Aucun achat ne correspond à ces filtres.">
                <x-bouton :href="route('achats.index')" variante="secondaire" icone="x" data-liste-lien>Effacer les filtres</x-bouton>
            </x-etat-vide>
        @else
            <x-etat-vide icone="truck" titre="Aucun achat" texte="Enregistrez vos réceptions de marchandises pour mettre le stock à jour.">
                @droit('achats.creer')
                    <x-bouton :href="route('achats.create')" icone="plus">Nouvel achat</x-bouton>
                @enddroit
            </x-etat-vide>
        @endif
    </x-carte>
@else
    <x-tableau
        legende="Liste des achats"
        :pagination="$achats"
        :colonnes="[
            'numero' => 'Achat',
            'fournisseur' => 'Fournisseur',
            'total' => ['libelle' => 'Total', 'alignement' => 'droite'],
            'paye' => ['libelle' => 'Payé', 'alignement' => 'droite'],
            'reste' => ['libelle' => 'Reste', 'alignement' => 'droite'],
            'paiement' => 'Paiement',
            'actions' => ['libelle' => 'Actions', 'masque' => true, 'alignement' => 'droite'],
        ]"
    >
        @foreach ($achats as $achat)
            @php($annule = $achat->statut === \App\Enums\StatutAchat::Annule)
            <x-tableau.ligne @class(['opacity-70' => $annule])>
                <x-tableau.cellule libelle="Achat" principale>
                    <a href="{{ route('achats.show', $achat) }}" class="font-medium text-texte hover:underline">{{ $achat->numero }}</a>
                    <span class="block text-xs font-normal text-texte-doux">{{ $achat->date_achat->translatedFormat('j M Y') }}</span>
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Fournisseur">{{ $achat->fournisseur->nom }}</x-tableau.cellule>
                <x-tableau.cellule libelle="Total" alignement="droite" class="font-semibold">{{ format_ar($achat->total) }}</x-tableau.cellule>
                <x-tableau.cellule libelle="Payé" alignement="droite" class="text-texte-doux">{{ format_ar($achat->montant_paye) }}</x-tableau.cellule>
                <x-tableau.cellule libelle="Reste" alignement="droite">
                    <span @class(['font-semibold text-danger-texte' => ! $annule && $achat->reste_a_payer > 0, 'text-texte-doux' => $annule || $achat->reste_a_payer <= 0])>
                        {{ format_ar($annule ? 0 : $achat->reste_a_payer) }}
                    </span>
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Paiement">
                    @if ($annule)
                        <x-badge :statut="$achat->statut" />
                    @else
                        <x-badge :statut="$achat->statut_paiement" />
                    @endif
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Actions" alignement="droite">
                    <x-menu-actions :libelle="'Actions pour l\'achat '.$achat->numero">
                        <x-menu-actions.element icone="eye" :href="route('achats.show', $achat)">Voir le détail</x-menu-actions.element>
                        <x-menu-actions.element icone="printer" :href="route('achats.bon', $achat)" target="_blank" x-ouvrir-pdf>Bon d'achat (PDF)</x-menu-actions.element>
                    </x-menu-actions>
                </x-tableau.cellule>
            </x-tableau.ligne>
        @endforeach
    </x-tableau>
@endif
