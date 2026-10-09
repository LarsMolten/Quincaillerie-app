{{-- Nouvel achat : recherche et lignes à gauche, récapitulatif collant à droite (composant Alpine saisieAchat) --}}
@extends('layouts.app')

@section('titre', 'Nouvel achat')

@section('page')
    <x-entete-page titre="Nouvel achat" description="Réception de marchandises : le stock est augmenté à l'enregistrement."
                   :fil="['Tableau de bord' => route('accueil'), 'Achats' => route('achats.index'), 'Nouvel achat' => null]" />

    <form method="POST" action="{{ route('achats.store') }}" x-chargement-envoi novalidate
          x-data="saisieAchat({{ \Illuminate\Support\Js::from([
              'urlProduits' => route('achats.produits'),
              'lignes' => $lignes,
              'fournisseur' => (string) $fournisseurChoisi,
              'montantPaye' => (string) old('montant_paye', ''),
              'modePaiement' => old('mode_paiement', 'especes'),
          ]) }})"
          class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_24rem]">
        @csrf

        {{-- Colonne gauche : recherche et lignes --}}
        <div class="space-y-4">
            <x-carte>
                <div class="relative">
                    <label for="recherche-produit-achat" class="mb-1.5 block text-sm font-medium">Ajouter un produit</label>
                    <div class="relative">
                        <x-icone nom="scan-barcode" taille="size-5" class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-texte-doux" />
                        <input id="recherche-produit-achat" x-ref="recherche" type="search" autocomplete="off" autofocus
                               x-model="recherche" x-on:input="saisir()"
                               x-on:keydown.enter.prevent="valider()" x-on:keydown.arrow-down.prevent="deplacer(1)"
                               x-on:keydown.arrow-up.prevent="deplacer(-1)" x-on:keydown.escape="recherche = ''; resultats = []"
                               role="combobox" aria-autocomplete="list" aria-controls="resultats-produits"
                               x-bind:aria-expanded="(resultats.length > 0).toString()" aria-expanded="false"
                               x-bind:aria-activedescendant="resultats.length ? 'resultat-' + actif : null"
                               placeholder="Nom, référence ou code-barres (scanner + Entrée)…"
                               class="h-12 w-full rounded-controle border border-bordure-forte bg-surface pl-11 pr-4 text-texte placeholder:text-texte-doux">
                    </div>
                    <ul id="resultats-produits" role="listbox" x-show="resultats.length > 0" x-cloak aria-label="Produits trouvés"
                        class="verre absolute inset-x-0 top-full z-20 mt-2 max-h-80 overflow-y-auto rounded-controle border border-bordure p-1 shadow-eleve">
                        <template x-for="(produit, index) in resultats" x-bind:key="produit.produit_id">
                            <li x-bind:id="'resultat-' + index" role="option" x-bind:aria-selected="(index === actif).toString()"
                                x-on:mousedown.prevent="ajouter(produit)" x-on:mouseenter="actif = index"
                                class="flex cursor-pointer items-center justify-between gap-3 rounded-lg px-3 py-2 text-sm"
                                x-bind:class="index === actif ? 'bg-primaire-doux' : ''">
                                <span class="min-w-0">
                                    <span class="block truncate font-medium text-texte" x-text="produit.nom"></span>
                                    <span class="block text-xs text-texte-doux" x-text="produit.reference + (produit.code_barres ? ' · ' + produit.code_barres : '')"></span>
                                </span>
                                <span class="shrink-0 text-right text-xs text-texte-doux">
                                    <span class="chiffres block font-medium text-texte" x-text="ar(produit.prix_actuel)"></span>
                                    <span x-text="'Stock : ' + produit.stock"></span>
                                </span>
                            </li>
                        </template>
                    </ul>
                </div>
            </x-carte>

            <x-carte :padding="false">
                <template x-if="lignes.length === 0">
                    <x-etat-vide icone="package" titre="Aucun produit" texte="Recherchez un produit ou scannez son code-barres pour commencer." />
                </template>

                <template x-if="lignes.length > 0">
                    <div>
                        <ul class="divide-y divide-bordure" role="list" aria-label="Lignes de l'achat">
                            <template x-for="(ligne, index) in lignes" x-bind:key="ligne.produit_id">
                                <li class="grid gap-3 px-carte py-4 sm:grid-cols-[minmax(0,1fr)_7rem_9rem_7rem_auto] sm:items-center">
                                    <input type="hidden" x-bind:name="'lignes[' + index + '][produit_id]'" x-bind:value="ligne.produit_id">
                                    <div class="min-w-0">
                                        <p class="truncate font-medium text-texte" x-text="ligne.nom"></p>
                                        <p class="text-xs text-texte-doux" x-text="ligne.reference + ' · stock actuel : ' + ligne.stock"></p>
                                    </div>
                                    <label class="block">
                                        <span class="mb-1 block text-xs text-texte-doux sm:sr-only" x-text="'Quantité (' + (ligne.unite ?? '') + ')'"></span>
                                        <input x-bind:id="'quantite-' + index" type="number" min="0" step="any" inputmode="decimal" required
                                               x-bind:name="'lignes[' + index + '][quantite]'" x-model="ligne.quantite"
                                               x-bind:aria-label="'Quantité de ' + ligne.nom"
                                               class="chiffres h-11 w-full rounded-controle border border-bordure-forte bg-surface px-3 text-right text-sm text-texte">
                                    </label>
                                    <label class="block">
                                        <span class="mb-1 block text-xs text-texte-doux sm:sr-only">Prix d'achat unitaire</span>
                                        <span class="flex h-11 overflow-hidden rounded-controle border border-bordure-forte bg-surface focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-anneau">
                                            <input type="number" min="0" step="1" inputmode="numeric" required
                                                   x-bind:name="'lignes[' + index + '][prix_achat]'" x-model="ligne.prix_achat"
                                                   x-bind:aria-label="`Prix d’achat de ${ligne.nom}`"
                                                   class="chiffres min-w-0 flex-1 bg-transparent px-3 text-right text-sm text-texte focus:outline-none">
                                            <span class="grid place-items-center border-l border-bordure bg-fond px-2 text-xs text-texte-doux" aria-hidden="true">Ar</span>
                                        </span>
                                        <span x-show="Number(ligne.prix_achat) !== ligne.prix_actuel" x-cloak
                                              class="mt-1 block text-xs text-alerte-texte" x-text="'Ancien prix : ' + ar(ligne.prix_actuel)"></span>
                                    </label>
                                    <p class="chiffres text-right font-semibold text-texte" x-text="ar(totalLigne(ligne))"></p>
                                    <button type="button" x-on:click="retirer(index)" x-bind:aria-label="'Retirer ' + ligne.nom"
                                            class="grid size-11 place-items-center justify-self-end rounded-controle text-texte-doux hover:bg-danger-doux hover:text-danger-texte">
                                        <x-icone nom="trash-2" taille="size-4" />
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>
                @error('lignes')<p class="px-carte pb-4 text-sm font-medium text-danger-texte">{{ $message }}</p>@enderror
                @foreach ($errors->getMessages() as $cle => $messages)
                    @if (str_starts_with($cle, 'lignes.'))
                        <p class="px-carte pb-2 text-sm font-medium text-danger-texte">{{ $messages[0] }}</p>
                    @endif
                @endforeach
            </x-carte>
        </div>

        {{-- Colonne droite : récapitulatif collant --}}
        <aside class="lg:sticky lg:top-20" aria-label="Récapitulatif de l'achat">
            <x-carte titre="Récapitulatif">
                <div class="space-y-4">
                    <x-select nom="fournisseur_id" label="Fournisseur" :options="$fournisseurs->pluck('nom', 'id')" vide="Choisir un fournisseur"
                              x-model="fournisseur" requis />
                    <x-champ nom="date_achat" label="Date de l'achat" type="date" :valeur="old('date_achat', today()->toDateString())"
                             max="{{ today()->toDateString() }}" requis />

                    <dl class="space-y-2 rounded-controle bg-fond p-4 text-sm">
                        <div class="flex justify-between"><dt class="text-texte-doux">Produits</dt><dd class="chiffres" x-text="lignes.length"></dd></div>
                        <div class="flex justify-between"><dt class="text-texte-doux">Quantité totale</dt><dd class="chiffres" x-text="articles.toLocaleString('fr-FR')"></dd></div>
                        <div class="flex items-end justify-between border-t border-bordure pt-2">
                            <dt class="font-medium">Total</dt>
                            <dd class="chiffres text-2xl font-semibold text-texte" x-text="ar(total)" aria-live="polite"></dd>
                        </div>
                    </dl>

                    <div class="space-y-2">
                        <x-champ nom="montant_paye" label="Montant payé" type="number" min="0" step="1" inputmode="numeric"
                                 suffixe="Ar" montant x-model="montantPaye" placeholder="0" />
                        <div class="grid grid-cols-3 gap-2" role="group" aria-label="Montant payé">
                            <x-bouton type="button" taille="sm" variante="secondaire" x-on:click="payerTotal()">Total</x-bouton>
                            <x-bouton type="button" taille="sm" variante="secondaire" x-on:click="payerPartiel()">Partiel</x-bouton>
                            <x-bouton type="button" taille="sm" variante="secondaire" x-on:click="aCredit()">À crédit</x-bouton>
                        </div>
                        <p class="text-sm" aria-live="polite">
                            <template x-if="depassement"><span class="font-medium text-danger-texte">Le montant payé dépasse le total.</span></template>
                            <template x-if="! depassement && reste > 0"><span class="text-texte-doux">Reste à payer : <span class="chiffres font-semibold text-danger-texte" x-text="ar(reste)"></span></span></template>
                            <template x-if="! depassement && reste <= 0 && total > 0"><span class="font-medium text-succes-texte">Achat payé en totalité.</span></template>
                        </p>
                    </div>

                    <div x-show="Number(montantPaye) > 0" x-cloak>
                        <x-select nom="mode_paiement" label="Mode de paiement" x-model="modePaiement"
                                  :options="collect($modes)->mapWithKeys(fn ($m) => [$m->value => $m->libelle()])" />
                    </div>

                    <input type="hidden" name="maj_prix" value="0">
                    <x-case nom="maj_prix" label="Mettre à jour les prix d'achat des produits" :coche="old('maj_prix', true)"
                            aide="Les nouveaux prix saisis deviennent le prix d'achat de référence." />
                    <x-textarea nom="notes" label="Notes" :lignes="2" :valeur="old('notes')" placeholder="N° de bon de livraison, remarques…" />

                    <x-bouton icone="save" class="w-full" taille="lg" x-bind:disabled="! peutEnregistrer">Enregistrer l'achat</x-bouton>
                    <p x-show="! peutEnregistrer" class="text-center text-xs text-texte-doux">
                        <span x-show="lignes.length === 0">Ajoutez au moins un produit.</span>
                        <span x-show="lignes.length > 0 && fournisseur === ''">Choisissez le fournisseur.</span>
                    </p>
                </div>
            </x-carte>
        </aside>
    </form>
@endsection
