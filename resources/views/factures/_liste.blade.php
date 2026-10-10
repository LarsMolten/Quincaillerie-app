{{-- Tableau des factures (rechargé seul lors d'un filtrage : en-tête X-Fragment) --}}
@if ($factures->isEmpty())
    <x-carte>
        @if ($filtres['statut'] || $filtres['paiement'] || $filtres['recherche'] !== '' || $filtres['periode'] !== 'tout')
            <x-etat-vide icone="search" titre="Aucune facture trouvée" texte="Aucune facture ne correspond à ces filtres.">
                <x-bouton :href="route('factures.index')" variante="secondaire" icone="x" data-liste-lien>Effacer les filtres</x-bouton>
            </x-etat-vide>
        @else
            <x-etat-vide icone="file-text" titre="Aucune facture" texte="Une facture est créée automatiquement à chaque vente validée.">
                @droit('ventes.creer')
                    <x-bouton :href="route('ventes.create')" icone="shopping-cart">Ouvrir la caisse</x-bouton>
                @enddroit
            </x-etat-vide>
        @endif
    </x-carte>
@else
    <x-tableau
        legende="Liste des factures"
        :pagination="$factures"
        :colonnes="[
            'numero' => 'Facture',
            'vente' => 'Vente',
            'client' => 'Client',
            'total' => ['libelle' => 'Total', 'alignement' => 'droite'],
            'reste' => ['libelle' => 'Reste', 'alignement' => 'droite'],
            'statut' => 'Statut',
            'actions' => ['libelle' => 'Actions', 'masque' => true, 'alignement' => 'droite'],
        ]"
    >
        @foreach ($factures as $facture)
            @php($annulee = $facture->statut === \App\Enums\StatutFacture::Annulee)
            <x-tableau.ligne @class(['opacity-70' => $annulee])>
                <x-tableau.cellule libelle="Facture" principale>
                    <button type="button" class="font-medium text-texte hover:underline" x-on:click="$dispatch('facture-apercu', {{ $facture->id }})">{{ $facture->numero }}</button>
                    <span class="block text-xs font-normal text-texte-doux">{{ $facture->date_emission->translatedFormat('j M Y, H:i') }}</span>
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Vente">
                    <a href="{{ route('ventes.show', $facture->vente_id) }}" class="text-texte-doux hover:text-texte hover:underline">{{ $facture->vente->numero }}</a>
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Client">{{ $facture->vente->client?->nom ?? '—' }}</x-tableau.cellule>
                <x-tableau.cellule libelle="Total" alignement="droite" class="font-semibold">{{ format_ar($facture->total) }}</x-tableau.cellule>
                <x-tableau.cellule libelle="Reste" alignement="droite">
                    <span @class(['font-semibold text-danger-texte' => ! $annulee && $facture->vente->reste_a_payer > 0, 'text-texte-doux' => $annulee || $facture->vente->reste_a_payer <= 0])>
                        {{ format_ar($annulee ? 0 : $facture->vente->reste_a_payer) }}
                    </span>
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Statut">
                    <span class="inline-flex flex-wrap gap-1.5">
                        <x-badge :statut="$facture->statut" />
                        @unless ($annulee)
                            <x-badge :statut="$facture->vente->statut_paiement" />
                        @endunless
                    </span>
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Actions" alignement="droite">
                    <x-menu-actions :libelle="'Actions pour la facture '.$facture->numero">
                        <x-menu-actions.element icone="eye" x-on:click="$dispatch('facture-apercu', {{ $facture->id }})">Aperçu</x-menu-actions.element>
                        <x-menu-actions.element icone="printer" :href="route('factures.pdf', [$facture, 'format' => 'a4'])" target="_blank" x-ouvrir-pdf>Imprimer (A4)</x-menu-actions.element>
                        <x-menu-actions.element icone="receipt" :href="route('factures.pdf', [$facture, 'format' => 'ticket'])" target="_blank" x-ouvrir-pdf>Ticket 80 mm</x-menu-actions.element>
                        <x-menu-actions.element icone="download" :href="route('factures.pdf', [$facture, 'telecharger' => 1])">Télécharger le PDF</x-menu-actions.element>
                        <x-menu-actions.element icone="send" x-on:click="$dispatch('facture-envoi', {{ $facture->id }})">Envoyer par email</x-menu-actions.element>
                        <x-menu-actions.element icone="share-2" x-on:click="$dispatch('facture-partage', {{ $facture->id }})">Partager (WhatsApp)</x-menu-actions.element>
                        <x-menu-actions.element icone="shopping-cart" :href="route('ventes.show', $facture->vente_id)">Voir la vente</x-menu-actions.element>
                    </x-menu-actions>
                </x-tableau.cellule>
            </x-tableau.ligne>
        @endforeach
    </x-tableau>
@endif
