{{-- Tableau des ventes (rechargé seul lors d'un filtrage : en-tête X-Fragment) --}}
@if ($ventes->isEmpty())
    <x-carte>
        @if ($filtres['client'] || $filtres['vendeur'] || $filtres['statut'] || $filtres['recherche'] !== '' || $filtres['periode'] !== 'tout')
            <x-etat-vide icone="search" titre="Aucune vente trouvée" texte="Aucune vente ne correspond à ces filtres.">
                <x-bouton :href="route('ventes.index', ['periode' => 'tout'])" variante="secondaire" icone="x" data-liste-lien>Voir toutes les ventes</x-bouton>
            </x-etat-vide>
        @else
            <x-etat-vide icone="shopping-cart" titre="Aucune vente" texte="Les ventes enregistrées à la caisse apparaîtront ici.">
                @droit('ventes.creer')
                    <x-bouton :href="route('ventes.create')" icone="shopping-cart">Ouvrir la caisse</x-bouton>
                @enddroit
            </x-etat-vide>
        @endif
    </x-carte>
@else
    <x-tableau
        legende="Liste des ventes"
        :pagination="$ventes"
        :colonnes="[
            'numero' => 'Vente',
            'client' => 'Client',
            'vendeur' => 'Vendeur',
            'total' => ['libelle' => 'Total', 'alignement' => 'droite'],
            'reste' => ['libelle' => 'Reste', 'alignement' => 'droite'],
            'statut' => 'Statut',
            'actions' => ['libelle' => 'Actions', 'masque' => true, 'alignement' => 'droite'],
        ]"
    >
        @foreach ($ventes as $vente)
            @php($annulee = $vente->statut === \App\Enums\StatutVente::Annulee)
            <x-tableau.ligne @class(['opacity-70' => $annulee])>
                <x-tableau.cellule libelle="Vente" principale>
                    <a href="{{ route('ventes.show', $vente) }}" class="font-medium text-texte hover:underline">{{ $vente->numero }}</a>
                    <span class="block text-xs font-normal text-texte-doux">{{ $vente->date_vente->translatedFormat('j M Y, H:i') }}</span>
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Client">{{ $vente->client?->nom ?? '—' }}</x-tableau.cellule>
                <x-tableau.cellule libelle="Vendeur" class="text-texte-doux">{{ $vente->utilisateur->nom }}</x-tableau.cellule>
                <x-tableau.cellule libelle="Total" alignement="droite" class="font-semibold">{{ format_ar($vente->total) }}</x-tableau.cellule>
                <x-tableau.cellule libelle="Reste" alignement="droite">
                    <span @class(['font-semibold text-danger-texte' => ! $annulee && $vente->reste_a_payer > 0, 'text-texte-doux' => $annulee || $vente->reste_a_payer <= 0])>
                        {{ format_ar($annulee ? 0 : $vente->reste_a_payer) }}
                    </span>
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Statut">
                    <span class="inline-flex flex-wrap gap-1.5">
                        <x-badge :statut="$vente->statut" />
                        @unless ($annulee)
                            <x-badge :statut="$vente->statut_paiement" />
                        @endunless
                    </span>
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Actions" alignement="droite">
                    <x-menu-actions :libelle="'Actions pour la vente '.$vente->numero">
                        <x-menu-actions.element icone="eye" :href="route('ventes.show', $vente)">Voir le détail</x-menu-actions.element>
                        <x-menu-actions.element icone="printer" :href="route('ventes.ticket', $vente)" target="_blank" x-ouvrir-pdf>Ticket (PDF)</x-menu-actions.element>
                    </x-menu-actions>
                </x-tableau.cellule>
            </x-tableau.ligne>
        @endforeach
    </x-tableau>
@endif
