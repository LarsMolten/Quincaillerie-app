{{--
    Écran de caisse : catalogue à gauche, ticket à droite (onglets sous 768 px).
    Composant Alpine « caisse » (resources/js/composants/caisse.js). Raccourcis : F1 aide, F2 recherche,
    F4 client, F8 remise, F9 paiement, Échap annuler, Ctrl+Entrée valider.
--}}
@extends('layouts.app')

@section('titre', 'Caisse')

@php
    $config = [
        'urls' => [
            'catalogue' => route('ventes.catalogue'),
            'clients' => route('ventes.clients'),
            'enregistrer' => route('ventes.store'),
        ],
        'comptoir' => $comptoir,
        'remise' => $remise,
        'stockNegatif' => $stockNegatif,
        'cleStockage' => 'caisse-ticket-'.auth()->id(),
    ];
    $modes = [
        'especes' => ['Espèces', 'banknote'],
        'mobile_money' => ['Mobile Money', 'smartphone'],
        'virement' => ['Virement', 'landmark'],
        'cheque' => ['Chèque', 'file-text'],
        'credit' => ['Crédit', 'hand-coins'],
    ];
    $billets = [1000, 2000, 5000, 10000, 20000];
    $classePuce = 'h-9 shrink-0 rounded-full border border-bordure-forte px-3.5 text-sm font-medium whitespace-nowrap text-texte-doux transition-colors duration-150 hover:text-texte aria-pressed:border-primaire aria-pressed:bg-primaire-doux aria-pressed:text-lien pointer-coarse:h-11';
    $classeTouche = 'inline-flex min-w-7 items-center justify-center rounded-md border border-bordure-forte bg-fond px-1.5 py-0.5 font-mono text-xs text-texte';
@endphp

