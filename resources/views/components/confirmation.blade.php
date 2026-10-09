{{--
    Modale de confirmation d'une action risquée (suppression, annulation) : remplace confirm().
    Le bouton de confirmation envoie un formulaire (CSRF + méthode) ; « Annuler » reçoit le focus.

    Props :
    - id, action (URL du formulaire)
    - methode          : DELETE (défaut) | PATCH | PUT | POST
    - titre, message   : (le slot peut remplacer le message)
    - libelleConfirmer : texte du bouton (défaut "Supprimer")
    - variante         : danger (défaut) | primaire
    - icone            : icône du bouton (défaut "trash-2")

    Exemple :
    <x-confirmation id="annuler-vente-12" :action="route('ventes.annuler', 12)" methode="PATCH"
                    titre="Annuler la vente VTE-2026-00012 ?" libelle-confirmer="Annuler la vente" icone="ban">
        Le stock sera réintégré. Cette action est enregistrée dans le journal.
    </x-confirmation>
--}}
@props([
    'id',
    'action',
    'methode' => 'DELETE',
    'titre' => 'Confirmer la suppression',
    'message' => null,
    'libelleConfirmer' => 'Supprimer',
    'variante' => 'danger',
    'icone' => 'trash-2',
])

<x-modal :id="$id" :titre="$titre" taille="sm" role="alertdialog" {{ $attributes }}>
    <p class="text-sm text-texte-doux">{{ $message ?? $slot }}</p>

    <x-slot:pied>
        <x-bouton variante="secondaire" type="button" autofocus x-on:click="$dispatch('fermer-modal', '{{ $id }}')">
            Annuler
        </x-bouton>
        <form method="POST" action="{{ $action }}" x-chargement-envoi>
            @csrf
            @if (strtoupper($methode) !== 'POST')
                @method(strtoupper($methode))
            @endif
            <x-bouton :variante="$variante" :icone="$icone" class="w-full">{{ $libelleConfirmer }}</x-bouton>
        </form>
    </x-slot:pied>
</x-modal>
