{{--
    Panneau latéral de création / modification d'un produit (composant Alpine formulaireProduit).
    Ouverture : $dispatch('nouveau-produit') ou $dispatch('editer-produit', $produit->pourFormulaire()).
    Après une erreur de validation, le panneau se rouvre seul avec les valeurs saisies (old()).
--}}
@php
    $formulaire = old('_formulaire');
    $cibleInitiale = $formulaire && $formulaire !== 'nouveau' ? (int) $formulaire : null;
    $valeursInitiales = $formulaire ? [
        'cible' => $cibleInitiale,
        ...collect(['reference', 'code_barres', 'nom', 'description', 'categorie_id', 'unite_id', 'prix_achat', 'prix_vente', 'prix_gros', 'stock_minimum', 'stock_initial'])
            ->mapWithKeys(fn ($champ) => [$champ => (string) old($champ, '')])->all(),
        // La photo d'un produit existant reste affichée ; un nouveau fichier doit être resélectionné
        'photo' => $cibleInitiale && ($p = \App\Models\Produit::find($cibleInitiale))?->image
            ? route('produits.photo', [$p, 'v' => $p->updated_at?->timestamp]) : null,
        'stock_actuel' => $cibleInitiale ? \App\Models\Produit::find($cibleInitiale)?->pourFormulaire()['stock_actuel'] : null,
    ] : [];
    $optionsCategories = $categoriesFormulaire->mapWithKeys(fn ($c) => [$c->id => $c->nom.($c->actif ? '' : ' (inactive)')]);
    $optionsUnites = $unitesFormulaire->mapWithKeys(fn ($u) => [$u->id => "{$u->nom} ({$u->abreviation})"]);
@endphp

<div
    x-data="formulaireProduit({{ \Illuminate\Support\Js::from([
        'valeurs' => $valeursInitiales,
        'ouvrir' => $errors->any() && $formulaire,
        'urlProduits' => url('produits'),
        'urlCodeBarres' => route('produits.code-barres'),
    ]) }})"
    x-on:nouveau-produit.window="remplir()"
    x-on:editer-produit.window="remplir($event.detail)"