@section('page')
    @if (! $comptoir)
        <x-carte>
            <x-etat-vide icone="circle-alert" titre="Client comptoir introuvable" texte="Le client « Client comptoir » est requis pour la caisse. Lancez les seeders de base." />
        </x-carte>
    @else
    <div x-data="caisse({{ \Illuminate\Support\Js::from($config) }})" class="relative">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Caisse</h1>
                <p class="text-sm text-texte-doux">Vendeur : {{ auth()->user()->nom }}</p>
            </div>
            <div class="flex items-center gap-2">
                <p x-show="! enLigne" x-cloak role="status" class="inline-flex items-center gap-2 rounded-full bg-danger-doux px-3 py-1.5 text-sm font-medium text-danger-texte">
                    <x-icone nom="wifi-off" taille="size-4" /> Hors ligne : ticket conservé, validation impossible
                </p>
                <x-bouton type="button" variante="secondaire" taille="sm" icone="keyboard" x-on:click="$dispatch('ouvrir-modal', 'caisse-aide')">
                    Raccourcis <kbd class="{{ $classeTouche }} ml-1">F1</kbd>
                </x-bouton>
            </div>
        </div>

        {{-- Onglets (mobile) --}}
        <div class="sticky top-16 z-10 -mx-4 mb-4 grid grid-cols-2 gap-2 border-b border-bordure bg-fond/95 px-4 py-2 md:hidden" role="tablist" aria-label="Zones de la caisse">
            <button type="button" role="tab" id="onglet-catalogue" aria-controls="zone-catalogue" x-bind:aria-selected="(onglet === 'catalogue').toString()"
                    x-on:click="onglet = 'catalogue'"
                    class="h-11 rounded-controle text-sm font-medium text-texte-doux aria-selected:bg-surface aria-selected:text-texte aria-selected:shadow-sm">Catalogue</button>
            <button type="button" role="tab" id="onglet-ticket" aria-controls="zone-ticket" x-bind:aria-selected="(onglet === 'ticket').toString()"
                    x-on:click="onglet = 'ticket'"
                    class="h-11 rounded-controle text-sm font-medium text-texte-doux aria-selected:bg-surface aria-selected:text-texte aria-selected:shadow-sm">
                Ticket <span class="chiffres" x-text="'(' + lignes.length + ') · ' + ar(total)"></span>
            </button>
        </div>

        <div class="grid items-start gap-4 md:grid-cols-[1fr_22rem] xl:grid-cols-[1fr_26rem]">
            {{-- ========== Catalogue ========== --}}
            <section id="zone-catalogue" aria-label="Catalogue" class="min-w-0" x-bind:class="onglet === 'catalogue' ? '' : 'max-md:hidden'">
                <div class="relative">
                    <label for="caisse-recherche" class="sr-only">Rechercher un produit (nom, référence ou code-barres)</label>
                    <x-icone nom="scan-barcode" taille="size-5" class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-texte-doux" />
                    <input id="caisse-recherche" x-ref="recherche" type="search" autocomplete="off" enterkeyhint="search"
                           x-model="recherche" x-on:input="saisir()" x-on:keydown.enter.prevent="valider()"
                           placeholder="Nom, référence ou code-barres (scanner + Entrée)…"
                           class="h-12 w-full rounded-controle border border-bordure-forte bg-surface pl-11 pr-16 text-base text-texte placeholder:text-texte-doux">
                    <kbd class="{{ $classeTouche }} absolute right-3 top-1/2 hidden -translate-y-1/2 sm:inline-flex">F2</kbd>
                </div>

                @if ($categories->isNotEmpty())
                    <div role="group" aria-label="Catégories" class="-mx-1 mt-3 flex gap-2 overflow-x-auto px-1 pb-1 [scrollbar-width:thin]">
                        <button type="button" class="{{ $classePuce }}" x-on:click="categorie = null; charger()" x-bind:aria-pressed="(categorie === null).toString()" aria-pressed="true">Tout</button>
                        @foreach ($categories as $categorieProduit)
                            <button type="button" class="{{ $classePuce }}" x-on:click="choisirCategorie({{ $categorieProduit->id }})"
                                    x-bind:aria-pressed="(categorie === {{ $categorieProduit->id }}).toString()" aria-pressed="false">{{ $categorieProduit->nom }}</button>
                        @endforeach
                    </div>
                @endif

                <div class="mt-4" x-bind:aria-busy="chargement.toString()">
                    {{-- Squelettes au premier chargement --}}
                    <div x-show="chargement && produits.length === 0" class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4" aria-hidden="true">
                        @for ($i = 0; $i < 8; $i++)
                            <div class="h-52 animate-squelette rounded-carte bg-neutre-doux"></div>
                        @endfor
                    </div>

                    <div x-show="! chargement && produits.length === 0" x-cloak>
                        <x-carte>
                            <x-etat-vide icone="search" titre="Aucun produit" texte="Aucun produit actif ne correspond à cette recherche.">
                                <x-bouton type="button" variante="secondaire" icone="x" x-on:click="recherche = ''; categorie = null; charger(); focusRecherche(true)">Effacer la recherche</x-bouton>
                            </x-etat-vide>
                        </x-carte>
                    </div>

                    <ul x-show="produits.length > 0" role="list" class="grid grid-cols-2 gap-3 transition-opacity duration-150 sm:grid-cols-3 xl:grid-cols-4"
                        x-bind:class="chargement ? 'opacity-60' : ''">
                        <template x-for="produit in produits" x-bind:key="produit.produit_id">
                            <li>
                                <button type="button" x-on:click="ajouter(produit)" x-bind:disabled="enRupture(produit)"
                                        x-bind:aria-label="'Ajouter ' + produit.nom + ', ' + ar(produit.prix_vente) + (enRupture(produit) ? ' (rupture)' : '')"
                                        class="group flex h-full w-full flex-col overflow-hidden rounded-carte border border-bordure bg-surface text-left shadow-sm transition duration-150 hover:-translate-y-0.5 hover:border-primaire hover:shadow-md active:translate-y-0 disabled:cursor-not-allowed disabled:opacity-55 disabled:grayscale disabled:hover:translate-y-0 disabled:hover:border-bordure">
                                    <span class="grid aspect-[4/3] w-full place-items-center bg-fond text-texte-doux">
                                        <template x-if="produit.photo">
                                            <img x-bind:src="produit.photo" alt="" loading="lazy" decoding="async" class="size-full object-cover">
                                        </template>
                                        <template x-if="! produit.photo">
                                            <x-icone nom="package" taille="size-8" />
                                        </template>
                                    </span>
                                    <span class="flex flex-1 flex-col gap-1.5 p-3">
                                        <span class="line-clamp-2 text-sm font-medium leading-snug text-texte" x-text="produit.nom"></span>
                                        <span class="mt-auto flex flex-wrap items-center justify-between gap-1.5">
                                            <span class="chiffres text-base font-semibold text-texte" x-text="ar(produit.prix_vente)"></span>
                                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                                                  x-bind:class="{
                                                      'bg-succes-doux text-succes-texte': produit.etat === 'normal',
                                                      'bg-alerte-doux text-alerte-texte': produit.etat === 'faible',
                                                      'bg-danger-doux text-danger-texte': produit.etat === 'rupture',
                                                  }">
                                                <span class="chiffres" x-text="produit.etat === 'rupture' ? 'Rupture' : qte(produit.stock, produit.unite)"></span>
                                            </span>
                                        </span>
                                    </span>
                                </button>
                            </li>
                        </template>
                    </ul>
                </div>
            </section>

            {{-- ========== Ticket ========== --}}
            <section id="zone-ticket" aria-label="Ticket en cours" x-bind:class="onglet === 'ticket' ? '' : 'max-md:hidden'"
                     class="md:sticky md:top-20">
                <div class="flex flex-col rounded-carte border border-bordure bg-surface shadow-sm md:max-h-[calc(100dvh-6.5rem)]">
                    {{-- Client --}}
                    <div class="flex items-center justify-between gap-3 border-b border-bordure p-4">
                        <div class="min-w-0">
                            <p class="text-xs text-texte-doux">Client</p>
                            <p class="truncate font-semibold" x-text="client.nom"></p>
                            <p x-show="! client.comptoir && client.plafond !== null" x-cloak class="chiffres text-xs text-texte-doux"
                               x-text="'Crédit disponible : ' + ar(client.disponible)"></p>
                        </div>
                        <x-bouton type="button" variante="secondaire" taille="sm" icone="user-round-search" x-on:click="ouvrirClient()">
                            Changer <kbd class="{{ $classeTouche }} ml-1 hidden sm:inline-flex">F4</kbd>
                        </x-bouton>
                    </div>

                    {{-- Lignes --}}
                    <div class="min-h-40 flex-1 overflow-y-auto">
                        <div x-show="lignes.length === 0" class="px-4 py-10">
                            <x-etat-vide icone="shopping-cart" titre="Ticket vide" texte="Touchez un produit ou scannez un code-barres pour l'ajouter." />
                        </div>
                        <ul role="list" class="divide-y divide-bordure">
                            <template x-for="(ligne, index) in lignes" x-bind:key="ligne.cle">
                                <li class="animate-glissement px-4 py-3">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium" x-text="ligne.nom"></p>
                                            <p class="chiffres text-xs text-texte-doux">
                                                <span x-text="ar(prix(ligne))"></span> / <span x-text="ligne.unite || 'unité'"></span>
                                            </p>
                                        </div>
                                        <p class="chiffres shrink-0 text-sm font-semibold" x-text="ar(totalLigne(ligne))"></p>
                                    </div>
                                    <div class="mt-2 flex flex-wrap items-center gap-2">
                                        <div class="inline-flex items-center rounded-controle border border-bordure-forte" role="group" x-bind:aria-label="'Quantité de ' + ligne.nom">
                                            <button type="button" x-on:click="changerQuantite(ligne, -1)" x-bind:aria-label="'Diminuer la quantité de ' + ligne.nom"
                                                    class="grid size-10 place-items-center rounded-l-controle text-texte-doux hover:bg-neutre-doux hover:text-texte pointer-coarse:size-11">
                                                <x-icone nom="minus" taille="size-4" />
                                            </button>
                                            <input type="number" min="0" step="any" inputmode="decimal" x-model="ligne.quantite"
                                                   x-bind:aria-label="'Quantité de ' + ligne.nom" x-bind:aria-invalid="quantiteInvalide(ligne).toString()"
                                                   class="chiffres h-10 w-16 border-x border-bordure-forte bg-transparent text-center text-sm aria-invalid:text-danger-texte pointer-coarse:h-11">
                                            <button type="button" x-on:click="changerQuantite(ligne, 1)" x-bind:aria-label="'Augmenter la quantité de ' + ligne.nom"
                                                    class="grid size-10 place-items-center rounded-r-controle text-texte-doux hover:bg-neutre-doux hover:text-texte pointer-coarse:size-11">
                                                <x-icone nom="plus" taille="size-4" />
                                            </button>
                                        </div>
                                        <template x-if="ligne.prix_gros !== null">
                                            <div class="inline-flex rounded-controle border border-bordure-forte p-0.5 text-xs" role="group" x-bind:aria-label="'Tarif de ' + ligne.nom">
                                                <button type="button" x-on:click="ligne.tarif = 'detail'" x-bind:aria-pressed="(ligne.tarif === 'detail').toString()"
                                                        class="h-9 rounded-[calc(var(--radius-controle)-2px)] px-2.5 font-medium text-texte-doux aria-pressed:bg-primaire-doux aria-pressed:text-lien pointer-coarse:h-10">Détail</button>
                                                <button type="button" x-on:click="ligne.tarif = 'gros'" x-bind:aria-pressed="(ligne.tarif === 'gros').toString()"
                                                        class="h-9 rounded-[calc(var(--radius-controle)-2px)] px-2.5 font-medium text-texte-doux aria-pressed:bg-primaire-doux aria-pressed:text-lien pointer-coarse:h-10">Gros</button>
                                            </div>
                                        </template>
                                        <button type="button" x-on:click="retirer(index)" x-bind:aria-label="'Retirer ' + ligne.nom + ' du ticket'"
                                                class="ml-auto grid size-10 place-items-center rounded-controle text-texte-doux hover:bg-danger-doux hover:text-danger-texte pointer-coarse:size-11">
                                            <x-icone nom="trash-2" taille="size-4" />
                                        </button>
                                    </div>
                                    @if ($remise['autorisee'])
                                        <label class="mt-2 flex items-center justify-end gap-2 text-xs text-texte-doux">
                                            Remise sur la ligne
                                            <span class="flex h-9 w-32 overflow-hidden rounded-controle border border-bordure-forte bg-surface focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-anneau">
                                                <input type="number" min="0" step="100" inputmode="numeric" x-model="ligne.remise" placeholder="0"
                                                       x-bind:aria-label="'Remise sur ' + ligne.nom"
                                                       class="chiffres min-w-0 flex-1 bg-transparent px-2 text-right text-sm text-texte focus:outline-none">
                                                <span class="grid place-items-center border-l border-bordure px-2 text-texte-doux">Ar</span>
                                            </span>
                                        </label>
                                    @endif
                                    <p x-show="quantiteInvalide(ligne)" x-cloak class="mt-1 text-xs text-danger-texte"
                                       x-text="messageQuantite(ligne)"></p>
                                </li>
                            </template>
                        </ul>
                    </div>

                    {{-- Totaux --}}
                    <div class="space-y-1.5 border-t border-bordure p-4 text-sm">
                        <div class="flex justify-between"><span class="text-texte-doux">Sous-total</span><span class="chiffres" x-text="ar(sousTotal)"></span></div>
                        <div class="flex items-center justify-between">
                            <button type="button" x-on:click="ouvrirRemise()" class="inline-flex min-h-8 items-center gap-1.5 text-texte-doux hover:text-texte">
                                Remise <kbd class="{{ $classeTouche }} hidden sm:inline-flex">F8</kbd>
                            </button>
                            <span class="chiffres" x-text="remises > 0 ? '− ' + ar(remises) : '—'"></span>
                        </div>
                        <p x-show="remiseTropForte" x-cloak role="alert" class="text-xs text-danger-texte"
                           x-text="'Remise au-delà du plafond de ' + plafondRemise + ' % (' + ar(remiseMaximum) + ' maximum).'"></p>
                        <div class="flex items-end justify-between gap-3 pt-2">
                            <span class="text-base font-medium">Total</span>
                            <span class="chiffres inline-block origin-right text-4xl font-bold tracking-tight text-texte xl:text-5xl"
                                  x-bind:class="battement ? 'animate-pulsation' : ''" x-on:animationend="battement = false"
                                  x-text="ar(totalAffiche)" aria-live="polite"></span>
                        </div>
                        <p class="chiffres text-right text-xs text-texte-doux" x-text="lignes.length + ' ligne(s) · ' + qte(articles) + ' article(s)'"></p>
                    </div>

                    <div class="grid grid-cols-[auto_1fr] gap-2 border-t border-bordure p-4">
                        <x-bouton type="button" variante="secondaire" icone="x" x-bind:disabled="lignes.length === 0"
                                  x-on:click="$dispatch('ouvrir-modal', 'caisse-abandon')" aria-label="Annuler la vente (Échap)">
                            <span class="sr-only sm:not-sr-only">Annuler</span>
                        </x-bouton>
                        <x-bouton type="button" taille="lg" icone="banknote" class="w-full" x-bind:disabled="lignes.length === 0" x-on:click="ouvrirPaiement()">
                            Encaisser <kbd class="ml-1 hidden rounded-md bg-primaire-texte/15 px-1.5 py-0.5 font-mono text-xs sm:inline">F9</kbd>
                        </x-bouton>
                    </div>
                </div>
            </section>
        </div>

        {{-- ========== Paiement (F9) ========== --}}
        <x-modal id="caisse-paiement" titre="Paiement" taille="lg">
            <div class="space-y-5">
                <div class="flex items-end justify-between gap-3 rounded-controle bg-fond p-4">
                    <div>
                        <p class="text-sm text-texte-doux">Total à payer</p>
                        <p class="text-xs text-texte-doux" x-text="client.nom"></p>
                    </div>
                    <p class="chiffres text-3xl font-bold sm:text-4xl" x-text="ar(total)"></p>
                </div>

                <fieldset>
                    <legend class="mb-2 text-sm font-medium">Mode de paiement</legend>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-5">
                        @foreach ($modes as $valeur => [$libelle, $icone])
                            <button type="button" x-on:click="choisirMode('{{ $valeur }}')" x-bind:aria-pressed="(mode === '{{ $valeur }}').toString()"
                                    @if ($valeur === 'credit') x-bind:disabled="refusCreditSansReste()" x-bind:title="refusCreditSansReste() ? (client.comptoir ? 'Crédit interdit pour le Client comptoir' : 'Pas de plafond de crédit pour ce client') : null" @endif
                                    class="flex min-h-20 flex-col items-center justify-center gap-1.5 rounded-controle border border-bordure-forte p-3 text-sm font-medium text-texte-doux transition-colors duration-150 hover:border-primaire hover:text-texte aria-pressed:border-primaire aria-pressed:bg-primaire-doux aria-pressed:text-lien disabled:cursor-not-allowed disabled:opacity-45 disabled:hover:border-bordure-forte">
                                <x-icone :nom="$icone" taille="size-6" />
                                {{ $libelle }}
                            </button>
                        @endforeach
                    </div>
                    <p x-show="refusCreditSansReste()" x-cloak class="mt-2 text-xs text-texte-doux"
                       x-text="client.comptoir ? 'Crédit indisponible pour le Client comptoir (F4 pour choisir un client).' : 'Crédit indisponible : ce client n\'a pas de plafond de crédit.'"></p>
                </fieldset>

                <div x-show="mode !== 'credit'" class="space-y-3">
                    <label for="caisse-recu" class="block text-sm font-medium">Montant reçu</label>
                    <div class="flex h-14 overflow-hidden rounded-controle border border-bordure-forte bg-surface focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-anneau">
                        <input id="caisse-recu" type="number" min="0" step="100" inputmode="numeric" x-model="recu" x-on:input="billetsSaisis = true"
                               class="chiffres min-w-0 flex-1 bg-transparent px-4 text-right text-2xl font-semibold text-texte focus:outline-none">
                        <span class="grid place-items-center border-l border-bordure px-4 text-texte-doux">Ar</span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" x-on:click="montantExact()" class="{{ $classePuce }}">Montant exact</button>
                        <template x-if="mode === 'especes'">
                            <div class="contents">
                                @foreach ($billets as $billet)
                                    <button type="button" x-on:click="ajouterBillet({{ $billet }})" class="{{ $classePuce }} chiffres">+ {{ number_format($billet, 0, ',', ' ') }}</button>
                                @endforeach
                            </div>
                        </template>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div x-show="mode === 'especes'" class="rounded-controle p-4" x-bind:class="monnaie > 0 ? 'bg-succes-doux text-succes-texte' : 'bg-fond text-texte-doux'">
                        <p class="text-sm">Monnaie à rendre</p>
                        <p class="chiffres text-3xl font-bold" x-text="ar(monnaie)" aria-live="polite"></p>
                    </div>
                    <div class="rounded-controle p-4" x-bind:class="reste > 0 ? 'bg-alerte-doux text-alerte-texte' : 'bg-fond text-texte-doux'">
                        <p class="text-sm">Reste à payer (crédit)</p>
                        <p class="chiffres text-3xl font-bold" x-text="ar(reste)" aria-live="polite"></p>
                    </div>
                </div>

                <p x-show="refusPaiement" x-cloak class="flex items-start gap-2 rounded-controle bg-danger-doux p-3 text-sm text-danger-texte">
                    <x-icone nom="circle-alert" taille="size-4" class="mt-0.5 shrink-0" /> <span x-text="refusPaiement"></span>
                </p>
                <p x-show="erreur" x-cloak role="alert" class="flex items-start gap-2 rounded-controle border border-danger bg-danger-doux p-3 text-sm font-medium text-danger-texte">
                    <x-icone nom="circle-x" taille="size-4" class="mt-0.5 shrink-0" /> <span x-text="erreur"></span>
                </p>
            </div>

            <x-slot:pied>
                <x-bouton type="button" variante="secondaire" x-on:click="$dispatch('fermer-modal', 'caisse-paiement')">Retour</x-bouton>
                <x-bouton type="button" icone="circle-check" x-on:click="enregistrer()" x-bind:disabled="! peutValider"
                          x-bind:data-chargement="envoi.toString()" x-bind:aria-busy="envoi.toString()">
                    Valider la vente <kbd class="ml-1 hidden rounded-md bg-primaire-texte/15 px-1.5 py-0.5 font-mono text-xs sm:inline">Ctrl+Entrée</kbd>
                </x-bouton>
            </x-slot:pied>
        </x-modal>

        {{-- ========== Client (F4) ========== --}}
        <x-modal id="caisse-client" titre="Choisir le client" description="Le Client comptoir ne peut pas acheter à crédit.">
            <div class="space-y-3">
                <div class="relative">
                    <label for="caisse-recherche-client" class="sr-only">Rechercher un client</label>
                    <x-icone nom="search" taille="size-4" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-texte-doux" />
                    <input id="caisse-recherche-client" type="search" autocomplete="off" autofocus x-model="rechercheClient"
                           x-on:input.debounce.250ms="chercherClients()" placeholder="Nom ou téléphone…"
                           class="h-11 w-full rounded-controle border border-bordure-forte bg-surface pl-9 pr-3 text-sm text-texte">
                </div>
                <ul role="list" class="max-h-80 divide-y divide-bordure overflow-y-auto rounded-controle border border-bordure" x-bind:aria-busy="chargementClients.toString()">
                    <template x-for="trouve in clientsTrouves" x-bind:key="trouve.id">
                        <li>
                            <button type="button" x-on:click="choisirClient(trouve)" x-bind:aria-current="(trouve.id === client.id).toString()"
                                    class="flex min-h-12 w-full items-center justify-between gap-3 px-4 py-2.5 text-left hover:bg-neutre-doux aria-[current=true]:bg-primaire-doux">
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium" x-text="trouve.nom"></span>
                                    <span class="block text-xs text-texte-doux" x-text="trouve.telephone || (trouve.comptoir ? 'Vente sans client identifié' : '')"></span>
                                </span>
                                <span class="chiffres shrink-0 text-right text-xs text-texte-doux"
                                      x-text="trouve.comptoir ? 'Pas de crédit' : (trouve.plafond === null ? 'Sans crédit' : 'Disponible : ' + ar(trouve.disponible))"></span>
                            </button>
                        </li>
                    </template>
                    <li x-show="! chargementClients && clientsTrouves.length === 0" class="px-4 py-6 text-center text-sm text-texte-doux">Aucun client actif trouvé.</li>
                </ul>
            </div>
        </x-modal>

        {{-- ========== Remise (F8) ========== --}}
        @if ($remise['autorisee'])
            <x-modal id="caisse-remise" titre="Remise sur le ticket" taille="sm"
                     :description="'Plafond : '.str_replace('.', ',', (string) $remise['plafond']).' % du montant (remises des lignes comprises).'">
                <div class="space-y-4">
                    <div class="inline-flex rounded-controle border border-bordure-forte p-0.5 text-sm" role="group" aria-label="Type de remise">
                        <button type="button" x-on:click="remiseGlobale.type = 'montant'" x-bind:aria-pressed="(remiseGlobale.type === 'montant').toString()"
                                class="h-10 rounded-[calc(var(--radius-controle)-2px)] px-4 font-medium text-texte-doux aria-pressed:bg-primaire-doux aria-pressed:text-lien">Montant (Ar)</button>
                        <button type="button" x-on:click="remiseGlobale.type = 'pourcentage'" x-bind:aria-pressed="(remiseGlobale.type === 'pourcentage').toString()"
                                class="h-10 rounded-[calc(var(--radius-controle)-2px)] px-4 font-medium text-texte-doux aria-pressed:bg-primaire-doux aria-pressed:text-lien">Pourcentage</button>
                    </div>
                    <div>
                        <label for="caisse-remise-valeur" class="mb-1.5 block text-sm font-medium">Remise</label>
                        <div class="flex h-12 overflow-hidden rounded-controle border border-bordure-forte bg-surface focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-anneau">
                            <input id="caisse-remise-valeur" type="number" min="0" step="any" inputmode="decimal" autofocus x-model="remiseGlobale.valeur" placeholder="0"
                                   class="chiffres min-w-0 flex-1 bg-transparent px-3 text-right text-lg text-texte focus:outline-none">
                            <span class="grid place-items-center border-l border-bordure px-3 text-texte-doux" x-text="remiseGlobale.type === 'pourcentage' ? '%' : 'Ar'"></span>
                        </div>
                    </div>
                    <dl class="space-y-1 text-sm">
                        <div class="flex justify-between"><dt class="text-texte-doux">Remise sur le ticket</dt><dd class="chiffres" x-text="ar(remiseGlobaleMontant)"></dd></div>
                        <div class="flex justify-between"><dt class="text-texte-doux">Total des remises</dt><dd class="chiffres" x-text="ar(remises) + ' / ' + ar(remiseMaximum) + ' max.'"></dd></div>
                        <div class="flex justify-between font-semibold"><dt>Nouveau total</dt><dd class="chiffres" x-text="ar(total)"></dd></div>
                    </dl>
                    <p x-show="remiseTropForte" x-cloak role="alert" class="text-sm text-danger-texte">Remise au-delà du plafond autorisé.</p>
                </div>
                <x-slot:pied>
                    <x-bouton type="button" variante="secondaire" x-on:click="remiseGlobale.valeur = ''">Supprimer la remise</x-bouton>
                    <x-bouton type="button" x-bind:disabled="remiseTropForte" x-on:click="$dispatch('fermer-modal', 'caisse-remise')">Appliquer</x-bouton>
                </x-slot:pied>
            </x-modal>
        @endif

        {{-- ========== Annuler la vente en cours (Échap) ========== --}}
        <x-modal id="caisse-abandon" titre="Annuler la vente en cours ?" taille="sm" role="alertdialog"
                 description="Le ticket sera vidé. Aucune vente n'est enregistrée.">
            <x-slot:pied>
                <x-bouton type="button" variante="secondaire" autofocus x-on:click="$dispatch('fermer-modal', 'caisse-abandon')">Retour</x-bouton>
                <x-bouton type="button" variante="danger" icone="trash-2" x-on:click="viderTicket()">Vider le ticket</x-bouton>
            </x-slot:pied>
        </x-modal>

        {{-- ========== Aide (F1) ========== --}}
        <x-modal id="caisse-aide" titre="Raccourcis clavier" taille="sm">
            <dl class="divide-y divide-bordure text-sm">
                @foreach ([['F1', 'Afficher cette aide'], ['F2', 'Rechercher un produit'], ['Entrée', 'Ajouter le produit scanné ou trouvé'], ['F4', 'Choisir le client'], ['F8', 'Remise sur le ticket'], ['F9', 'Encaisser (paiement)'], ['Ctrl+Entrée', 'Valider la vente'], ['Échap', 'Fermer la fenêtre, ou annuler la vente']] as [$touche, $action])
                    <div class="flex items-center justify-between gap-4 py-2.5">
                        <dt class="text-texte-doux">{{ $action }}</dt>
                        <dd><kbd class="{{ $classeTouche }}">{{ $touche }}</kbd></dd>
                    </div>
                @endforeach
            </dl>
        </x-modal>

        {{-- ========== Réussite ========== --}}
        <div x-show="reussite" x-cloak x-transition.opacity.duration.150ms class="fixed inset-0 z-40 grid place-items-center bg-fond/70 p-4 backdrop-blur-sm"
             role="dialog" aria-modal="true" aria-labelledby="caisse-reussite-titre">
            <template x-if="reussite">
                <div class="verre w-full max-w-md animate-apparition rounded-carte border border-bordure p-8 text-center shadow-eleve">
                    <span class="mx-auto grid size-20 animate-coche place-items-center rounded-full bg-succes-doux text-succes">
                        <x-icone nom="circle-check-big" taille="size-11" />
                    </span>
                    <h2 id="caisse-reussite-titre" class="mt-5 text-xl font-semibold">Vente enregistrée</h2>
                    <p class="mt-1 text-sm text-texte-doux"><span x-text="reussite.numero"></span> · facture <span x-text="reussite.facture"></span></p>
                    <p class="chiffres mt-4 text-4xl font-bold" x-text="ar(reussite.total)"></p>
                    <div x-show="reussite.monnaie > 0" class="mt-4 rounded-controle bg-succes-doux p-3 text-succes-texte">
                        <p class="text-sm">Monnaie à rendre</p>
                        <p class="chiffres text-2xl font-bold" x-text="ar(reussite.monnaie)"></p>
                    </div>
                    <p x-show="reussite.reste > 0" class="chiffres mt-4 rounded-controle bg-alerte-doux p-3 text-sm font-medium text-alerte-texte"
                       x-text="'Reste à payer : ' + ar(reussite.reste) + ' (' + reussite.client + ')'"></p>
                    <div class="mt-6 grid gap-2 sm:grid-cols-2">
                        <x-bouton variante="secondaire" icone="printer" x-bind:href="reussite.url_ticket" href="#" target="_blank" x-ouvrir-pdf="reussite.url_ticket">Imprimer le ticket</x-bouton>
                        <x-bouton type="button" id="caisse-nouvelle-vente" icone="plus" x-on:click="nouvelleVente()">Nouvelle vente</x-bouton>
                    </div>
                    <a x-bind:href="reussite.url_vente" class="mt-4 inline-block text-sm font-medium text-lien underline-offset-4 hover:underline">Voir le détail de la vente</a>
                </div>
            </template>
        </div>
    </div>
    @endif
@endsection
