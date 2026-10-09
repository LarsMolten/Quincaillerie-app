{{-- Menu « ⋯ » d'un produit (liste et grille) --}}
<x-menu-actions :libelle="'Actions pour '.$produit->nom">
    <x-menu-actions.element icone="eye" :href="route('produits.show', $produit)">Voir la fiche</x-menu-actions.element>
    @droit('produits.modifier')
        <x-menu-actions.element icone="pencil" x-on:click="$dispatch('editer-produit', {{ \Illuminate\Support\Js::from($produit->pourFormulaire()) }})">
            Modifier
        </x-menu-actions.element>
    @enddroit
    <x-menu-actions.element icone="printer" x-on:click="$dispatch('etiquettes-produits', { produits: ['{{ $produit->id }}'] })">
        Imprimer des étiquettes
    </x-menu-actions.element>
    @droit('produits.desactiver')
        <x-menu-actions.element :icone="$produit->actif ? 'ban' : 'check'" :danger="$produit->actif"
            x-on:click="document.getElementById('statut-produit-{{ $produit->id }}').requestSubmit()">
            {{ $produit->actif ? 'Désactiver' : 'Réactiver' }}
        </x-menu-actions.element>
    @enddroit
</x-menu-actions>
@droit('produits.desactiver')
    <form id="statut-produit-{{ $produit->id }}" method="POST" action="{{ route('produits.statut', $produit) }}" class="hidden">
        @csrf
        @method('PATCH')
    </form>
@enddroit
