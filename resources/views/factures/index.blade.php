{{-- Factures : synthèse, recherche, puces (période, statut, paiement), tableau, aperçu PDF, envoi et partage --}}
@extends('layouts.app')

@section('titre', 'Factures')

@php
    $classePuce = 'h-9 rounded-full border border-bordure-forte px-3.5 text-sm font-medium whitespace-nowrap text-texte-doux transition-colors duration-150 hover:text-texte aria-pressed:border-primaire aria-pressed:bg-primaire-doux aria-pressed:text-lien pointer-coarse:h-11';
    $config = ['urlFiche' => route('factures.show', '__ID__'), 'apercu' => $apercu];
@endphp

@section('page')
    <div x-data="factures({{ \Illuminate\Support\Js::from($config) }})"
         x-on:facture-apercu.window="ouvrirApercu($event.detail)"
         x-on:facture-envoi.window="ouvrirEnvoi($event.detail)"
         x-on:facture-partage.window="ouvrirPartage($event.detail)">
        <x-entete-page titre="Factures" description="Une facture émise n'est jamais modifiée : une correction passe par l'annulation de la vente ou un retour."
                       :fil="['Tableau de bord' => route('accueil'), 'Factures' => null]" />

        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <x-carte-stat libelle="Montant facturé" :valeur="format_ar($synthese['montant'])" icone="file-text" />
            <x-carte-stat libelle="Factures émises" :valeur="$synthese['nombre']" icone="receipt" />
            <x-carte-stat libelle="Reste à encaisser" :valeur="format_ar($synthese['reste'])" icone="hand-coins" />
        </div>

        <div x-data="listeDynamique">
            <form x-ref="filtres" method="GET" action="{{ route('factures.index') }}" role="search">
                {{-- x-data sur un élément interne : x-ref="filtres" doit appartenir au composant listeDynamique --}}
                <div class="mb-5 space-y-3" x-data="{ periode: @js($filtres['periode']), statut: @js((string) $filtres['statut']), paiement: @js((string) $filtres['paiement']) }">
                    <input type="hidden" name="periode" value="{{ $filtres['periode'] }}">
                    <input type="hidden" name="statut" value="{{ $filtres['statut'] }}">
                    <input type="hidden" name="paiement" value="{{ $filtres['paiement'] }}">

                    <x-recherche id="recherche-factures" :valeur="$filtres['recherche']" :raccourci="false" label="Rechercher une facture"
                                 placeholder="N° de facture, de vente ou client…" class="lg:w-96" />

                    <div class="flex flex-wrap items-center gap-2">
                        <div role="group" aria-label="Période" class="flex flex-wrap gap-2">
                            @foreach (\App\Http\Controllers\FactureController::PERIODES as $valeur => $libelle)
                                <button type="button" class="{{ $classePuce }}" x-on:click="periode = '{{ $valeur }}'; filtrer('periode', periode)"
                                        aria-pressed="{{ $filtres['periode'] === $valeur ? 'true' : 'false' }}" x-bind:aria-pressed="(periode === '{{ $valeur }}').toString()">{{ $libelle }}</button>
                            @endforeach
                        </div>
                        <span class="mx-1 hidden h-6 w-px bg-bordure sm:block" aria-hidden="true"></span>
                        <div role="group" aria-label="Statut" class="flex flex-wrap gap-2">
                            @foreach (['' => ['Toutes', null], 'emises' => ['Émises', 'bg-succes'], 'annulees' => ['Annulées', 'bg-danger']] as $valeur => [$libelle, $pastille])
                                <button type="button" class="{{ $classePuce }} inline-flex items-center gap-2" x-on:click="statut = '{{ $valeur }}'; filtrer('statut', statut)"
                                        aria-pressed="{{ (string) $filtres['statut'] === $valeur ? 'true' : 'false' }}" x-bind:aria-pressed="(statut === '{{ $valeur }}').toString()">
                                    @if ($pastille)<span class="size-2 rounded-full {{ $pastille }}" aria-hidden="true"></span>@endif
                                    {{ $libelle }}
                                </button>
                            @endforeach
                        </div>
                        <span class="mx-1 hidden h-6 w-px bg-bordure sm:block" aria-hidden="true"></span>
                        <div role="group" aria-label="Paiement" class="flex flex-wrap gap-2">
                            @foreach (['paye' => ['Payées', 'bg-succes'], 'partiel' => ['Partielles', 'bg-alerte'], 'credit' => ['À crédit', 'bg-danger']] as $valeur => [$libelle, $pastille])
                                <button type="button" class="{{ $classePuce }} inline-flex items-center gap-2"
                                        x-on:click="paiement = paiement === '{{ $valeur }}' ? '' : '{{ $valeur }}'; filtrer('paiement', paiement)"
                                        aria-pressed="{{ (string) $filtres['paiement'] === $valeur ? 'true' : 'false' }}" x-bind:aria-pressed="(paiement === '{{ $valeur }}').toString()">
                                    <span class="size-2 rounded-full {{ $pastille }}" aria-hidden="true"></span>
                                    {{ $libelle }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </form>

            <div x-ref="liste">@include('factures._liste')</div>

            <template x-ref="squelette"><x-squelette type="ligne" :lignes="6" libelle="Chargement des factures…" /></template>
        </div>

        {{-- Aperçu (PDF affiché depuis une URL blob:, non interceptable par un gestionnaire de téléchargement) --}}
        <x-modal id="facture-apercu" titre="Aperçu de la facture" taille="xl" titre-dynamique="facture ? 'Facture ' + facture.numero : 'Aperçu de la facture'">
            <div class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-texte-doux" x-show="facture" x-text="facture ? facture.client + ' · ' + facture.date + ' · ' + facture.total : ''"></p>
                    <div class="inline-flex rounded-controle border border-bordure-forte p-0.5 text-sm" role="group" aria-label="Format">
                        <button type="button" x-on:click="afficher('a4')" x-bind:aria-pressed="(format === 'a4').toString()"
                                class="h-9 rounded-[calc(var(--radius-controle)-2px)] px-3 font-medium text-texte-doux aria-pressed:bg-primaire-doux aria-pressed:text-lien pointer-coarse:h-11">A4</button>
                        <button type="button" x-on:click="afficher('ticket')" x-bind:aria-pressed="(format === 'ticket').toString()"
                                class="h-9 rounded-[calc(var(--radius-controle)-2px)] px-3 font-medium text-texte-doux aria-pressed:bg-primaire-doux aria-pressed:text-lien pointer-coarse:h-11">Ticket 80 mm</button>
                    </div>
                </div>
                <div class="relative h-[65dvh] overflow-hidden rounded-controle border border-bordure bg-fond">
                    <div x-show="chargement" class="absolute inset-0 animate-squelette bg-neutre-doux" role="status" aria-label="Génération du PDF…"></div>
                    <div x-show="erreur && ! chargement" x-cloak class="absolute inset-0 grid place-items-center p-6">
                        <x-etat-vide icone="circle-alert" titre="Aperçu indisponible" texte="Le PDF n'a pas pu être généré.">
                            <x-bouton type="button" variante="secondaire" icone="refresh-cw" x-on:click="afficher(format)">Réessayer</x-bouton>
                        </x-etat-vide>
                    </div>
                    <iframe id="facture-cadre" x-show="adresse && ! chargement" x-bind:src="adresse" title="Aperçu PDF de la facture" class="size-full"></iframe>
                </div>
            </div>
            <x-slot:pied>
                <x-bouton type="button" variante="secondaire" icone="send" x-on:click="ouvrirEnvoi(facture.id)">Envoyer par email</x-bouton>
                <x-bouton type="button" variante="secondaire" icone="share-2" x-on:click="ouvrirPartage(facture.id)">Partager</x-bouton>
                <x-bouton type="button" variante="secondaire" icone="download" x-bind:disabled="! adresse" x-on:click="telecharger()">Télécharger</x-bouton>
                <x-bouton type="button" icone="printer" x-bind:disabled="! adresse" x-on:click="imprimer()">Imprimer</x-bouton>
            </x-slot:pied>
        </x-modal>

        {{-- Envoi par email --}}
        <x-modal id="facture-envoi" titre="Envoyer la facture" taille="sm" titre-dynamique="facture ? 'Envoyer ' + facture.numero : 'Envoyer la facture'"
                 description="Le PDF A4 est joint au message.">
            <form class="space-y-4" novalidate x-on:submit.prevent="envoyer()" id="formulaire-envoi-facture">
                <div>
                    <label for="facture-email" class="mb-1.5 block text-sm font-medium">Adresse email <span class="text-danger-texte" aria-hidden="true">*</span></label>
                    <input id="facture-email" type="email" autocomplete="email" required x-model="envoi.email"
                           x-bind:aria-invalid="(!! envoi.erreurs.email).toString()" aria-describedby="facture-email-erreur"
                           class="h-11 w-full rounded-controle border border-bordure-forte bg-surface px-3 text-sm text-texte aria-invalid:border-danger">
                    <p id="facture-email-erreur" x-show="envoi.erreurs.email" x-cloak class="mt-1.5 text-sm text-danger-texte" x-text="envoi.erreurs.email?.[0]"></p>
                </div>
                <div>
                    <label for="facture-message" class="mb-1.5 block text-sm font-medium">Message <span class="font-normal text-texte-doux">(facultatif)</span></label>
                    <textarea id="facture-message" rows="3" maxlength="500" x-model="envoi.message" placeholder="Veuillez trouver ci-joint votre facture."
                              class="w-full rounded-controle border border-bordure-forte bg-surface px-3 py-2 text-sm text-texte"></textarea>
                </div>
            </form>
            <x-slot:pied>
                <x-bouton type="button" variante="secondaire" x-on:click="$dispatch('fermer-modal', 'facture-envoi')">Annuler</x-bouton>
                <x-bouton type="submit" form="formulaire-envoi-facture" icone="send" x-bind:disabled="envoi.enCours"
                          x-bind:data-chargement="envoi.enCours.toString()" x-bind:aria-busy="envoi.enCours.toString()">Envoyer</x-bouton>
            </x-slot:pied>
        </x-modal>

        {{-- Partage (lien signé, WhatsApp) --}}
        <x-modal id="facture-partage" titre="Partager la facture" taille="sm" titre-dynamique="facture ? 'Partager ' + facture.numero : 'Partager la facture'">
            <div class="space-y-4">
                <div x-show="partage.enCours" class="h-24 animate-squelette rounded-controle bg-neutre-doux" role="status" aria-label="Création du lien…"></div>
                <div x-show="! partage.enCours" x-cloak class="space-y-3">
                    <label for="facture-lien" class="block text-sm font-medium">Lien vers le PDF</label>
                    <div class="flex gap-2">
                        <input id="facture-lien" type="text" readonly x-bind:value="partage.url" x-on:focus="$event.target.select()"
                               class="h-11 min-w-0 flex-1 rounded-controle border border-bordure-forte bg-fond px-3 text-sm text-texte">
                        <x-bouton type="button" variante="secondaire" icone="copy" x-on:click="copier()">Copier</x-bouton>
                    </div>
                    <p class="text-sm text-texte-doux">
                        Accessible sans compte, uniquement pour cette facture, jusqu'au <span class="font-medium text-texte" x-text="partage.expiration"></span>.
                    </p>
                </div>
            </div>
            <x-slot:pied>
                <x-bouton type="button" variante="secondaire" x-on:click="$dispatch('fermer-modal', 'facture-partage')">Fermer</x-bouton>
                <x-bouton icone="message-circle" href="#" x-bind:href="partage.whatsapp" target="_blank" rel="noopener" x-bind:aria-disabled="partage.enCours.toString()">Ouvrir WhatsApp</x-bouton>
            </x-slot:pied>
        </x-modal>
    </div>
@endsection
