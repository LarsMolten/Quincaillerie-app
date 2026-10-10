{{--
    Inventaire : saisie du comptage optimisée tablette (composant Alpine « comptageInventaire »), import CSV,
    PDF (feuille de comptage, rapport d'écarts) et validation. Lecture seule une fois validé.
--}}
@extends('layouts.app')

@php
    $classePuce = 'h-11 rounded-full border border-bordure-forte px-4 text-sm font-medium text-texte-doux transition-colors duration-150 hover:text-texte aria-pressed:border-primaire aria-pressed:bg-primaire-doux aria-pressed:text-lien';
@endphp

@section('titre', 'Inventaire '.$inventaire->numero)

@section('page')
    <div x-data="comptageInventaire({{ \Illuminate\Support\Js::from($config) }})">
        <x-entete-page :titre="'Inventaire '.$inventaire->numero"
                       :description="'Ouvert le '.$inventaire->date_inventaire->translatedFormat('j F Y').' par '.$inventaire->utilisateur->nom.($inventaire->notes ? ' · '.$inventaire->notes : '')"
                       :fil="['Tableau de bord' => route('accueil'), 'Inventaires' => route('inventaires.index'), $inventaire->numero => null]">
            <x-slot:actions>
                <x-bouton :href="route('inventaires.pdf', [$inventaire, 'feuille'])" target="_blank" x-ouvrir-pdf variante="secondaire" icone="printer">Feuille de comptage</x-bouton>
                <x-bouton :href="route('inventaires.pdf', [$inventaire, 'rapport'])" target="_blank" x-ouvrir-pdf variante="secondaire" icone="file-text">Rapport d'écarts</x-bouton>
                @unless ($valide)
                    <x-bouton type="button" variante="secondaire" icone="upload" x-on:click="$dispatch('ouvrir-modal', 'import-inventaire')">Importer CSV</x-bouton>
                    <x-bouton type="button" icone="check" x-on:click="$dispatch('ouvrir-modal', 'valider-inventaire')">Valider l'inventaire</x-bouton>
                @endunless
            </x-slot:actions>
        </x-entete-page>

        @if ($valide)
            <div role="note" class="mb-6 flex items-start gap-3 rounded-carte border border-succes bg-succes-doux p-4 text-sm text-succes-texte">
                <x-icone nom="circle-check" class="mt-0.5" />
                <p><span class="font-semibold">Inventaire validé</span> — le stock a été corrigé par {{ $inventaire->mouvementsStock()->count() }} ajustement(s) ;
                    cet inventaire n'est plus modifiable.</p>
            </div>
        @endif

        {{-- Progression et filtres (collants) --}}
        <div class="sticky top-16 z-10 -mx-4 mb-4 space-y-3 border-b border-bordure bg-fond/95 px-4 py-3 backdrop-blur sm:mx-0 sm:rounded-carte sm:border sm:bg-surface/95">
            <div class="flex items-center gap-4">
                <div class="h-3 flex-1 overflow-hidden rounded-full bg-neutre-doux" role="progressbar" aria-label="Progression du comptage"
                     aria-valuemin="0" aria-valuemax="100" x-bind:aria-valuenow="pourcentage">
                    <div class="h-full rounded-full bg-primaire transition-[width] duration-200" x-bind:style="`width: ${pourcentage}%`"></div>
                </div>
                <p class="chiffres shrink-0 text-sm"><span class="font-semibold" x-text="comptees"></span> / <span x-text="total"></span> comptés
                    · <span class="font-semibold" x-text="pourcentage + ' %'"></span></p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <label for="recherche-comptage" class="sr-only">Rechercher un produit</label>
                <div class="relative sm:w-80">
                    <x-icone nom="search" taille="size-4" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-texte-doux" />
                    <input id="recherche-comptage" type="search" x-model="recherche" placeholder="Nom, référence ou code-barres…" autocomplete="off"
                           class="h-11 w-full rounded-controle border border-bordure-forte bg-surface pr-3 pl-9 text-sm text-texte">
                </div>
                <div role="group" aria-label="Filtrer" class="flex flex-wrap gap-2">
                    <button type="button" class="{{ $classePuce }}" x-on:click="filtre = 'tous'" x-bind:aria-pressed="(filtre === 'tous').toString()">Tous</button>
                    <button type="button" class="{{ $classePuce }}" x-on:click="filtre = 'non_comptes'" x-bind:aria-pressed="(filtre === 'non_comptes').toString()">
                        Non comptés <span class="chiffres" x-text="'(' + (total - comptees) + ')'"></span>
                    </button>
                    <button type="button" class="{{ $classePuce }}" x-on:click="filtre = 'ecarts'" x-bind:aria-pressed="(filtre === 'ecarts').toString()">
                        Avec écart <span class="chiffres" x-text="'(' + avecEcart + ')'"></span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Lignes de comptage --}}
        <x-carte :padding="false">
            <p x-show="visibles.length === 0" x-cloak class="px-carte py-10 text-center text-sm text-texte-doux">Aucun produit ne correspond.</p>
            <ul class="divide-y divide-bordure" aria-label="Produits à compter">
                <template x-for="ligne in visibles" x-bind:key="ligne.id">
                    <li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-carte py-3" x-bind:class="ligne.compte !== null && 'bg-fond/60'">
                        <div class="min-w-0 flex-1 basis-full sm:basis-0">
                            <p class="font-medium" x-text="ligne.nom"></p>
                            <p class="chiffres text-xs text-texte-doux">
                                <span x-text="ligne.reference + ' · ' + ligne.categorie"></span>
                                · stock <span x-text="qte(ligne.actuel, ligne.unite)"></span>
                                <span x-show="ligne.actuel !== ligne.theorique" class="text-alerte-texte" x-text="'(' + qte(ligne.theorique, ligne.unite) + ' à l’ouverture)'"
                                      title="Le stock a bougé depuis l’ouverture de l’inventaire"></span>
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <label x-bind:for="'compte-' + ligne.id" class="sr-only" x-text="'Quantité comptée : ' + ligne.nom"></label>
                            <input x-bind:id="'compte-' + ligne.id" type="text" inputmode="decimal" autocomplete="off" enterkeyhint="next"
                                   x-model="ligne.saisie" x-bind:disabled="lectureSeule" placeholder="—"
                                   x-on:keydown.enter.prevent="toucheEntree(ligne)" x-on:blur="enregistrer(ligne)"
                                   x-bind:aria-invalid="(ligne.etat === 'erreur').toString()" x-bind:aria-describedby="'etat-' + ligne.id"
                                   class="chiffres h-14 w-32 rounded-controle border-2 border-bordure-forte bg-surface px-3 text-right text-xl font-semibold text-texte focus:border-primaire disabled:bg-fond aria-invalid:border-danger">
                            <span class="w-10 text-sm text-texte-doux" x-text="ligne.unite ?? ''"></span>
                            <div class="w-28 text-right">
                                <p class="text-xs text-texte-doux">Écart</p>
                                <p class="chiffres text-lg font-semibold" x-bind:class="couleur(ligne)" x-text="ecartTexte(ligne)"></p>
                            </div>
                            <span x-bind:id="'etat-' + ligne.id" class="grid size-6 place-items-center" aria-live="polite">
                                <x-icone nom="loader-circle" taille="size-4" class="animate-spin text-texte-doux" x-show="ligne.etat === 'envoi'" x-cloak />
                                <x-icone nom="check" taille="size-4" class="text-succes-texte" x-show="ligne.etat === 'ok'" x-cloak />
                                <x-icone nom="circle-alert" taille="size-4" class="text-danger-texte" x-show="ligne.etat === 'erreur'" x-cloak />
                                <span class="sr-only" x-text="ligne.etat === 'ok' ? 'Enregistré' : (ligne.etat === 'erreur' ? ligne.message : '')"></span>
                            </span>
                        </div>
                        <p x-show="ligne.etat === 'erreur'" x-cloak class="basis-full text-right text-sm text-danger-texte" x-text="ligne.message"></p>
                    </li>
                </template>
            </ul>
        </x-carte>

        @unless ($valide)
            {{-- Import CSV --}}
            <x-modal id="import-inventaire" titre="Importer un comptage (CSV)" taille="sm"
                     description="Une ligne par produit : référence (ou code-barres) puis quantité, séparées par ; ou ,. L'en-tête est facultatif.">
                <form method="POST" action="{{ route('inventaires.import', $inventaire) }}" enctype="multipart/form-data" x-chargement-envoi class="space-y-4">
                    @csrf
                    <pre class="rounded-controle bg-fond px-3 py-2 text-xs text-texte-doux">reference;quantite
