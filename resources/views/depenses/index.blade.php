{{-- Dépenses : filtres (période, dates, catégorie, recherche), total, anneau par catégorie, tableau, modales --}}
@extends('layouts.app')

@section('titre', 'Dépenses')

@php
    $classePuce = 'h-9 rounded-full border border-bordure-forte px-3.5 text-sm font-medium whitespace-nowrap text-texte-doux transition-colors duration-150 hover:text-texte aria-pressed:border-primaire aria-pressed:bg-primaire-doux aria-pressed:text-lien pointer-coarse:h-11';
    $classeChamp = 'h-11 w-full rounded-controle border border-bordure-forte bg-surface px-3 text-sm text-texte aria-invalid:border-danger';
@endphp

@section('page')
    <div x-data="depenses({ urlCreation: @js(route('depenses.store')) })"
         x-on:depense-modifier.window="modifier($event.detail)"
         x-on:depense-supprimer.window="confirmerSuppression($event.detail)">
        <x-entete-page titre="Dépenses" description="Frais de fonctionnement de la quincaillerie, déduits du bénéfice."
                       :fil="['Tableau de bord' => route('accueil'), 'Dépenses' => null]">
            <x-slot:actions>
                <x-bouton type="button" icone="plus" x-on:click="nouvelle()">Nouvelle dépense</x-bouton>
            </x-slot:actions>
        </x-entete-page>

        <div x-data="listeDynamique" x-on:depenses-modifiees.window="charger(window.location.href)">
            <form x-ref="filtres" method="GET" action="{{ route('depenses.index') }}" role="search" x-on:change="$event.target.type !== 'search' && chargerDepuisFiltres()">
                <div class="mb-5 space-y-3" x-data="{ periode: @js($filtres['periode']), categorie: @js((string) $filtres['categorie']) }">
                    <input type="hidden" name="periode" value="{{ $filtres['periode'] }}">
                    <input type="hidden" name="categorie" value="{{ $filtres['categorie'] }}">

                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                        <x-recherche id="recherche-depenses" :valeur="$filtres['recherche']" :raccourci="false" label="Rechercher une dépense"
                                     placeholder="Libellé ou notes…" class="lg:w-80" />
                        <div class="flex items-center gap-2 text-sm text-texte-doux">
                            <label for="filtre-du">Du</label>
                            <input id="filtre-du" x-ref="du" type="date" name="du" value="{{ $filtres['du'] }}" class="{{ $classeChamp }} w-auto">
                            <label for="filtre-au">au</label>
                            <input id="filtre-au" x-ref="au" type="date" name="au" value="{{ $filtres['au'] }}" class="{{ $classeChamp }} w-auto">
                        </div>
                    </div>

                    <div role="group" aria-label="Période" class="flex flex-wrap gap-2">
                        @foreach (\App\Http\Controllers\DepenseController::PERIODES as $valeur => $libelle)
                            <button type="button" class="{{ $classePuce }}" x-on:click="periode = '{{ $valeur }}'; $refs.du.value = ''; $refs.au.value = ''; filtrer('periode', periode)"
                                    aria-pressed="{{ $filtres['periode'] === $valeur && ! $filtres['du'] && ! $filtres['au'] ? 'true' : 'false' }}" x-bind:aria-pressed="(periode === '{{ $valeur }}').toString()">{{ $libelle }}</button>
                        @endforeach
                    </div>
                    <div role="group" aria-label="Catégorie" class="flex flex-wrap gap-2">
                        <button type="button" class="{{ $classePuce }}" x-on:click="categorie = ''; filtrer('categorie', '')"
                                aria-pressed="{{ $filtres['categorie'] ? 'false' : 'true' }}" x-bind:aria-pressed="(categorie === '').toString()">Toutes</button>
                        @foreach (\App\Enums\CategorieDepense::cases() as $categorie)
                            <button type="button" class="{{ $classePuce }} inline-flex items-center gap-2"
                                    x-on:click="categorie = categorie === '{{ $categorie->value }}' ? '' : '{{ $categorie->value }}'; filtrer('categorie', categorie)"
                                    aria-pressed="{{ $filtres['categorie'] === $categorie->value ? 'true' : 'false' }}" x-bind:aria-pressed="(categorie === '{{ $categorie->value }}').toString()">
                                <x-icone :nom="$categorie->icone()" taille="size-4" />
                                {{ $categorie->libelle() }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </form>

            <div x-ref="liste">@include('depenses._liste')</div>

            <template x-ref="squelette"><x-squelette type="ligne" :lignes="6" libelle="Chargement des dépenses…" /></template>
        </div>

        {{-- Saisie / modification --}}
        <x-modal id="modale-depense" titre="Nouvelle dépense" titre-dynamique="cible ? 'Modifier la dépense' : 'Nouvelle dépense'" taille="md">
            <form id="formulaire-depense" class="space-y-4" novalidate x-on:submit.prevent="enregistrer()">
                <fieldset>
                    <legend class="mb-2 text-sm font-medium">Catégorie <span class="text-danger-texte" aria-hidden="true">*</span></legend>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        @foreach (\App\Enums\CategorieDepense::cases() as $categorie)
                            <label class="flex min-h-16 cursor-pointer flex-col items-center justify-center gap-1.5 rounded-controle border border-bordure-forte px-2 py-2 text-center text-xs font-medium transition-colors duration-150 has-checked:border-primaire has-checked:bg-primaire-doux has-focus-visible:outline-2 has-focus-visible:outline-anneau">
                                <input type="radio" name="categorie" value="{{ $categorie->value }}" x-model="formulaire.categorie" class="sr-only">
                                <span class="grid size-8 place-items-center rounded-full {{ $categorie->classesIcone() }}"><x-icone :nom="$categorie->icone()" taille="size-4" /></span>
                                {{ $categorie->libelle() }}
                            </label>
                        @endforeach
                    </div>
                    <p x-show="erreurs.categorie" x-cloak class="mt-1.5 text-sm text-danger-texte" x-text="erreurs.categorie?.[0]"></p>
                </fieldset>

                <div>
                    <label for="depense-libelle" class="mb-1.5 block text-sm font-medium">Libellé <span class="text-danger-texte" aria-hidden="true">*</span></label>
                    <input id="depense-libelle" type="text" maxlength="255" x-model="formulaire.libelle" placeholder="Ex. facture JIRAMA de septembre"
                           x-bind:aria-invalid="(!! erreurs.libelle).toString()" aria-describedby="depense-libelle-erreur" class="{{ $classeChamp }}">
                    <p id="depense-libelle-erreur" x-show="erreurs.libelle" x-cloak class="mt-1.5 text-sm text-danger-texte" x-text="erreurs.libelle?.[0]"></p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="depense-montant" class="mb-1.5 block text-sm font-medium">Montant <span class="text-danger-texte" aria-hidden="true">*</span></label>
                        <div class="relative">
                            <input id="depense-montant" type="text" inputmode="numeric" x-model="formulaire.montant" placeholder="0"
                                   x-bind:aria-invalid="(!! erreurs.montant).toString()" aria-describedby="depense-montant-erreur" class="{{ $classeChamp }} chiffres pr-10 text-right">
                            <span class="pointer-events-none absolute inset-y-0 right-3 grid place-items-center text-sm text-texte-doux">Ar</span>
                        </div>
                        <p id="depense-montant-erreur" x-show="erreurs.montant" x-cloak class="mt-1.5 text-sm text-danger-texte" x-text="erreurs.montant?.[0]"></p>
                    </div>
                    <div>
                        <label for="depense-date" class="mb-1.5 block text-sm font-medium">Date <span class="text-danger-texte" aria-hidden="true">*</span></label>
                        <input id="depense-date" type="date" x-model="formulaire.date_depense" max="{{ today()->toDateString() }}"
                               x-bind:aria-invalid="(!! erreurs.date_depense).toString()" aria-describedby="depense-date-erreur" class="{{ $classeChamp }}">
                        <p id="depense-date-erreur" x-show="erreurs.date_depense" x-cloak class="mt-1.5 text-sm text-danger-texte" x-text="erreurs.date_depense?.[0]"></p>
                    </div>
                </div>

                <div>
                    <label for="depense-mode" class="mb-1.5 block text-sm font-medium">Mode de paiement <span class="text-danger-texte" aria-hidden="true">*</span></label>
                    <select id="depense-mode" x-model="formulaire.mode_paiement" class="{{ $classeChamp }}" aria-describedby="depense-mode-erreur">
                        @foreach (\App\Enums\ModePaiement::encaissements() as $mode)
                            <option value="{{ $mode->value }}">{{ $mode->libelle() }}</option>
                        @endforeach
                    </select>
                    <p id="depense-mode-erreur" x-show="erreurs.mode_paiement" x-cloak class="mt-1.5 text-sm text-danger-texte" x-text="erreurs.mode_paiement?.[0]"></p>
                </div>

                <div>
                    <label for="depense-notes" class="mb-1.5 block text-sm font-medium">Notes <span class="font-normal text-texte-doux">(facultatif)</span></label>
                    <textarea id="depense-notes" rows="2" maxlength="1000" x-model="formulaire.notes" placeholder="N° de facture, bénéficiaire…"
                              class="w-full rounded-controle border border-bordure-forte bg-surface px-3 py-2 text-sm text-texte"></textarea>
                </div>
            </form>
            <x-slot:pied>
                <x-bouton type="button" variante="secondaire" x-on:click="$dispatch('fermer-modal', 'modale-depense')">Annuler</x-bouton>
                <x-bouton type="submit" form="formulaire-depense" icone="save" x-bind:disabled="envoi"
                          x-bind:data-chargement="envoi.toString()" x-bind:aria-busy="envoi.toString()">Enregistrer</x-bouton>
            </x-slot:pied>
        </x-modal>

        {{-- Suppression (Administrateur) --}}
        @if ($estAdministrateur)
            <x-modal id="modale-suppression-depense" titre="Supprimer cette dépense ?" taille="sm" role="alertdialog"
                     description="Elle disparaîtra des listes et des totaux ; la suppression est enregistrée dans le journal.">
                <p class="text-sm" x-show="suppression"><span class="font-medium" x-text="suppression?.libelle"></span> · <span class="chiffres" x-text="suppression?.montant"></span></p>
                <x-slot:pied>
                    <x-bouton type="button" variante="secondaire" autofocus x-on:click="$dispatch('fermer-modal', 'modale-suppression-depense')">Retour</x-bouton>
                    <x-bouton type="button" variante="danger" icone="trash-2" x-on:click="supprimer()" x-bind:disabled="envoi"
                              x-bind:data-chargement="envoi.toString()">Supprimer</x-bouton>
                </x-slot:pied>
            </x-modal>
        @endif
    </div>
@endsection
