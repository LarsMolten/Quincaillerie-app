{{-- Liste des produits, en tableau ou en grille (rechargée seule lors d'une recherche : en-tête X-Fragment) --}}
@php
    $filtresActifs = $filtres['recherche'] !== '' || $filtres['categorie'] || $filtres['stock'] || $filtres['statut'] !== 'actifs';
@endphp

@if ($produits->isEmpty())
    <x-carte>
        @if ($filtresActifs)
            <x-etat-vide icone="search" titre="Aucun produit trouvé"
                         :texte="$filtres['recherche'] !== '' ? 'Aucun résultat pour « '.$filtres['recherche'].' » avec ces filtres.' : 'Aucun produit ne correspond à ces filtres.'">
                <x-bouton :href="route('produits.index')" variante="secondaire" icone="x" data-liste-lien>Effacer les filtres</x-bouton>
            </x-etat-vide>
        @else
            <x-etat-vide icone="package" titre="Aucun produit" texte="Ajoutez votre premier article au catalogue.">
                @droit('produits.creer')
                    <x-bouton type="button" icone="plus" x-data x-on:click="$dispatch('nouveau-produit')">Ajouter un produit</x-bouton>
                @enddroit
            </x-etat-vide>
        @endif
    </x-carte>
@elseif ($vue === 'grille')
    <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" role="list">
        @foreach ($produits as $produit)
            <li>
                <article @class([
                    'group relative flex h-full flex-col overflow-hidden rounded-carte border border-bordure bg-surface shadow-doux transition-shadow duration-150 hover:shadow-eleve',
                    'opacity-75' => ! $produit->actif,
                ])>
                    <div class="aspect-[4/3] bg-fond">
                        @if ($produit->image)
                            <img src="{{ route('produits.photo', [$produit, 'v' => $produit->updated_at?->timestamp]) }}" alt="" loading="lazy" decoding="async"
                                 class="size-full object-cover">
                        @else
                            <div class="grid size-full place-items-center text-texte-doux"><x-icone nom="package" taille="size-10" /></div>
                        @endif
                    </div>
                    <div class="flex flex-1 flex-col gap-2 p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-xs text-texte-doux">{{ $produit->reference }} · {{ $produit->categorie->nom }}</p>
                                <h2 class="line-clamp-2 font-semibold leading-snug text-texte">
                                    <a href="{{ route('produits.show', $produit) }}" class="rounded after:absolute after:inset-0 hover:underline">{{ $produit->nom }}</a>
                                </h2>
                            </div>
                            <div class="relative z-10">@include('produits._actions')</div>
                        </div>
                        <div class="mt-auto flex items-end justify-between gap-2 pt-2">
                            <p class="chiffres text-lg font-semibold text-texte">{{ format_ar($produit->prix_vente) }}</p>
                            <div class="flex flex-col items-end gap-1">
                                <x-badge :statut="$produit->etat_stock">{{ format_quantite($produit->stock_actuel, $produit->unite->abreviation) }}</x-badge>
                                @unless ($produit->actif)<x-badge couleur="neutre" :point="false">Inactif</x-badge>@endunless
                            </div>
                        </div>
                    </div>
                </article>
            </li>
        @endforeach
    </ul>
    @if ($produits->hasPages())
        <x-carte class="mt-5" :padding="false"><div class="px-4 py-3">{{ $produits->links() }}</div></x-carte>
    @endif
@else
    <x-tableau
        legende="Liste des produits"
        :pagination="$produits"
        :colonnes="[
            'selection' => ['libelle' => 'Sélection', 'masque' => true],
            'nom' => ['libelle' => 'Produit', 'triable' => true],
            'categorie' => ['libelle' => 'Catégorie', 'triable' => true],
            'prix_vente' => ['libelle' => 'Prix de vente', 'triable' => true, 'alignement' => 'droite'],
            'stock_actuel' => ['libelle' => 'Stock', 'triable' => true, 'alignement' => 'droite'],
            'etat' => 'État',
            'actions' => ['libelle' => 'Actions', 'masque' => true, 'alignement' => 'droite'],
        ]"
    >
        @foreach ($produits as $produit)
            <x-tableau.ligne @class(['opacity-75' => ! $produit->actif])>
                <x-tableau.cellule libelle="Sélection" class="w-10">
                    <input type="checkbox" value="{{ $produit->id }}" data-selection
                           aria-label="Sélectionner {{ $produit->nom }} pour les étiquettes"
                           class="size-5 cursor-pointer rounded accent-primaire">
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Produit" principale>
                    <div class="flex items-center gap-3">
                        <span class="grid size-10 shrink-0 place-items-center overflow-hidden rounded-lg bg-fond text-texte-doux">
                            @if ($produit->image)
                                <img src="{{ route('produits.photo', [$produit, 'v' => $produit->updated_at?->timestamp]) }}" alt="" loading="lazy" decoding="async" class="size-full object-cover">
                            @else
                                <x-icone nom="package" taille="size-5" />
                            @endif
                        </span>
                        <span class="min-w-0">
                            <a href="{{ route('produits.show', $produit) }}" class="block truncate font-medium text-texte hover:underline">{{ $produit->nom }}</a>
                            <span class="block text-xs font-normal text-texte-doux">{{ $produit->reference }}@if ($produit->code_barres) · {{ $produit->code_barres }}@endif</span>
                        </span>
                    </div>
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Catégorie" class="text-texte-doux">{{ $produit->categorie->nom }}</x-tableau.cellule>
                <x-tableau.cellule libelle="Prix de vente" alignement="droite">{{ format_ar($produit->prix_vente) }}</x-tableau.cellule>
                <x-tableau.cellule libelle="Stock" alignement="droite">{{ format_quantite($produit->stock_actuel, $produit->unite->abreviation) }}</x-tableau.cellule>
                <x-tableau.cellule libelle="État">
                    <span class="inline-flex flex-wrap gap-1.5">
                        <x-badge :statut="$produit->etat_stock" />
                        @unless ($produit->actif)<x-badge couleur="neutre" :point="false">Inactif</x-badge>@endunless
                    </span>
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Actions" alignement="droite">@include('produits._actions')</x-tableau.cellule>
            </x-tableau.ligne>
        @endforeach
    </x-tableau>
@endif
