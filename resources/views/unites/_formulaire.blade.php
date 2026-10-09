{{--
    Modale unique de création / modification d'une unité.
    Ouverture : $dispatch('nouvelle-unite') ou $dispatch('editer-unite', { id, nom, abreviation }).
    Après une erreur de validation, la modale se rouvre seule avec les valeurs saisies (old()).
--}}
@php
    $formulaire = old('_formulaire');
    $cibleInitiale = $formulaire && $formulaire !== 'nouveau' ? (int) $formulaire : null;
@endphp

<div
    x-data="{
        cible: @js($cibleInitiale),
        nom: @js((string) old('nom', '')),
        abreviation: @js((string) old('abreviation', '')),
        get action() { return this.cible ? @js(url('unites')) + '/' + this.cible : @js(route('unites.store')) },
        remplir(donnees = {}) {
            this.cible = donnees.id ?? null;
            this.nom = donnees.nom ?? '';
            this.abreviation = donnees.abreviation ?? '';
            this.$dispatch('ouvrir-modal', 'formulaire-unite');
        },
        init() {
            @if ($errors->any() && $formulaire)
                this.$nextTick(() => this.$dispatch('ouvrir-modal', 'formulaire-unite'));
            @endif
        },
    }"
    x-on:nouvelle-unite.window="remplir()"
    x-on:editer-unite.window="remplir($event.detail)"
>
    <x-modal id="formulaire-unite" titre="Unité" titre-dynamique="cible ? 'Modifier l’unité' : 'Nouvelle unité'" taille="sm">
        <form method="POST" x-bind:action="action" action="{{ route('unites.store') }}" x-chargement-envoi novalidate class="space-y-5">
            @csrf
            <input type="hidden" name="_method" value="PUT" x-bind:disabled="! cible" @disabled(! $cibleInitiale)>
            <input type="hidden" name="_formulaire" x-bind:value="cible ?? 'nouveau'" value="{{ $cibleInitiale ?? 'nouveau' }}">

            <x-champ nom="nom" label="Nom" x-model="nom" maxlength="50" placeholder="Ex. kilogramme" requis autofocus />
            <x-champ nom="abreviation" label="Abréviation" x-model="abreviation" maxlength="20" placeholder="Ex. kg"
                     aide="Affichée après les quantités : 2,5 kg, 10 pce…" requis />

            <div class="-mx-6 -mb-6 flex flex-col-reverse gap-3 border-t border-bordure px-6 py-4 sm:flex-row sm:justify-end">
                <x-bouton type="button" variante="secondaire" x-on:click="$dispatch('fermer-modal', 'formulaire-unite')">Annuler</x-bouton>
                <x-bouton icone="save">Enregistrer</x-bouton>
            </div>
        </form>
    </x-modal>
</div>
