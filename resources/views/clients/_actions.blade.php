{{--
    Menu « ⋯ » d'un client (liste et fiche).
    Client comptoir : seule la modification des coordonnées est proposée.
    Supprimer : seulement si le client n'a aucune vente.
--}}
@php
    $comptoir = $client->estComptoir();
    $sansVente = ! $comptoir && (isset($client->ventes_count) ? $client->ventes_count === 0 : ! $client->ventes()->exists());
@endphp
<x-menu-actions :libelle="'Actions pour '.$client->nom">
    <x-menu-actions.element icone="eye" :href="route('clients.show', $client)">Voir la fiche</x-menu-actions.element>
    <x-menu-actions.element icone="pencil"
        x-on:click="$dispatch('editer-client', {{ \Illuminate\Support\Js::from([
            'cible' => $client->id,
            'comptoir' => $comptoir,
            ...$client->only(['nom', 'telephone', 'email', 'adresse']),
            'plafond_credit' => $client->plafond_credit === null ? '' : (string) (float) $client->plafond_credit,
        ]) }})">
        Modifier
    </x-menu-actions.element>
    @unless ($comptoir)
        <x-menu-actions.element :icone="$client->actif ? 'ban' : 'check'"
            x-on:click="document.getElementById('statut-client-{{ $client->id }}').requestSubmit()">
            {{ $client->actif ? 'Désactiver' : 'Réactiver' }}
        </x-menu-actions.element>
    @endunless
    @if ($sansVente)
        <x-menu-actions.element icone="trash-2" danger x-on:click="$dispatch('ouvrir-modal', 'supprimer-client-{{ $client->id }}')">
            Supprimer
        </x-menu-actions.element>
    @endif
</x-menu-actions>
@unless ($comptoir)
    <form id="statut-client-{{ $client->id }}" method="POST" action="{{ route('clients.statut', $client) }}" class="hidden">
        @csrf
        @method('PATCH')
    </form>
@endunless
@if ($sansVente)
    <x-confirmation :id="'supprimer-client-'.$client->id" :action="route('clients.destroy', $client)"
                    :titre="'Supprimer « '.$client->nom.' » ?'"
                    message="Ce client n'a aucune vente. Il sera retiré de la liste (opération enregistrée dans le journal)." />
@endif
