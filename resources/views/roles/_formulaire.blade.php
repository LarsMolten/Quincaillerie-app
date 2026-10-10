{{--
    Modale de création / modification d'un rôle.
    Ouverture : $dispatch('nouveau-role') ou $dispatch('editer-role', { cible, nom, description, verrouille }).
    Les rôles livrés gardent leur nom (verrouille) ; un nouveau rôle peut partir des droits d'un rôle existant.
--}}
@php
    $formulaire = old('_formulaire');
    $cibleInitiale = $formulaire && $formulaire !== 'nouveau' ? (int) $formulaire : null;
    $modeles = \App\Models\Role::orderBy('nom')->pluck('nom', 'id');
@endphp
<div
    x-data="{
        cible: @js($cibleInitiale),
        verrouille: @js($cibleInitiale && \App\Models\Role::find($cibleInitiale)?->estParDefaut()),
        nom: @js((string) old('nom', '')),
        description: @js((string) old('description', '')),
        get action() { return this.cible ? @js(url('roles')) + '/' + this.cible : @js(route('roles.store')) },
        remplir(donnees = {}) {
            this.cible = donnees.cible ?? null;
            this.verrouille = donnees.verrouille ?? false;
            this.nom = donnees.nom ?? '';
            this.description = donnees.description ?? '';
            this.$dispatch('ouvrir-modal', 'modale-role');
        },
        init() {
            @if ($errors->any() && $formulaire)
                this.$nextTick(() => this.$dispatch('ouvrir-modal', 'modale-role'));
            @endif
        },
    }"
    x-on:nouveau-role.window="remplir()"
    x-on:editer-role.window="remplir($event.detail)"
>
    <x-modal id="modale-role" titre="Rôle" titre-dynamique="cible ? 'Modifier le rôle' : 'Nouveau rôle'">
        <form id="formulaire-role" method="POST" x-bind:action="action" action="{{ route('roles.store') }}" x-chargement-envoi novalidate class="space-y-5">
            @csrf
            <input type="hidden" name="_method" value="PUT" x-bind:disabled="! cible" @disabled(! $cibleInitiale)>
            <input type="hidden" name="_formulaire" x-bind:value="cible ?? 'nouveau'" value="{{ $cibleInitiale ?? 'nouveau' }}">

            <x-champ nom="nom" id="role-nom" label="Nom du rôle" x-model="nom" maxlength="60" placeholder="Ex. Comptable" x-bind:readonly="verrouille" requis />
            <p x-show="verrouille" x-cloak class="-mt-3 text-sm text-texte-doux">Rôle livré avec l'application : son nom ne change pas.</p>
            <x-textarea nom="description" id="role-description" label="Description" x-model="description" :lignes="2" maxlength="255"
                        placeholder="Ce que ce rôle permet de faire" />
            <template x-if="! cible">
                <x-select nom="modele_id" id="role-modele" label="Partir des droits de" :options="$modeles" vide="Aucun (aucun droit)"
                          aide="Vous pourrez ensuite ajuster chaque droit." />
            </template>
        </form>

        <x-slot:pied>
            <x-bouton type="button" variante="secondaire" x-on:click="$dispatch('fermer-modal', 'modale-role')">Annuler</x-bouton>
            <x-bouton form="formulaire-role" icone="save">Enregistrer</x-bouton>
        </x-slot:pied>
    </x-modal>
</div>
