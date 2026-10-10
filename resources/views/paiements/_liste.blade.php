{{-- Historique des paiements (rechargé seul lors d'un filtrage : en-tête X-Fragment) --}}
@if ($paiements->isEmpty())
    <x-carte>
        @if ($filtres['mode'] || $filtres['sens'] || $filtres['recherche'] !== '' || $filtres['periode'] !== 'mois')
            <x-etat-vide icone="search" titre="Aucun paiement trouvé" texte="Aucun paiement ne correspond à ces filtres.">
                <x-bouton :href="route('paiements.index')" variante="secondaire" icone="x" data-liste-lien>Effacer les filtres</x-bouton>
            </x-etat-vide>
        @else
            <x-etat-vide icone="credit-card" titre="Aucun paiement ce mois-ci" texte="Les encaissements de la caisse et des créances apparaissent ici.">
                <x-bouton :href="route('creances.index')" icone="hand-coins">Voir les créances</x-bouton>
            </x-etat-vide>
        @endif
    </x-carte>
@else
    <x-tableau
        legende="Historique des paiements, du plus récent au plus ancien"
        :pagination="$paiements"
        :colonnes="[
            'numero' => 'Reçu',
            'tiers' => 'Client / fournisseur',
            'document' => 'Document',
            'mode' => 'Mode',
            'montant' => ['libelle' => 'Montant', 'alignement' => 'droite'],
            'actions' => ['libelle' => 'Actions', 'masque' => true, 'alignement' => 'droite'],
        ]"
    >
        @foreach ($paiements as $paiement)
            @php
                $document = $paiement->payable;
                $estVente = $paiement->payable_type === 'vente';
                $lienDocument = $estVente ? route('ventes.show', $document) : route('achats.show', $document);
            @endphp
            <x-tableau.ligne>
                <x-tableau.cellule libelle="Reçu" principale>
                    <a href="{{ route('paiements.recu', $paiement) }}" target="_blank" x-ouvrir-pdf class="font-medium text-texte hover:underline">{{ $paiement->numero }}</a>
                    <span class="block text-xs font-normal text-texte-doux">{{ $paiement->date_paiement->translatedFormat('j M Y, H:i') }} · {{ $paiement->utilisateur->nom }}</span>
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Client / fournisseur">
                    {{ ($estVente ? $document->client?->nom : $document->fournisseur?->nom) ?? '—' }}
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Document">
                    <a href="{{ $lienDocument }}" class="text-texte-doux hover:text-texte hover:underline">
                        {{ $estVente ? ($document->facture?->numero ?? $document->numero) : $document->numero }}
                    </a>
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Mode">
                    <span class="inline-flex flex-wrap items-center gap-1.5">
                        <x-badge :couleur="$paiement->mode->couleur()">{{ $paiement->mode->libelle() }}</x-badge>
                        @if ($paiement->reference)<span class="text-xs text-texte-doux">{{ $paiement->reference }}</span>@endif
                    </span>
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Montant" alignement="droite">
                    @if ($estVente)
                        <span class="font-semibold text-succes-texte">+ {{ format_ar($paiement->montant) }}</span>
                        <span class="sr-only">(encaissement)</span>
                    @else
                        <span class="font-semibold text-texte">− {{ format_ar($paiement->montant) }}</span>
                        <span class="sr-only">(décaissement)</span>
                    @endif
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Actions" alignement="droite">
                    <x-menu-actions :libelle="'Actions pour le paiement '.$paiement->numero">
                        <x-menu-actions.element icone="printer" :href="route('paiements.recu', $paiement)" target="_blank" x-ouvrir-pdf>Reçu (A5)</x-menu-actions.element>
                        <x-menu-actions.element icone="receipt" :href="route('paiements.recu', [$paiement, 'format' => 'ticket'])" target="_blank" x-ouvrir-pdf>Reçu 80 mm</x-menu-actions.element>
                        <x-menu-actions.element icone="download" :href="route('paiements.recu', [$paiement, 'telecharger' => 1])">Télécharger le reçu</x-menu-actions.element>
                        <x-menu-actions.element :icone="$estVente ? 'shopping-cart' : 'truck'" :href="$lienDocument">{{ $estVente ? 'Voir la vente' : 'Voir l\'achat' }}</x-menu-actions.element>
                    </x-menu-actions>
                </x-tableau.cellule>
            </x-tableau.ligne>
        @endforeach
    </x-tableau>
@endif
