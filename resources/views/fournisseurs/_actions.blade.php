{{-- Menu « ⋯ » d'un fournisseur (liste et fiche) ; Supprimer seulement s'il n'a aucun achat --}}
@php($sansAchat = isset($fournisseur->achats_count) ? $fournisseur->achats_count === 0 : ! $fournisseur->achats()->exists())
<x-menu-actions :libelle="'Actions pour '.$fournisseur->nom">
    <x-menu-actions.element icone="eye" :href="route('fournisseurs.show', $fournisseur)">Voir la fiche</x-menu-actions.element>
    <x-menu-actions.element icone="pencil"
        x-on:click="$dispatch('editer-fournisseur', {{ \Illuminate\Support\Js::from(['cible' => $fournisseur->id, ...$fournisseur->only(['nom', 'contact', 'telephone', 'email', 'adresse'])]) }})">
        Modifier
    </x-menu-actions.element>
    <x-menu-actions.element :icone="$fournisseur->actif ? 'ban' : 'check'"
        x-on:click="document.getElementById('statut-fournisseur-{{ $fournisseur->id }}').requestSubmit()">
        {{ $fournisseur->actif ? 'Désactiver' : 'Réactiver' }}
    </x-menu-actions.element>
    @if ($sansAchat)
        <x-menu-actions.element icone="trash-2" danger x-on:click="$dispatch('ouvrir-modal', 'supprimer-fournisseur-{{ $fournisseur->id }}')">
            Supprimer
        </x-menu-actions.element>
    @endif
</x-menu-actions>
<form id="statut-fournisseur-{{ $fournisseur->id }}" method="POST" action="{{ route('fournisseurs.statut', $fournisseur) }}" class="hidden">
    @csrf
    @method('PATCH')
</form>
@if ($sansAchat)
    <x-confirmation :id="'supprimer-fournisseur-'.$fournisseur->id" :action="route('fournisseurs.destroy', $fournisseur)"
                    :titre="'Supprimer « '.$fournisseur->nom.' » ?'"
                    message="Ce fournisseur n'a aucun achat. Il sera retiré de la liste (opération enregistrée dans le journal)." />
@endif
