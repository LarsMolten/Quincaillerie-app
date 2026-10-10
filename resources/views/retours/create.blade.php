{{-- Assistant de retour en 3 étapes : 1. document d'origine, 2. produits et motif, 3. confirmation --}}
@extends('layouts.app')

@php
    $client = $type === \App\Enums\TypeRetour::Client;
    $titre = $client ? 'Nouveau retour client' : 'Nouveau retour fournisseur';
    $liste = $client ? 'retours.clients' : 'retours.fournisseurs';
    $etapes = [1 => $client ? 'Vente d\'origine' : 'Achat d\'origine', 2 => 'Produits', 3 => 'Confirmation'];
    $classePuce = 'h-9 rounded-full border border-bordure-forte px-3.5 text-sm font-medium text-texte-doux transition-colors duration-150 hover:text-texte aria-pressed:border-primaire aria-pressed:bg-primaire-doux aria-pressed:text-lien pointer-coarse:h-11';
    $classeChamp = 'h-11 w-full rounded-controle border border-bordure-forte bg-surface px-3 text-sm text-texte';
@endphp

@section('titre', $titre)

@section('page')
    <x-entete-page :titre="$titre"
                   :description="$client ? 'Produits rapportés par un client : remis en stock, déduits de sa créance puis remboursés.' : 'Produits renvoyés à un fournisseur : retirés du stock, déduits de la dette puis remboursés par le fournisseur.'"
                   :fil="['Tableau de bord' => route('accueil'), ($client ? 'Retours clients' : 'Retours fournisseurs') => route($liste), 'Nouveau' => null]" />

    <div x-data="assistantRetour({{ \Illuminate\Support\Js::from($config) }})" class="mx-auto max-w-4xl">
        {{-- Indicateur de progression --}}
        <nav aria-label="Étapes du retour" class="mb-6" x-show="! reussite">
            <ol class="grid grid-cols-3 gap-2">
                @foreach ($etapes as $numero => $libelle)
                    <li x-bind:aria-current="etape === {{ $numero }} ? 'step' : null">
                        <button type="button" x-on:click="retour({{ $numero }})" x-bind:disabled="etape <= {{ $numero }}"
                                class="group flex w-full flex-col gap-2 text-left disabled:cursor-default">
                            <span class="h-1.5 w-full rounded-full transition-colors duration-200"
                                  x-bind:class="etape >= {{ $numero }} ? 'bg-primaire' : 'bg-bordure'"></span>
                            <span class="flex items-center gap-2 text-sm">
                                <span class="chiffres grid size-7 shrink-0 place-items-center rounded-full border text-xs font-semibold transition-colors duration-200"
                                      x-bind:class="etape > {{ $numero }} ? 'border-primaire bg-primaire text-primaire-texte' : (etape === {{ $numero }} ? 'border-primaire text-lien' : 'border-bordure-forte text-texte-doux')">
                                    <span x-show="etape <= {{ $numero }}">{{ $numero }}</span>
                                    <x-icone nom="check" taille="size-3.5" x-show="etape > {{ $numero }}" x-cloak />
                                </span>
                                <span class="font-medium" x-bind:class="etape === {{ $numero }} ? 'text-texte' : 'text-texte-doux group-enabled:group-hover:text-texte'">{{ $libelle }}</span>
                            </span>
                        </button>
                    </li>
                @endforeach
            </ol>
            <p class="sr-only" aria-live="polite" x-text="'Étape ' + etape + ' sur 3'"></p>
        </nav>

        {{-- Étape 1 : document d'origine --}}
        <section x-show="etape === 1 && ! reussite" aria-labelledby="titre-etape-1">
            <x-carte>
                <h2 id="titre-etape-1" class="mb-4 text-lg font-semibold">{{ $client ? 'Quelle vente ?' : 'Quel achat ?' }}</h2>
                <label for="retour-recherche" class="sr-only">Rechercher</label>
                <div class="relative mb-4">
                    <x-icone nom="search" taille="size-4" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-texte-doux" />
                    <input id="retour-recherche" type="search" x-model="recherche" x-on:input="rechercherAvecDelai()" autocomplete="off" autofocus
                           placeholder="{{ $client ? 'N° de vente, de facture ou nom du client…' : 'N° d\'achat ou nom du fournisseur…' }}"
                           class="{{ $classeChamp }} pl-9">
                </div>

                <div x-show="chargement && resultats.length === 0" class="space-y-2" role="status" aria-label="Recherche…">
                    <div class="h-16 animate-squelette rounded-controle bg-neutre-doux"></div>
                    <div class="h-16 animate-squelette rounded-controle bg-neutre-doux"></div>
                </div>
                <p x-show="! chargement && resultats.length === 0" x-cloak class="py-6 text-center text-sm text-texte-doux">
                    Aucun document validé ne correspond à cette recherche.
                </p>
                <ul class="grid gap-2 sm:grid-cols-2" x-show="resultats.length > 0" x-bind:aria-busy="chargement.toString()">
                    <template x-for="doc in resultats" x-bind:key="doc.id">
                        <li>
                            <button type="button" x-on:click="choisir(doc.id)"
                                    class="flex w-full flex-col gap-1 rounded-controle border border-bordure bg-surface px-4 py-3 text-left transition-colors duration-150 hover:border-primaire hover:bg-primaire-doux/40 focus-visible:border-primaire">
                                <span class="flex items-center justify-between gap-3">
                                    <span class="font-medium" x-text="doc.facture ?? doc.numero"></span>
                                    <span class="chiffres font-semibold" x-text="ar(doc.total)"></span>
                                </span>
                                <span class="flex items-center justify-between gap-3 text-xs text-texte-doux">
                                    <span x-text="(doc.tiers ?? '—') + ' · ' + doc.date + (doc.facture ? ' · ' + doc.numero : '')"></span>
                                    <span x-show="doc.reste > 0" class="chiffres font-medium text-danger-texte" x-text="'reste ' + ar(doc.reste)"></span>
                                </span>
                            </button>
                        </li>
                    </template>
                </ul>
            </x-carte>
        </section>

        {{-- Étape 2 : produits et motif --}}
        <section x-show="etape === 2 && ! reussite" x-cloak aria-labelledby="titre-etape-2" class="space-y-4">
            <x-carte :padding="false">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-bordure px-carte py-4">
                    <div>
                        <h2 id="titre-etape-2" class="text-lg font-semibold">Produits retournés</h2>
                        <p class="text-sm text-texte-doux" x-text="document ? (document.facture ?? document.numero) + ' · ' + (document.tiers ?? '—') + ' · ' + document.date : ''"></p>
                    </div>
                    <x-bouton type="button" variante="secondaire" taille="sm" icone="x" x-on:click="aucun()">Tout effacer</x-bouton>
                </div>
                <ul class="divide-y divide-bordure">
                    <template x-for="ligne in document?.lignes ?? []" x-bind:key="ligne.produit_id">
                        <li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-carte py-3" x-bind:class="ligne.retournable <= 0 && 'opacity-60'">
                            <div class="min-w-0 flex-1 basis-full sm:basis-0">
                                <p class="font-medium" x-text="ligne.nom"></p>
                                <p class="chiffres text-xs text-texte-doux">
                                    <span x-text="'{{ $client ? 'Vendu' : 'Acheté' }} ' + qte(ligne.quantite, ligne.unite)"></span>
                                    <span x-show="ligne.retourne > 0" x-text="' · déjà retourné ' + qte(ligne.retourne, ligne.unite)"></span>
                                    · <span x-text="ar(ligne.prix_unitaire) + ' l\'unité'"></span>
                                </p>
                            </div>
                            <template x-if="ligne.retournable > 0">
                                <div class="flex items-center gap-2">
                                    <label x-bind:for="'quantite-' + ligne.produit_id" class="sr-only" x-text="'Quantité retournée de ' + ligne.nom"></label>
                                    <input x-bind:id="'quantite-' + ligne.produit_id" type="number" min="0" x-bind:max="ligne.retournable" step="any" inputmode="decimal"
                                           x-model="quantites[ligne.produit_id]" x-on:change="borner(ligne)" placeholder="0"
                                           class="chiffres h-11 w-24 rounded-controle border border-bordure-forte bg-surface px-3 text-right text-sm text-texte">
                                    <span class="chiffres w-24 text-xs text-texte-doux" x-text="'/ ' + qte(ligne.retournable, ligne.unite)"></span>
                                    <x-bouton type="button" variante="secondaire" taille="sm" x-on:click="tout(ligne)">Tout</x-bouton>
                                </div>
                            </template>
                            <template x-if="ligne.retournable <= 0">
                                <x-badge>Déjà tout retourné</x-badge>
                            </template>
                        </li>
                    </template>
                </ul>
                <div class="flex items-center justify-between border-t border-bordure px-carte py-4">
                    <span class="text-sm text-texte-doux">Montant du retour (indicatif)</span>
                    <span class="chiffres text-xl font-semibold" x-text="ar(total)"></span>
                </div>
            </x-carte>

            <x-carte>
                <fieldset>
                    <legend class="mb-3 text-base font-semibold">Motif du retour <span class="text-danger-texte" aria-hidden="true">*</span></legend>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="libelle in motifs" x-bind:key="libelle">
                            <button type="button" class="{{ $classePuce }}" x-on:click="motif = libelle" x-bind:aria-pressed="(motif === libelle).toString()" x-text="libelle"></button>
                        </template>
                    </div>
                    <label for="retour-precision" class="mt-4 mb-1.5 block text-sm font-medium">
                        Précision <span class="font-normal text-texte-doux" x-text="motif === 'Autre' ? '(obligatoire)' : '(facultatif)'"></span>
                    </label>
                    <input id="retour-precision" type="text" maxlength="200" x-model="precision" placeholder="Ex. sac déchiré à la livraison, mauvaise référence…"
                           class="{{ $classeChamp }}">
                </fieldset>
            </x-carte>

            <p x-show="erreur" x-cloak role="alert" class="rounded-controle bg-danger-doux px-4 py-3 text-sm text-danger-texte" x-text="erreur"></p>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
                <x-bouton type="button" variante="secondaire" icone="arrow-left" x-on:click="retour(1)">Changer de document</x-bouton>
                <x-bouton type="button" icone="arrow-right" x-on:click="suivant()">Continuer</x-bouton>
            </div>
        </section>

        {{-- Étape 3 : confirmation --}}
        <section x-show="etape === 3 && ! reussite" x-cloak aria-labelledby="titre-etape-3" class="space-y-4">
            <x-carte>
                <h2 id="titre-etape-3" class="mb-1 text-lg font-semibold">Confirmer le retour</h2>
                <p class="mb-4 text-sm text-texte-doux">
                    <span x-text="document ? (document.facture ?? document.numero) + ' · ' + (document.tiers ?? '—') : ''"></span> ·
                    Motif : <span class="text-texte" x-text="motif === 'Autre' ? precision : (motif + (precision.trim() ? ' — ' + precision.trim() : ''))"></span>
                </p>
                <ul class="divide-y divide-bordure rounded-controle border border-bordure">
                    <template x-for="ligne in lignesRetournees" x-bind:key="ligne.produit_id">
                        <li class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm">
                            <span><span x-text="ligne.nom"></span> <span class="chiffres text-texte-doux" x-text="'× ' + qte(ligne.quantiteRetour, ligne.unite)"></span></span>
                            <span class="chiffres font-medium" x-text="ar(Math.round(ligne.quantiteRetour * ligne.prix_unitaire))"></span>
                        </li>
                    </template>
                </ul>

                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-texte-doux">Montant du retour</dt><dd class="chiffres font-semibold" x-text="ar(total)"></dd></div>
                    <div class="flex justify-between">
                        <dt class="text-texte-doux">{{ $client ? 'Déduit de la créance du client' : 'Déduit de notre dette envers le fournisseur' }}</dt>
                        <dd class="chiffres" x-text="ar(avoir)"></dd>
                    </div>
                    <div class="flex justify-between border-t border-bordure pt-2 text-base">
                        <dt class="font-medium">{{ $client ? 'À rembourser au client' : 'À recevoir du fournisseur' }}</dt>
                        <dd class="chiffres font-semibold" x-bind:class="rembourse > 0 ? 'text-lien' : 'text-texte-doux'" x-text="ar(rembourse)"></dd>
                    </div>
                </dl>

                <div x-show="rembourse > 0" class="mt-4">
                    <label for="retour-mode" class="mb-1.5 block text-sm font-medium">Mode de remboursement <span class="text-danger-texte" aria-hidden="true">*</span></label>
                    <select id="retour-mode" x-model="mode" class="{{ $classeChamp }} sm:w-72">
                        <option value="">Choisir…</option>
                        <template x-for="m in modes" x-bind:key="m.valeur">
                            <option x-bind:value="m.valeur" x-text="m.libelle"></option>
                        </template>
                    </select>
                </div>
            </x-carte>

            <p x-show="erreur" x-cloak role="alert" class="rounded-controle bg-danger-doux px-4 py-3 text-sm text-danger-texte" x-text="erreur"></p>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
                <x-bouton type="button" variante="secondaire" icone="arrow-left" x-on:click="retour(2)">Modifier les produits</x-bouton>
                <x-bouton type="button" icone="check" x-on:click="enregistrer()" x-bind:disabled="! peutValider"
                          x-bind:data-chargement="envoi.toString()" x-bind:aria-busy="envoi.toString()">Valider le retour</x-bouton>
            </div>
        </section>

        {{-- Réussite --}}
        <section x-show="reussite" x-cloak aria-live="polite">
            <x-carte>
                <div class="flex flex-col items-center gap-3 py-6 text-center">
                    <span class="grid size-14 place-items-center rounded-full bg-succes-doux text-succes-texte"><x-icone nom="circle-check" taille="size-7" /></span>
                    <h2 class="text-xl font-semibold" x-text="'Retour ' + (reussite?.numero ?? '') + ' enregistré'"></h2>
                    <p class="chiffres text-sm text-texte-doux">
                        <span x-text="ar(reussite?.total ?? 0)"></span>
                        <span x-show="(reussite?.avoir ?? 0) > 0" x-text="' · ' + ar(reussite?.avoir ?? 0) + ' déduits du reste à payer'"></span>
                        <span x-show="(reussite?.rembourse ?? 0) > 0" x-text="' · ' + ar(reussite?.rembourse ?? 0) + ' remboursés'"></span>
                    </p>
                    <div class="mt-2 flex flex-wrap justify-center gap-3">
                        <x-bouton href="#" x-bind:href="reussite?.url_bon" target="_blank" x-ouvrir-pdf="reussite?.url_bon" variante="secondaire" icone="printer">Imprimer le bon</x-bouton>
                        <x-bouton href="#" x-bind:href="reussite?.url" variante="secondaire" icone="eye">Voir le retour</x-bouton>
                        <x-bouton :href="route('retours.create', ['type' => $type->value])" icone="plus">Nouveau retour</x-bouton>
                    </div>
                </div>
            </x-carte>
        </section>
    </div>
@endsection
