{{--
    Panneau de création / modification d'un fournisseur.
    Ouverture : $dispatch('nouveau-fournisseur') ou $dispatch('editer-fournisseur', { cible, nom, contact, telephone, email, adresse }).
    Après une erreur de validation, le panneau se rouvre seul avec les valeurs saisies (old()).
--}}
@php
    $formulaire = old('_formulaire');
    $cibleInitiale = $formulaire && $formulaire !== 'nouveau' ? (int) $formulaire : null;
    $champs = ['nom', 'contact', 'telephone', 'email', 'adresse'];
@endphp

<div
    x-data="{
        cible: @js($cibleInitiale),
        @foreach ($champs as $champ) {{ $champ }}: @js((string) old($champ, '')), @endforeach
        get action() { return this.cible ? @js(url('fournisseurs')) + '/' + this.cible : @js(route('fournisseurs.store')) },
        remplir(donnees = {}) {
            this.cible = donnees.cible ?? null;
            @foreach ($champs as $champ) this.{{ $champ }} = donnees.{{ $champ }} ?? ''; @endforeach
            this.$dispatch('ouvrir-modal', 'panneau-fournisseur');
        },
        init() {
            @if ($errors->any() && $formulaire)
                this.$nextTick(() => this.$dispatch('ouvrir-modal', 'panneau-fournisseur'));
            @endif
        },
    }"
    x-on:nouveau-fournisseur.window="remplir()"
    x-on:editer-fournisseur.window="remplir($event.detail)"
>
    <x-panneau id="panneau-fournisseur" titre="Fournisseur" titre-dynamique="cible ? 'Modifier le fournisseur' : 'Nouveau fournisseur'">
        <form id="formulaire-fournisseur" method="POST" x-bind:action="action" action="{{ route('fournisseurs.store') }}"
              x-chargement-envoi novalidate class="space-y-5">
            @csrf
            <input type="hidden" name="_method" value="PUT" x-bind:disabled="! cible" @disabled(! $cibleInitiale)>
            <input type="hidden" name="_formulaire" x-bind:value="cible ?? 'nouveau'" value="{{ $cibleInitiale ?? 'nouveau' }}">

            <x-champ nom="nom" label="Nom ou raison sociale" x-model="nom" maxlength="150" placeholder="Ex. Ravinala Matériaux" requis />
            <x-champ nom="contact" label="Personne à contacter" x-model="contact" icone="user" maxlength="150" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-champ nom="telephone" label="Téléphone" type="tel" x-model="telephone" icone="phone" inputmode="tel" placeholder="020 22 123 45" requis />
                <x-champ nom="email" label="Email" type="email" x-model="email" icone="mail" />
            </div>
            <x-textarea nom="adresse" label="Adresse" x-model="adresse" :lignes="2" maxlength="255" />
        </form>

        <x-slot:pied>
            <x-bouton type="button" variante="secondaire" x-on:click="$dispatch('fermer-modal', 'panneau-fournisseur')">Annuler</x-bouton>
            <x-bouton form="formulaire-fournisseur" icone="save">Enregistrer</x-bouton>
        </x-slot:pied>
    </x-panneau>
</div>
