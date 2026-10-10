{{-- Tableau des mouvements de stock (rechargé seul lors d'un filtrage : en-tête X-Fragment) --}}
@if ($mouvements->isEmpty())
    <x-carte>
        @if ($filtresActifs)
            <x-etat-vide icone="search" titre="Aucun mouvement trouvé" texte="Aucun mouvement ne correspond à ces filtres.">
                <x-bouton :href="url()->current()" variante="secondaire" icone="x" data-liste-lien>Effacer les filtres</x-bouton>
            </x-etat-vide>
        @else
            <x-etat-vide icone="history" titre="Aucun mouvement" texte="Les achats, ventes, retours et ajustements apparaissent ici." />
        @endif
    </x-carte>
@else
    <x-tableau
        legende="Mouvements de stock, du plus récent au plus ancien"
        :pagination="$mouvements"
        :colonnes="[
            'date' => 'Date',
            'produit' => 'Produit',
            'type' => 'Type',
            'quantite' => ['libelle' => 'Quantité', 'alignement' => 'droite'],
            'stock' => ['libelle' => 'Stock avant → après', 'alignement' => 'droite'],
            'document' => 'Document',
            'utilisateur' => 'Par',
        ]"
    >
        @foreach ($mouvements as $mouvement)
            @php
                $entree = $mouvement->sens->value === 'entree';
                $unite = $mouvement->produit?->unite?->abreviation;
                $lien = $mouvement->lienDocument(auth()->user());
            @endphp
            <x-tableau.ligne>
                <x-tableau.cellule libelle="Date" class="whitespace-nowrap text-texte-doux">{{ $mouvement->created_at->translatedFormat('j M Y, H:i') }}</x-tableau.cellule>
                <x-tableau.cellule libelle="Produit" principale>
                    @if ($mouvement->produit && auth()->user()->can('produits.voir'))
                        <a href="{{ route('produits.show', $mouvement->produit) }}" class="font-medium text-texte hover:underline">{{ $mouvement->produit->nom }}</a>
                    @else
                        <span class="font-medium">{{ $mouvement->produit?->nom ?? '—' }}</span>
                    @endif
                    <span class="block text-xs font-normal text-texte-doux">{{ $mouvement->produit?->reference }}</span>
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Type"><x-badge :statut="$mouvement->type" /></x-tableau.cellule>
                <x-tableau.cellule libelle="Quantité" alignement="droite">
                    <span @class(['inline-flex items-center gap-1 font-semibold', 'text-succes-texte' => $entree, 'text-danger-texte' => ! $entree])>
                        <x-icone :nom="$entree ? 'arrow-up-right' : 'arrow-down-right'" taille="size-4" />
                        {{ $entree ? '+' : '−' }}{{ format_quantite($mouvement->quantite, $unite) }}
                    </span>
                    <span class="sr-only">({{ $entree ? 'entrée' : 'sortie' }})</span>
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Stock avant → après" alignement="droite" class="whitespace-nowrap text-texte-doux">
                    {{ format_quantite($mouvement->stock_avant) }} → <span class="font-medium text-texte">{{ format_quantite($mouvement->stock_apres) }}</span>
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Document">
                    @if ($lien && $lien['url'])
                        <a href="{{ $lien['url'] }}" class="font-medium whitespace-nowrap text-lien hover:underline">{{ $lien['libelle'] }}</a>
                    @elseif ($lien)
                        <span class="whitespace-nowrap">{{ $lien['libelle'] }}</span>
                    @else
                        <span class="text-texte-doux">Saisie manuelle</span>
                    @endif
                    @if ($mouvement->motif)
                        <span class="block max-w-64 truncate text-xs text-texte-doux" title="{{ $mouvement->motif }}">{{ $mouvement->motif }}</span>
                    @endif
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Par" class="text-texte-doux">{{ $mouvement->utilisateur?->nom }}</x-tableau.cellule>
            </x-tableau.ligne>
        @endforeach
    </x-tableau>
@endif
