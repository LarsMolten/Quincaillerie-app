{{--
    Panneau de création / modification d'un compte.
    Ouverture : $dispatch('nouvel-utilisateur') ou $dispatch('editer-utilisateur', { cible, moi, nom, email, telephone, role_id }).
    Le mot de passe n'est saisi qu'à la création (ensuite : « Réinitialiser le mot de passe »).
    Son propre compte : le rôle est verrouillé (on ne peut pas se retirer ses droits par erreur).
--}}
@php
    $formulaire = old('_formulaire');
    $cibleInitiale = $formulaire && $formulaire !== 'nouveau' ? (int) $formulaire : null;
    $champs = ['nom', 'email', 'telephone', 'role_id'];
    $rolesListe = \App\Models\Role::orderBy('nom')->pluck('nom', 'id');
    $roleParDefaut = (string) \App\Models\Role::where('nom', 'Vendeur/Caissier')->value('id');
@endphp

<div
    x-data="{
        cible: @js($cibleInitiale),
        moi: @js($cibleInitiale === auth()->id()),
        @foreach ($champs as $champ) {{ $champ }}: @js((string) old($champ, '')), @endforeach
        motDePasse: '',
        visible: false,
        get action() { return this.cible ? @js(url('utilisateurs')) + '/' + this.cible : @js(route('utilisateurs.store')) },
        remplir(donnees = {}) {
            this.cible = donnees.cible ?? null;
            this.moi = donnees.moi ?? false;
            @foreach ($champs as $champ) this.{{ $champ }} = donnees.{{ $champ }} ?? ''; @endforeach
            if (! this.cible) { this.role_id = @js($roleParDefaut); }
            this.motDePasse = '';
            this.visible = false;
            this.$dispatch('ouvrir-modal', 'panneau-utilisateur');
        },
        // Mot de passe aléatoire de 12 caractères (lettres et chiffres, sans caractères ambigus), affiché pour être communiqué
        generer() {
            const alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            const tirage = crypto.getRandomValues(new Uint32Array(12));
            let mot = Array.from(tirage, (n) => alphabet[n % alphabet.length]).join('');
            if (! /[0-9]/.test(mot)) { mot = mot.slice(0, 11) + '7'; }
            this.motDePasse = mot;
            this.visible = true;
        },
        init() {
            @if ($errors->any() && $formulaire)
                this.$nextTick(() => this.$dispatch('ouvrir-modal', 'panneau-utilisateur'));
            @endif
        },
    }"
    x-on:nouvel-utilisateur.window="remplir()"
    x-on:editer-utilisateur.window="remplir($event.detail)"
>
    <x-panneau id="panneau-utilisateur" titre="Compte" titre-dynamique="cible ? 'Modifier le compte' : 'Nouveau compte'">
        <form id="formulaire-utilisateur" method="POST" x-bind:action="action" action="{{ route('utilisateurs.store') }}"
              x-chargement-envoi novalidate class="space-y-5">
            @csrf
            <input type="hidden" name="_method" value="PUT" x-bind:disabled="! cible" @disabled(! $cibleInitiale)>
            <input type="hidden" name="_formulaire" x-bind:value="cible ?? 'nouveau'" value="{{ $cibleInitiale ?? 'nouveau' }}">

            <x-champ nom="nom" label="Nom complet" x-model="nom" maxlength="150" placeholder="Ex. Rakotonirina Andry" autocomplete="off" requis />
            <x-champ nom="email" label="Adresse email" type="email" x-model="email" icone="mail" maxlength="150" autocomplete="off"
                     aide="Sert d'identifiant de connexion." requis />
            <x-champ nom="telephone" label="Téléphone" type="tel" x-model="telephone" icone="phone" inputmode="tel" placeholder="034 12 345 67" />

            <div>
                <x-select nom="role_id" label="Rôle" :options="$rolesListe" x-model="role_id" x-bind:disabled="moi" requis
                          aide="Les droits de chaque rôle se règlent dans « Rôles et droits »." />
                {{-- Son propre rôle : champ verrouillé, valeur envoyée telle quelle --}}
                <template x-if="moi"><input type="hidden" name="role_id" x-bind:value="role_id"></template>
                <p x-show="moi" x-cloak class="mt-1 text-sm text-texte-doux">Vous ne pouvez pas changer votre propre rôle.</p>
            </div>

            <template x-if="! cible">
                <div class="space-y-5 rounded-carte border border-bordure p-4">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-medium text-texte">Mot de passe initial</p>
                        <x-bouton type="button" variante="secondaire" taille="sm" icone="sparkles" x-on:click="generer()">Générer</x-bouton>
                    </div>
                    <x-champ nom="password" label="Mot de passe" type="password" x-model="motDePasse" x-bind:type="visible ? 'text' : 'password'"
                             autocomplete="new-password" aide="8 caractères au moins, avec des lettres et des chiffres." requis />
                    <x-champ nom="password_confirmation" label="Confirmation" type="password" x-model="motDePasse" x-bind:type="visible ? 'text' : 'password'"
                             x-show="! visible" autocomplete="new-password" />
                    <p x-show="visible" x-cloak class="flex items-start gap-2 rounded-controle bg-info-doux px-3 py-2 text-sm text-info-texte">
                        <x-icone nom="info" taille="size-4" class="mt-0.5" />
                        Notez ce mot de passe pour le communiquer à la personne : il ne sera plus affiché.
                    </p>
                    <x-interrupteur nom="actif" label="Compte actif" :actif="true" aide="Un compte désactivé ne peut pas se connecter." />
                </div>
            </template>
        </form>

        <x-slot:pied>
            <x-bouton type="button" variante="secondaire" x-on:click="$dispatch('fermer-modal', 'panneau-utilisateur')">Annuler</x-bouton>
            <x-bouton form="formulaire-utilisateur" icone="save">Enregistrer</x-bouton>
        </x-slot:pied>
    </x-panneau>
</div>