PRD-00001;38
PRD-00002;19,5</pre>
                    <div>
                        <label for="fichier-csv" class="mb-1.5 block text-sm font-medium">Fichier CSV</label>
                        <input id="fichier-csv" type="file" name="fichier" accept=".csv,text/csv,text/plain" required
                               class="block w-full text-sm text-texte file:mr-3 file:h-11 file:rounded-controle file:border-0 file:bg-primaire-doux file:px-4 file:font-medium file:text-lien">
                        @error('fichier')<p class="mt-1.5 text-sm text-danger-texte">{{ $message }}</p>@enderror
                    </div>
                    <div class="-mx-6 -mb-6 flex flex-col-reverse gap-3 border-t border-bordure px-6 py-4 sm:flex-row sm:justify-end">
                        <x-bouton type="button" variante="secondaire" x-on:click="$dispatch('fermer-modal', 'import-inventaire')">Annuler</x-bouton>
                        <x-bouton icone="upload">Importer</x-bouton>
                    </div>
                </form>
            </x-modal>
            @error('fichier')
                <div x-data x-init="$nextTick(() => $dispatch('ouvrir-modal', 'import-inventaire'))"></div>
            @enderror

            {{-- Validation --}}
            <x-modal id="valider-inventaire" titre="Valider l'inventaire {{ $inventaire->numero }} ?" taille="sm" role="alertdialog"
                     description="Le stock de chaque produit compté sera aligné sur la quantité comptée. L'inventaire ne sera plus modifiable.">
                <form method="POST" action="{{ route('inventaires.valider', $inventaire) }}" x-chargement-envoi class="space-y-4">
                    @csrf
                    <ul class="space-y-1 text-sm">
                        <li><span class="chiffres font-semibold" x-text="comptees"></span> produit(s) compté(s), dont <span class="chiffres font-semibold" x-text="avecEcart"></span> avec un écart.</li>
                        <li x-show="total - comptees > 0" class="text-alerte-texte"><span class="chiffres font-semibold" x-text="total - comptees"></span> produit(s) non compté(s) : leur stock ne changera pas.</li>
                    </ul>
                    <label x-show="total - comptees > 0" class="flex min-h-11 cursor-pointer items-center gap-2 text-sm">
                        <input type="checkbox" name="confirmer_non_comptes" value="1" class="size-4 accent-primaire" x-bind:required="total - comptees > 0">
                        Valider sans les produits non comptés
                    </label>
                    <div class="-mx-6 -mb-6 flex flex-col-reverse gap-3 border-t border-bordure px-6 py-4 sm:flex-row sm:justify-end">
                        <x-bouton type="button" variante="secondaire" autofocus x-on:click="$dispatch('fermer-modal', 'valider-inventaire')">Retour</x-bouton>
                        <x-bouton icone="check" x-bind:disabled="comptees === 0">Valider et corriger le stock</x-bouton>
                    </div>
                </form>
            </x-modal>
        @endunless
    </div>
@endsection
