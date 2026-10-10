{{-- Tableau des retours (rechargé seul lors d'un filtrage : en-tête X-Fragment) --}}
@php
    $client = $type === \App\Enums\TypeRetour::Client;
@endphp

@if ($retours->isEmpty())
    <x-carte>
        @if ($filtres['statut'] || $filtres['recherche'] !== '' || $filtres['periode'] !== 'tout')
            <x-etat-vide icone="search" titre="Aucun retour trouvé" texte="Aucun retour ne correspond à ces filtres.">
                <x-bouton :href="route($client ? 'retours.clients' : 'retours.fournisseurs')" variante="secondaire" icone="x" data-liste-lien>Effacer les filtres</x-bouton>
            </x-etat-vide>
        @else
            <x-etat-vide icone="undo-2" :titre="$client ? 'Aucun retour client' : 'Aucun retour fournisseur'"
                         :texte="$client ? 'Un retour corrige une vente validée sans modifier sa facture.' : 'Un retour renvoie au fournisseur des produits d’un achat validé.'">
                <x-bouton :href="route('retours.create', ['type' => $type->value])" icone="plus">Nouveau retour</x-bouton>
            </x-etat-vide>
        @endif
    </x-carte>
@else
    <x-tableau
        :legende="$client ? 'Liste des retours clients' : 'Liste des retours fournisseurs'"
        :pagination="$retours"
        :colonnes="[
            'numero' => 'Retour',
            'document' => $client ? 'Vente / facture' : 'Achat',
            'tiers' => $client ? 'Client' : 'Fournisseur',
            'motif' => 'Motif',
            'total' => ['libelle' => 'Montant', 'alignement' => 'droite'],
            'statut' => 'Statut',
            'actions' => ['libelle' => 'Actions', 'masque' => true, 'alignement' => 'droite'],
        ]"
    >
        @foreach ($retours as $retour)
            @php
                $annule = $retour->statut === \App\Enums\StatutRetour::Annule;
                $document = $client ? $retour->vente : $retour->achat;
            @endphp
            <x-tableau.ligne @class(['opacity-70' => $annule])>
                <x-tableau.cellule libelle="Retour" principale>
                    <a href="{{ route('retours.show', $retour) }}" class="font-medium text-texte hover:underline">{{ $retour->numero }}</a>
                    <span class="block text-xs font-normal text-texte-doux">{{ $retour->date_retour->translatedFormat('j M Y') }}</span>
                </x-tableau.cellule>
                <x-tableau.cellule :libelle="$client ? 'Vente / facture' : 'Achat'">
                    <a href="{{ $client ? route('ventes.show', $document) : route('achats.show', $document) }}" class="text-texte-doux hover:text-texte hover:underline">
                        {{ $client ? ($document->facture?->numero ?? $document->numero) : $document->numero }}
                    </a>
                </x-tableau.cellule>
                <x-tableau.cellule :libelle="$client ? 'Client' : 'Fournisseur'">{{ ($client ? $document->client?->nom : $document->fournisseur?->nom) ?? '—' }}</x-tableau.cellule>
                <x-tableau.cellule libelle="Motif" class="max-w-56 truncate text-texte-doux" :title="$retour->motif">{{ $retour->motif }}</x-tableau.cellule>
                <x-tableau.cellule libelle="Montant" alignement="droite">
                    <span class="font-semibold">{{ format_ar($retour->total) }}</span>
                    @if ((float) $retour->montant_rembourse > 0)
                        <span class="block text-xs font-normal text-texte-doux">dont {{ format_ar($retour->montant_rembourse) }} remboursés</span>
                    @endif
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Statut"><x-badge :statut="$retour->statut" /></x-tableau.cellule>
                <x-tableau.cellule libelle="Actions" alignement="droite">
                    <x-menu-actions :libelle="'Actions pour le retour '.$retour->numero">
                        <x-menu-actions.element icone="eye" :href="route('retours.show', $retour)">Voir le retour</x-menu-actions.element>
                        <x-menu-actions.element icone="printer" :href="route('retours.bon', $retour)" target="_blank" x-ouvrir-pdf>Bon de retour</x-menu-actions.element>
                        <x-menu-actions.element icone="download" :href="route('retours.bon', [$retour, 'telecharger' => 1])">Télécharger le bon</x-menu-actions.element>
                    </x-menu-actions>
                </x-tableau.cellule>
            </x-tableau.ligne>
        @endforeach
    </x-tableau>
@endif