>
    <x-panneau id="panneau-produit" titre="Produit" titre-dynamique="cible ? 'Modifier le produit' : 'Nouveau produit'" largeur="max-w-2xl">
        <form id="formulaire-produit" method="POST" x-bind:action="action" action="{{ route('produits.store') }}"
              enctype="multipart/form-data" x-chargement-envoi novalidate class="space-y-8">
            @csrf
            <input type="hidden" name="_method" value="PUT" x-bind:disabled="! cible" @disabled(! $cibleInitiale)>
            <input type="hidden" name="_formulaire" x-bind:value="cible ?? 'nouveau'" value="{{ $cibleInitiale ?? 'nouveau' }}">
            <input type="hidden" name="retirer_photo" x-bind:value="retirerPhoto ? 1 : 0" value="0">

            @if ($errors->any() && $formulaire)
                {{-- Le navigateur ne peut pas conserver un fichier choisi après un rechargement --}}
                <p class="flex items-start gap-2 rounded-controle bg-alerte-doux px-3 py-2 text-sm text-alerte-texte">
                    <x-icone nom="info" taille="size-4" class="mt-0.5" />
                    Corrigez les champs signalés. Si vous aviez choisi une nouvelle photo, sélectionnez-la à nouveau.
                </p>
            @endif

            {{-- Identification --}}
            <fieldset class="space-y-4">
                <legend class="mb-3 text-sm font-semibold uppercase tracking-wider text-texte-doux">Identification</legend>
                <x-champ nom="nom" label="Nom du produit" x-model="nom" maxlength="150" placeholder="Ex. Ciment CEM II 42,5 – sac 50 kg" requis />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-champ nom="reference" label="Référence" x-model="reference" maxlength="50"
                             x-bind:placeholder="cible ? '' : 'Automatique : {{ $prochaineReference }}'"
                             aide="Laissez vide pour une référence automatique." />
                    <x-champ nom="code_barres" label="Code-barres" x-model="code_barres" icone="scan-barcode" inputmode="numeric"
                             maxlength="14" placeholder="Scannez ou saisissez" aide="EAN-13 ou code du fabricant.">
                        @droit('produits.creer')
                            <x-slot:action>
                                <button type="button" x-on:click="genererCodeBarres()" x-bind:disabled="generation"
                                        class="shrink-0 border-l border-bordure px-3 text-xs font-medium text-lien hover:bg-neutre-doux disabled:opacity-60"
                                        title="Générer un code EAN-13 interne">Générer</button>
                            </x-slot:action>
                        @enddroit
                    </x-champ>
                </div>
                <x-textarea nom="description" label="Description" x-model="description" :lignes="2" maxlength="1000" />
            </fieldset>

            {{-- Photo --}}
            <fieldset>
                <legend class="mb-3 text-sm font-semibold uppercase tracking-wider text-texte-doux">Photo</legend>
                <div class="flex items-center gap-4">
                    <div class="grid size-24 shrink-0 place-items-center overflow-hidden rounded-carte border border-bordure bg-fond text-texte-doux">
                        <template x-if="apercu"><img x-bind:src="apercu" alt="Aperçu de la photo" class="size-full object-cover"></template>
                        <template x-if="! apercu"><x-icone nom="package" taille="size-8" /></template>
                    </div>
                    <div class="space-y-2">
                        <label for="champ-photo"
                               class="inline-flex h-11 cursor-pointer items-center gap-2 rounded-controle border border-bordure-forte bg-surface px-4 text-sm font-medium text-texte transition-colors duration-150 hover:bg-fond focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-anneau">
                            <x-icone nom="upload" taille="size-4" />
                            <span x-text="apercu ? 'Changer la photo' : 'Choisir une photo'">Choisir une photo</span>
                            <input id="champ-photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" x-ref="photo"
                                   x-on:change="choisirPhoto($event)" class="sr-only" aria-describedby="champ-photo-aide @error('photo') champ-photo-erreur @enderror">
                        </label>
                        <button type="button" x-show="apercu" x-cloak x-on:click="enleverPhoto()"
                                class="ml-2 inline-flex h-11 items-center gap-1.5 rounded-controle px-3 text-sm font-medium text-danger-texte hover:bg-danger-doux">
                            <x-icone nom="trash-2" taille="size-4" /> Retirer
                        </button>
                        <p id="champ-photo-aide" class="text-sm text-texte-doux">JPEG, PNG ou WebP, 4 Mo maximum. Redimensionnée automatiquement.</p>
                        @error('photo')<p id="champ-photo-erreur" class="text-sm font-medium text-danger-texte">{{ $message }}</p>@enderror
                    </div>
                </div>
            </fieldset>

            {{-- Classement --}}
            <fieldset class="grid gap-4 sm:grid-cols-2">
                <legend class="mb-3 text-sm font-semibold uppercase tracking-wider text-texte-doux sm:col-span-2">Classement</legend>
                <x-select nom="categorie_id" label="Catégorie" :options="$optionsCategories" vide="Choisir une catégorie" x-model="categorie_id" requis />
                <x-select nom="unite_id" label="Unité de vente" :options="$optionsUnites" vide="Choisir une unité" x-model="unite_id" requis />
            </fieldset>

            {{-- Prix et marge --}}
            <fieldset class="space-y-4">
                <legend class="mb-3 text-sm font-semibold uppercase tracking-wider text-texte-doux">Prix</legend>
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-champ nom="prix_achat" label="Prix d'achat" type="number" min="0" step="1" inputmode="numeric" suffixe="Ar" montant x-model="prix_achat" requis />
                    <x-champ nom="prix_vente" label="Prix de vente" type="number" min="0" step="1" inputmode="numeric" suffixe="Ar" montant x-model="prix_vente" requis />
                    <x-champ nom="prix_gros" label="Prix de gros" type="number" min="0" step="1" inputmode="numeric" suffixe="Ar" montant x-model="prix_gros" aide="Facultatif." />
                </div>
                <div aria-live="polite"
                     class="flex items-start gap-3 rounded-controle border px-4 py-3 text-sm"
                     x-bind:class="! marge ? 'border-bordure bg-fond text-texte-doux' : (marge.negative ? 'border-danger bg-danger-doux text-danger-texte' : 'border-succes bg-succes-doux text-succes-texte')">
                    <x-icone nom="percent" taille="size-4" class="mt-0.5" />
                    <p>
                        <span class="font-medium">Marge : </span><span class="chiffres font-semibold" x-text="texteMarge"></span>
                        <template x-if="marge && marge.negative">
                            <span class="mt-1 block">Prix de vente inférieur au prix d'achat : le produit sera vendu à perte (enregistrement possible).</span>
                        </template>
                    </p>
                </div>
            </fieldset>

            {{-- Stock --}}
            <fieldset class="space-y-4">
                <legend class="mb-3 text-sm font-semibold uppercase tracking-wider text-texte-doux">Stock</legend>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-champ nom="stock_minimum" label="Stock minimum" type="number" min="0" step="any" inputmode="decimal" montant x-model="stock_minimum"
                             aide="Alerte « stock faible » à ce niveau." />
                    <template x-if="! cible">
                        <x-champ nom="stock_initial" label="Stock initial" type="number" min="0" step="any" inputmode="decimal" montant x-model="stock_initial"
                                 aide="Enregistré comme mouvement « Stock initial »." />
                    </template>
                    <template x-if="cible">
                        <div class="space-y-1.5">
                            <p class="text-sm font-medium text-texte">Stock actuel</p>
                            <p class="chiffres flex h-11 items-center rounded-controle border border-bordure bg-fond px-3 text-sm text-texte" x-text="stock_actuel"></p>
                            <p class="text-sm text-texte-doux">Se modifie par une entrée ou une sortie de stock.</p>
                        </div>
                    </template>
                </div>
            </fieldset>
        </form>

        <x-slot:pied>
            <x-bouton type="button" variante="secondaire" x-on:click="$dispatch('fermer-modal', 'panneau-produit')">Annuler</x-bouton>
            <x-bouton form="formulaire-produit" icone="save">Enregistrer</x-bouton>
        </x-slot:pied>
    </x-panneau>
</div>
