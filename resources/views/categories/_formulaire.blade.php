{{--
    Modale unique de création / modification d'une catégorie.
    Ouverture : $dispatch('nouvelle-categorie') ou $dispatch('editer-categorie', { id, nom, description, actif }).
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
        description: @js((string) old('description', '')),
        actif: @js((bool) old('actif', true)),
        get action() { return this.cible ? @js(url('categories')) + '/' + this.cible : @js(route('categories.store')) },
        remplir(donnees = {}) {
            this.cible = donnees.id ?? null;
            this.nom = donnees.nom ?? '';
            this.description = donnees.description ?? '';
            this.actif = donnees.actif ?? true;
            this.$dispatch('ouvrir-modal', 'formulaire-categorie');
        },
        init() {
            @if ($errors->any() && $formulaire)
                this.$nextTick(() => this.$dispatch('ouvrir-modal', 'formulaire-categorie'));
            @endif
        },
    }"
    x-on:nouvelle-categorie.window="remplir()"
    x-on:editer-categorie.window="remplir($event.detail)"
>
    <x-modal id="formulaire-categorie" titre="Catégorie" titre-dynamique="cible ? 'Modifier la catégorie' : 'Nouvelle catégorie'">
        <form method="POST" x-bind:action="action" action="{{ route('categories.store') }}" x-chargement-envoi novalidate class="space-y-5">
            @csrf
            <input type="hidden" name="_method" value="PUT" x-bind:disabled="! cible" @disabled(! $cibleInitiale)>
            <input type="hidden" name="_formulaire" x-bind:value="cible ?? 'nouveau'" value="{{ $cibleInitiale ?? 'nouveau' }}">

            <x-champ nom="nom" label="Nom" x-model="nom" maxlength="100" placeholder="Ex. Plomberie" requis autofocus />
            <x-textarea nom="description" label="Description" x-model="description" :lignes="3" maxlength="255"
                        aide="Facultatif : aide à reconnaître la catégorie." />
            <div>
                <input type="hidden" name="actif" value="0">
                <x-case nom="actif" label="Catégorie active" x-model="actif" aide="Une catégorie inactive n'est plus proposée pour les nouveaux produits." />
            </div>

            <div class="-mx-6 -mb-6 flex flex-col-reverse gap-3 border-t border-bordure px-6 py-4 sm:flex-row sm:justify-end">
                <x-bouton type="button" variante="secondaire" x-on:click="$dispatch('fermer-modal', 'formulaire-categorie')">Annuler</x-bouton>
                <x-bouton icone="save">Enregistrer</x-bouton>
            </div>
        </form>
    </x-modal>
</div>
