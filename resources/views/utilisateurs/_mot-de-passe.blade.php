{{--
    Modale de réinitialisation du mot de passe d'un compte (par l'administrateur).
    Ouverture : $dispatch('reinitialiser-mot-de-passe', { cible, nom }). Les autres sessions du compte sont fermées.
--}}
@php
    $cibleInitiale = old('_reinitialisation') ? (int) old('_reinitialisation') : null;
@endphp
<div
    x-data="{
        cible: @js($cibleInitiale),
        nom: @js($cibleInitiale ? \App\Models\Utilisateur::find($cibleInitiale)?->nom : ''),
        motDePasse: '',
        visible: false,
        ouvrir(donnees) {
            this.cible = donnees.cible;
            this.nom = donnees.nom;
            this.motDePasse = '';
            this.visible = false;
            this.$dispatch('ouvrir-modal', 'modale-mot-de-passe');
        },
        generer() {
            const alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            const tirage = crypto.getRandomValues(new Uint32Array(12));
            let mot = Array.from(tirage, (n) => alphabet[n % alphabet.length]).join('');
            if (! /[0-9]/.test(mot)) { mot = mot.slice(0, 11) + '7'; }
            this.motDePasse = mot;
            this.visible = true;
        },
        init() {
            @if ($cibleInitiale && $errors->has('password'))
                this.$nextTick(() => this.$dispatch('ouvrir-modal', 'modale-mot-de-passe'));
            @endif
        },
    }"
    x-on:reinitialiser-mot-de-passe.window="ouvrir($event.detail)"
>
    <x-modal id="modale-mot-de-passe" titre="Réinitialiser le mot de passe" titre-dynamique="'Mot de passe de ' + nom">
        <form id="formulaire-mot-de-passe" method="POST" x-bind:action="@js(url('utilisateurs')) + '/' + cible + '/mot-de-passe'"
              x-chargement-envoi novalidate class="space-y-4">
            @csrf
            @method('PUT')
            <input type="hidden" name="_reinitialisation" x-bind:value="cible">
            <p class="text-sm text-texte-doux">
                Le nouveau mot de passe remplace l'ancien immédiatement ; la personne est déconnectée de ses autres appareils.
            </p>
            <div class="flex justify-end">
                <x-bouton type="button" variante="secondaire" taille="sm" icone="sparkles" x-on:click="generer()">Générer</x-bouton>
            </div>
            <x-champ nom="password" id="reinitialisation-password" label="Nouveau mot de passe" type="password" x-model="motDePasse"
                     x-bind:type="visible ? 'text' : 'password'" autocomplete="new-password"
                     aide="8 caractères au moins, avec des lettres et des chiffres." requis />
            <x-champ nom="password_confirmation" id="reinitialisation-confirmation" label="Confirmation" type="password" x-model="motDePasse"
                     x-bind:type="visible ? 'text' : 'password'" x-show="! visible" autocomplete="new-password" />
            <p x-show="visible" x-cloak class="flex items-start gap-2 rounded-controle bg-info-doux px-3 py-2 text-sm text-info-texte">
                <x-icone nom="info" taille="size-4" class="mt-0.5" />
                Notez ce mot de passe pour le communiquer : il ne sera plus affiché.
            </p>
        </form>

        <x-slot:pied>
            <x-bouton type="button" variante="secondaire" x-on:click="$dispatch('fermer-modal', 'modale-mot-de-passe')">Annuler</x-bouton>
            <x-bouton form="formulaire-mot-de-passe" icone="key-round">Réinitialiser</x-bouton>
        </x-slot:pied>
    </x-modal>
</div>
