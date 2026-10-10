{{--
    Modale d'ajustement manuel (Entrées : ajustement positif ; Sorties : perte ou ajustement négatif).
    Variable : $types (list<TypeMouvementStock>). Composant Alpine « ajustementStock ».
--}}
@php
    $config = [
        'urls' => ['produits' => route('stock.produits'), 'enregistrer' => route('stock.ajustements.store')],
        'types' => collect($types)->map(fn ($t) => ['valeur' => $t->value, 'libelle' => $t->libelle()])->all(),
    ];
    $classeChamp = 'h-11 w-full rounded-controle border border-bordure-forte bg-surface px-3 text-sm text-texte aria-invalid:border-danger';
@endphp

<div x-data="ajustementStock({{ \Illuminate\Support\Js::from($config) }})">
    <x-modal id="modale-ajustement" taille="sm" :titre="count($types) === 1 ? 'Ajustement d’entrée' : 'Perte ou ajustement de sortie'"
             description="Le stock est modifié immédiatement ; l’opération est enregistrée dans le journal.">
        <form id="formulaire-ajustement" class="space-y-4" novalidate x-on:submit.prevent="enregistrer()">
            {{-- Produit --}}
            <div>
                <label for="ajustement-produit" class="mb-1.5 block text-sm font-medium">Produit <span class="text-danger-texte" aria-hidden="true">*</span></label>
                <template x-if="produit">
                    <div class="flex items-center justify-between gap-3 rounded-controle border border-primaire bg-primaire-doux/40 px-3 py-2">
                        <div class="min-w-0">
                            <p class="truncate font-medium" x-text="produit.nom"></p>
                            <p class="chiffres text-xs text-texte-doux" x-text="produit.reference + ' · en stock : ' + qte(produit.stock, produit.unite)"></p>
                        </div>
                        <x-bouton type="button" variante="secondaire" taille="sm" x-on:click="produit = null; $nextTick(() => document.getElementById('ajustement-produit')?.focus())">Changer</x-bouton>
                    </div>
                </template>
                <div x-show="! produit" class="relative">
                    <input id="ajustement-produit" type="search" autocomplete="off" x-model="recherche" x-on:input="rechercherAvecDelai()"
                           placeholder="Nom, référence ou code-barres…" x-bind:aria-invalid="(!! erreurs.produit_id).toString()"
                           aria-describedby="ajustement-produit-erreur" class="{{ $classeChamp }}">
                    <ul x-show="resultats.length > 0" x-cloak class="absolute inset-x-0 top-full z-20 mt-1 max-h-64 overflow-auto rounded-controle border border-bordure bg-surface shadow-doux" role="listbox">
                        <template x-for="p in resultats" x-bind:key="p.id">
                            <li role="option">
                                <button type="button" x-on:click="choisir(p)" class="flex w-full items-center justify-between gap-3 px-3 py-2.5 text-left text-sm hover:bg-fond focus-visible:bg-fond">
                                    <span class="min-w-0"><span class="block truncate font-medium" x-text="p.nom"></span><span class="text-xs text-texte-doux" x-text="p.reference"></span></span>
                                    <span class="chiffres shrink-0 text-xs text-texte-doux" x-text="qte(p.stock, p.unite)"></span>
                                </button>
                            </li>
                        </template>
                    </ul>
                </div>
                <p id="ajustement-produit-erreur" x-show="erreurs.produit_id" x-cloak class="mt-1.5 text-sm text-danger-texte" x-text="erreurs.produit_id?.[0]"></p>
            </div>

            {{-- Type --}}
            @if (count($types) > 1)
                <fieldset>
                    <legend class="mb-1.5 text-sm font-medium">Type</legend>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach ($types as $type)
                            <label class="flex h-11 cursor-pointer items-center gap-2 rounded-controle border border-bordure-forte px-3 text-sm has-checked:border-primaire has-checked:bg-primaire-doux">
                                <input type="radio" name="type" value="{{ $type->value }}" x-model="type" class="accent-primaire">
                                {{ $type->libelle() }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endif

            {{-- Quantité --}}
            <div>
                <label for="ajustement-quantite" class="mb-1.5 block text-sm font-medium">Quantité <span class="text-danger-texte" aria-hidden="true">*</span></label>
                <div class="relative">
                    <input id="ajustement-quantite" type="number" min="0" step="any" inputmode="decimal" x-model="quantite"
                           x-bind:aria-invalid="(!! erreurs.quantite).toString()" aria-describedby="ajustement-quantite-erreur"
                           class="{{ $classeChamp }} chiffres pr-14 text-right">
                    <span class="pointer-events-none absolute inset-y-0 right-3 grid place-items-center text-sm text-texte-doux" x-text="produit?.unite ?? ''"></span>
                </div>
                <p id="ajustement-quantite-erreur" x-show="erreurs.quantite" x-cloak class="mt-1.5 text-sm text-danger-texte" x-text="erreurs.quantite?.[0]"></p>
            </div>

            {{-- Motif --}}
            <div>
                <label for="ajustement-motif" class="mb-1.5 block text-sm font-medium">Motif <span class="text-danger-texte" aria-hidden="true">*</span></label>
                <textarea id="ajustement-motif" rows="2" maxlength="255" x-model="motif" placeholder="Ex. sac percé, casse en rayon, erreur de saisie…"
                          x-bind:aria-invalid="(!! erreurs.motif).toString()" aria-describedby="ajustement-motif-erreur"
                          class="w-full rounded-controle border border-bordure-forte bg-surface px-3 py-2 text-sm text-texte aria-invalid:border-danger"></textarea>
                <p id="ajustement-motif-erreur" x-show="erreurs.motif" x-cloak class="mt-1.5 text-sm text-danger-texte" x-text="erreurs.motif?.[0]"></p>
            </div>
        </form>
        <x-slot:pied>
            <x-bouton type="button" variante="secondaire" x-on:click="$dispatch('fermer-modal', 'modale-ajustement')">Annuler</x-bouton>
            <x-bouton type="submit" form="formulaire-ajustement" icone="save" x-bind:disabled="envoi"
                      x-bind:data-chargement="envoi.toString()" x-bind:aria-busy="envoi.toString()">Enregistrer</x-bouton>
        </x-slot:pied>
    </x-modal>
</div>
