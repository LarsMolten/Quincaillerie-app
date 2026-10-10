{{--
    Menu « ⋯ » d'un compte. Sur son propre compte : ni désactivation ni suppression
    (les autres règles, dont le dernier administrateur, sont vérifiées par UtilisateurService).
--}}
@php
    $moi = $utilisateur->is(auth()->user());
@endphp
<x-menu-actions :libelle="'Actions pour '.$utilisateur->nom">
    <x-menu-actions.element icone="pencil"
        x-on:click="$dispatch('editer-utilisateur', {{ \Illuminate\Support\Js::from([
            'cible' => $utilisateur->id,
            'moi' => $moi,
            ...$utilisateur->only(['nom', 'email', 'telephone']),
            'role_id' => (string) $utilisateur->role_id,
        ]) }})">
        Modifier
    </x-menu-actions.element>
    <x-menu-actions.element icone="key-round"
        x-on:click="$dispatch('reinitialiser-mot-de-passe', {{ \Illuminate\Support\Js::from(['cible' => $utilisateur->id, 'nom' => $utilisateur->nom]) }})">
        Réinitialiser le mot de passe
    </x-menu-actions.element>
    @droit('journal.voir')
        <x-menu-actions.element icone="scroll-text" :href="route('journal.index', ['utilisateur' => $utilisateur->id])">Voir son activité</x-menu-actions.element>
    @enddroit
    @unless ($moi)
        <x-menu-actions.element :icone="$utilisateur->actif ? 'user-x' : 'user-check'"
            x-on:click="document.getElementById('statut-utilisateur-{{ $utilisateur->id }}').requestSubmit()">
            {{ $utilisateur->actif ? 'Désactiver' : 'Réactiver' }}
        </x-menu-actions.element>
        <x-menu-actions.element icone="trash-2" danger x-on:click="$dispatch('ouvrir-modal', 'supprimer-utilisateur-{{ $utilisateur->id }}')">
            Supprimer
        </x-menu-actions.element>
    @endunless
</x-menu-actions>
@unless ($moi)
    <form id="statut-utilisateur-{{ $utilisateur->id }}" method="POST" action="{{ route('utilisateurs.statut', $utilisateur) }}" class="hidden">
        @csrf
        @method('PATCH')
    </form>
    <x-confirmation :id="'supprimer-utilisateur-'.$utilisateur->id" :action="route('utilisateurs.destroy', $utilisateur)"
                    :titre="'Supprimer le compte de « '.$utilisateur->nom.' » ?'"
                    message="Il ne pourra plus se connecter. Ses ventes, achats et son activité restent dans l'historique (opération enregistrée dans le journal)." />
@endunless
