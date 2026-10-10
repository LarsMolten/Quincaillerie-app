{{-- Détail d'une vente : totaux, lignes, paiements, facture, mouvements de stock et annulation --}}
@extends('layouts.app')

@section('titre', 'Vente '.$vente->numero)

@php
    $annulee = $vente->statut === \App\Enums\StatutVente::Annulee;
    $client = $vente->client;
    $peutEncaisser = ! $annulee && (float) $vente->reste_a_payer > 0 && auth()->user()->can('paiements.gerer');
@endphp

@section('page')
    <x-entete-page :titre="'Vente '.$vente->numero"
                   :description="'Le '.$vente->date_vente->translatedFormat('j F Y à H:i').' · vendeur : '.$vente->utilisateur->nom"
                   :fil="['Tableau de bord' => route('accueil'), 'Ventes' => route('ventes.index'), $vente->numero => null]">
        <x-slot:actions>
            <x-bouton :href="route('ventes.ticket', $vente)" target="_blank" x-data x-ouvrir-pdf variante="secondaire" icone="printer">Ticket</x-bouton>
            @if ($peutEncaisser)
                <x-bouton type="button" icone="hand-coins" x-data x-on:click="$dispatch('ouvrir-modal', 'modale-paiement')">Encaisser</x-bouton>
            @endif
            @if (! $annulee)
                @droit('retours.gerer')
                    <x-bouton :href="route('retours.create', ['vente' => $vente->id])" variante="secondaire" icone="undo-2">Retour</x-bouton>
                @enddroit
                @droit('ventes.annuler')
                    <x-bouton type="button" variante="danger" icone="ban" x-data x-on:click="$dispatch('ouvrir-modal', 'modale-annulation')">Annuler la vente</x-bouton>
                @enddroit
            @endif
        </x-slot:actions>
    </x-entete-page>

    @if ($annulee)
        <div role="note" class="mb-6 flex items-start gap-3 rounded-carte border border-danger bg-danger-doux p-4 text-sm text-danger-texte">
            <x-icone nom="ban" class="mt-0.5" />
            <div>
                <p class="font-semibold">Vente annulée — les produits ont été remis en stock et la facture est annulée.</p>
                @if ($annulation)
                    <p>Le {{ $annulation->created_at->translatedFormat('j F Y à H:i') }} par {{ $annulation->utilisateur?->nom ?? 'le système' }} : « {{ $annulation->details['motif'] ?? '' }} »</p>
                @endif
                @if ((float) $vente->montant_paye > 0)
                    <p class="mt-1 font-medium">{{ format_ar($vente->montant_paye) }} déjà encaissés : à rembourser au client.</p>
                @endif
            </div>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- Totaux --}}
        <x-carte class="lg:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm text-texte-doux">Client</p>
                    @if ($client && ! $client->estComptoir() && ! $client->trashed())
                        <a href="{{ route('clients.show', $client) }}" class="flex items-center gap-2 text-lg font-semibold hover:underline">
                            <x-avatar :nom="$client->nom" taille="sm" /> {{ $client->nom }}
                        </a>
                    @else
                        <p class="flex items-center gap-2 text-lg font-semibold"><x-avatar :nom="$client?->nom ?? 'Client'" taille="sm" /> {{ $client?->nom ?? '—' }}</p>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-badge :statut="$vente->statut" />
                    @unless ($annulee)<x-badge :statut="$vente->statut_paiement" />@endunless
                    <x-badge :statut="$vente->mode_paiement" />
                </div>
            </div>
            <dl class="mt-6 grid gap-4 sm:grid-cols-3">
                <div class="rounded-controle bg-fond p-4"><dt class="text-sm text-texte-doux">Total</dt><dd class="chiffres text-2xl font-semibold">{{ format_ar($vente->total) }}</dd></div>
                <div class="rounded-controle bg-fond p-4"><dt class="text-sm text-texte-doux">Payé</dt><dd class="chiffres text-2xl font-semibold text-succes-texte">{{ format_ar($vente->montant_paye) }}</dd></div>
                <div class="rounded-controle bg-fond p-4"><dt class="text-sm text-texte-doux">Reste à payer</dt>
                    <dd @class(['chiffres text-2xl font-semibold', 'text-danger-texte' => ! $annulee && $vente->reste_a_payer > 0, 'text-texte-doux' => $annulee || $vente->reste_a_payer <= 0])>{{ format_ar($annulee ? 0 : $vente->reste_a_payer) }}</dd></div>
            </dl>
            <dl class="mt-4 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                <div class="flex justify-between gap-3"><dt class="text-texte-doux">Sous-total</dt><dd class="chiffres">{{ format_ar($vente->sous_total) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-texte-doux">Remise sur le ticket</dt><dd class="chiffres">{{ (float) $vente->remise > 0 ? '− '.format_ar($vente->remise) : '—' }}</dd></div>
                @if ($vente->montant_recu !== null)
                    <div class="flex justify-between gap-3"><dt class="text-texte-doux">Montant reçu</dt><dd class="chiffres">{{ format_ar($vente->montant_recu) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-texte-doux">Monnaie rendue</dt><dd class="chiffres">{{ format_ar($vente->monnaie_rendue) }}</dd></div>
                @endif
            </dl>
            @if ($vente->notes)
                <p class="mt-4 text-sm text-texte-doux"><span class="font-medium text-texte">Notes :</span> {{ $vente->notes }}</p>
            @endif
        </x-carte>

        {{-- Facture et paiements --}}
        <x-carte titre="Facture et paiements" :padding="false">
            @if ($vente->facture)
                <div class="flex items-center justify-between gap-3 border-b border-bordure px-carte py-3 text-sm">
                    <div>
                        @droit('factures.voir')
                            <a href="{{ route('factures.index', ['apercu' => $vente->facture->id]) }}" class="font-medium hover:underline">Facture {{ $vente->facture->numero }}</a>
                        @else
                            <p class="font-medium">Facture {{ $vente->facture->numero }}</p>
                        @enddroit
                        <p class="text-xs text-texte-doux">Émise le {{ $vente->facture->date_emission->translatedFormat('j M Y, H:i') }}</p>
                    </div>
                    <x-badge :statut="$vente->facture->statut" />
                </div>
            @endif
            @if ($vente->paiements->isEmpty())
                <x-etat-vide icone="banknote" titre="Aucun paiement" texte="Vente entièrement à crédit." />
            @else
                <ul class="divide-y divide-bordure" role="list">
                    @foreach ($vente->paiements as $paiement)
                        @include('paiements._element')
                    @endforeach
                </ul>
            @endif
        </x-carte>

        {{-- Lignes --}}
        <x-carte titre="Produits vendus" class="lg:col-span-2" :padding="false">
            <x-tableau :colonnes="[
                'produit' => 'Produit',
                'quantite' => ['libelle' => 'Quantité', 'alignement' => 'droite'],
                'prix' => ['libelle' => 'Prix unitaire', 'alignement' => 'droite'],
                'remise' => ['libelle' => 'Remise', 'alignement' => 'droite'],
                'total' => ['libelle' => 'Total', 'alignement' => 'droite'],
            ]" class="border-0 shadow-none">
                @foreach ($vente->lignes as $ligne)
                    <x-tableau.ligne>
                        <x-tableau.cellule libelle="Produit" principale>
                            <a href="{{ route('produits.show', $ligne->produit) }}" class="font-medium hover:underline">{{ $ligne->produit->nom }}</a>
                            <span class="block text-xs font-normal text-texte-doux">{{ $ligne->produit->reference }}</span>
                        </x-tableau.cellule>
                        <x-tableau.cellule libelle="Quantité" alignement="droite">{{ format_quantite($ligne->quantite, $ligne->produit->unite?->abreviation) }}</x-tableau.cellule>
                        <x-tableau.cellule libelle="Prix unitaire" alignement="droite">{{ format_ar($ligne->prix_unitaire) }}</x-tableau.cellule>
                        <x-tableau.cellule libelle="Remise" alignement="droite" class="text-texte-doux">{{ (float) $ligne->remise > 0 ? '− '.format_ar($ligne->remise) : '—' }}</x-tableau.cellule>
                        <x-tableau.cellule libelle="Total" alignement="droite" class="font-semibold">{{ format_ar($ligne->total) }}</x-tableau.cellule>
                    </x-tableau.ligne>
                @endforeach
            </x-tableau>
        </x-carte>

        {{-- Mouvements de stock générés --}}
        <x-carte titre="Mouvements de stock" :padding="false">
            <ul class="divide-y divide-bordure" role="list">
                @foreach ($vente->mouvementsStock->sortBy('id') as $mouvement)
                    <li class="flex items-center justify-between gap-3 px-carte py-3 text-sm">
                        <div class="min-w-0">
                            <x-badge :statut="$mouvement->type" />
                            <p class="mt-1 truncate text-xs text-texte-doux">{{ $vente->lignes->firstWhere('produit_id', $mouvement->produit_id)?->produit->nom }}</p>
                        </div>
                        <p @class(['chiffres font-semibold', 'text-succes-texte' => $mouvement->sens->value === 'entree', 'text-danger-texte' => $mouvement->sens->value === 'sortie'])>
                            {{ $mouvement->sens->value === 'entree' ? '+' : '−' }}{{ format_quantite($mouvement->quantite) }}
                        </p>
                    </li>
                @endforeach
            </ul>
        </x-carte>

        @include('retours._carte-document', ['retours' => $vente->retours])
    </div>

    {{-- Encaissement ultérieur (vente à crédit ou partielle) --}}
    @if ($peutEncaisser)
        <x-modal id="modale-paiement" titre="Encaisser" taille="sm"
                 :description="'Reste à payer : '.format_ar($vente->reste_a_payer)">
            <form method="POST" action="{{ route('paiements.ventes.store', $vente) }}" x-chargement-envoi novalidate class="space-y-4">
                @csrf
                <x-champ nom="montant" label="Montant" type="number" min="1" :max="(float) $vente->reste_a_payer" step="1" inputmode="numeric"
                         suffixe="Ar" montant :valeur="old('montant', (float) $vente->reste_a_payer)" requis />
                <x-select nom="mode" label="Mode de paiement" :valeur="old('mode', 'especes')"
                          :options="collect(\App\Enums\ModePaiement::encaissements())->mapWithKeys(fn ($m) => [$m->value => $m->libelle()])" requis />
                <x-champ nom="reference" label="Référence" placeholder="N° de chèque, de transaction…" :valeur="old('reference')" />
                <x-champ nom="date_paiement" label="Date" type="date" :valeur="old('date_paiement', today()->toDateString())" max="{{ today()->toDateString() }}" requis />
                <div class="-mx-6 -mb-6 flex flex-col-reverse gap-3 border-t border-bordure px-6 py-4 sm:flex-row sm:justify-end">
                    <x-bouton type="button" variante="secondaire" x-on:click="$dispatch('fermer-modal', 'modale-paiement')">Annuler</x-bouton>
                    <x-bouton icone="save">Enregistrer le paiement</x-bouton>
                </div>
            </form>
        </x-modal>
        @if ($errors->hasAny(['montant', 'mode', 'reference', 'date_paiement']))
            <div x-data x-init="$nextTick(() => $dispatch('ouvrir-modal', 'modale-paiement'))"></div>
        @endif
    @endif

    {{-- Annulation avec motif --}}
    @if (! $annulee && auth()->user()->can('ventes.annuler'))
        <x-modal id="modale-annulation" titre="Annuler la vente {{ $vente->numero }} ?" taille="sm" role="alertdialog"
                 description="Les produits seront remis en stock et la facture sera annulée.">
            <form method="POST" action="{{ route('ventes.annuler', $vente) }}" x-chargement-envoi novalidate class="space-y-4">
                @csrf
                <x-textarea nom="motif" label="Motif de l'annulation" :lignes="3" maxlength="255" requis
                            placeholder="Ex. erreur de caisse, client a renoncé…" />
                <div class="-mx-6 -mb-6 flex flex-col-reverse gap-3 border-t border-bordure px-6 py-4 sm:flex-row sm:justify-end">
                    <x-bouton type="button" variante="secondaire" autofocus x-on:click="$dispatch('fermer-modal', 'modale-annulation')">Retour</x-bouton>
                    <x-bouton variante="danger" icone="ban">Annuler la vente</x-bouton>
                </div>
            </form>
        </x-modal>
        @error('motif')
            <div x-data x-init="$nextTick(() => $dispatch('ouvrir-modal', 'modale-annulation'))"></div>
        @enderror
    @endif
@endsection
