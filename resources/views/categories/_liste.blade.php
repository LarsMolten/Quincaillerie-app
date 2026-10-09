{{-- Liste des catégories en cartes (rechargée seule lors d'une recherche : en-tête X-Fragment) --}}
@if ($categories->isEmpty())
    <x-carte>
        @if ($recherche !== '' || $statut)
            <x-etat-vide icone="search" titre="Aucune catégorie trouvée"
                         :texte="$recherche !== '' ? 'Aucun résultat pour « '.$recherche.' ».' : 'Aucune catégorie ne correspond à ce filtre.'">
                <x-bouton :href="route('categories.index')" variante="secondaire" icone="x" data-liste-lien>Effacer la recherche</x-bouton>
            </x-etat-vide>
        @else
            <x-etat-vide icone="tags" titre="Aucune catégorie" texte="Créez votre première catégorie pour classer vos produits.">
                <x-bouton type="button" icone="plus" x-data x-on:click="$dispatch('nouvelle-categorie')">Ajouter une catégorie</x-bouton>
            </x-etat-vide>
        @endif
    </x-carte>
@else
    <ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" role="list">
        @foreach ($categories as $categorie)
            <li>
                <article @class([
                    'flex h-full flex-col gap-3 rounded-carte border border-bordure bg-surface p-carte shadow-doux transition-shadow duration-150 hover:shadow-eleve',
                    'opacity-80' => ! $categorie->actif,
                ])>
                    <header class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="truncate text-base font-semibold text-texte">{{ $categorie->nom }}</h2>
                            <x-badge :couleur="$categorie->actif ? 'succes' : 'neutre'" class="mt-1.5">
                                {{ $categorie->actif ? 'Active' : 'Inactive' }}
                            </x-badge>
                        </div>

                        <x-menu-actions :libelle="'Actions pour la catégorie '.$categorie->nom">
                            <x-menu-actions.element icone="pencil"
                                x-on:click="$dispatch('editer-categorie', {{ \Illuminate\Support\Js::from(['id' => $categorie->id, 'nom' => $categorie->nom, 'description' => $categorie->description, 'actif' => $categorie->actif]) }})">
                                Modifier
                            </x-menu-actions.element>
                            <x-menu-actions.element :icone="$categorie->actif ? 'ban' : 'check'"
                                x-on:click="document.getElementById('statut-categorie-{{ $categorie->id }}').requestSubmit()">
                                {{ $categorie->actif ? 'Désactiver' : 'Réactiver' }}
                            </x-menu-actions.element>
                            @if ($categorie->produits_count === 0)
                                <x-menu-actions.element icone="trash-2" danger
                                    x-on:click="$dispatch('ouvrir-modal', 'supprimer-categorie-{{ $categorie->id }}')">
                                    Supprimer
                                </x-menu-actions.element>
                            @endif
                        </x-menu-actions>
                    </header>

                    <p @class(['line-clamp-2 flex-1 text-sm', 'text-texte-doux' => $categorie->description, 'italic text-texte-doux' => ! $categorie->description])>
                        {{ $categorie->description ?: 'Aucune description' }}
                    </p>

                    <footer class="flex items-center justify-between gap-3 border-t border-bordure pt-3 text-sm">
                        <span class="inline-flex items-center gap-2 text-texte-doux">
                            <x-icone nom="package" taille="size-4" />
                            <span><span class="chiffres font-semibold text-texte">{{ $categorie->produits_count }}</span>
                                produit{{ $categorie->produits_count > 1 ? 's' : '' }}</span>
                        </span>
                        @if ($categorie->produits_count > 0)
                            <span class="inline-flex items-center gap-1 text-xs text-texte-doux" title="Une catégorie utilisée ne peut pas être supprimée, seulement désactivée.">
                                <x-icone nom="lock" taille="size-3.5" /> Utilisée
                            </span>
                        @endif
                    </footer>
                </article>

                <form id="statut-categorie-{{ $categorie->id }}" method="POST" action="{{ route('categories.statut', $categorie) }}" class="hidden">
                    @csrf
                    @method('PATCH')
                </form>

                @if ($categorie->produits_count === 0)
                    <x-confirmation :id="'supprimer-categorie-'.$categorie->id" :action="route('categories.destroy', $categorie)"
                                    :titre="'Supprimer « '.$categorie->nom.' » ?'"
                                    message="La catégorie ne contient aucun produit. Elle sera retirée de la liste (opération enregistrée dans le journal)." />
                @endif
            </li>
        @endforeach
    </ul>

    @if ($categories->hasPages())
        <x-carte class="mt-5" :padding="false">
            <div class="px-4 py-3">{{ $categories->links() }}</div>
        </x-carte>
    @endif
@endif
