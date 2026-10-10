{{-- Détail d'un achat : totaux, lignes, paiements, mouvements de stock, paiement ultérieur et annulation --}}
@extends('layouts.app')

@section('titre', 'Achat '.$achat->numero)

@php
    $annule = $achat->statut === \App\Enums\StatutAchat::Annule;
    $peutPayer = ! $annule && (float) $achat->reste_a_payer > 0;
@endphp

@section('page')
    <x-entete-page :titre="'Achat '.$achat->numero"
                   :description="'Du '.$achat->date_achat->translatedFormat('j F Y').' · saisi par '.$achat->utilisateur->nom"
                   :fil="['Tableau de bord' => route('accueil'), 'Achats' => route('achats.index'), $achat->numero => null]">
        <x-slot:actions>
            <x-bouton :href="route('achats.bon', $achat)" target="_blank" x-data x-ouvrir-pdf variante="secondaire" icone="printer">Bon d'achat</x-bouton>
            @if ($peutPayer)
                @droit('paiements.gerer')
                    <x-bouton type="button" icone="banknote" x-data x-on:click="$dispatch('ouvrir-modal', 'modale-paiement')">Ajouter un paiement</x-bouton>
                @enddroit
            @endif
            @if (! $annule)
                @droit('achats.annuler')
                    <x-bouton type="button" variante="danger" icone="ban" x-data x-on:click="$dispatch('ouvrir-modal', 'modale-annulation')">Annuler l'achat</x-bouton>
                @enddroit
            @endif
        </x-slot:actions>
    </x-entete-page>

    @if ($annule)
        <div role="note" class="mb-6 flex items-start gap-3 rounded-carte border border-danger bg-danger-doux p-4 text-sm text-danger-texte">
            <x-icone nom="ban" class="mt-0.5" />
            <div>
                <p class="font-semibold">Achat annulé — le stock reçu a été retiré.</p>
                @if ($annulation)
                    <p>Le {{ $annulation->created_at->translatedFormat('j F Y à H:i') }} par {{ $annulation->utilisateur?->nom ?? 'le système' }} : « {{ $annulation->details['motif'] ?? '' }} »</p>
                @endif
                @if ((float) $achat->montant_paye > 0)
                    <p class="mt-1 font-medium">{{ format_ar($achat->montant_paye) }} déjà payés : à récupérer auprès du fournisseur.</p>
                @endif
            </div>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- Totaux --}}
        <x-carte class="lg:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm text-texte-doux">Fournisseur</p>
                    <a href="{{ route('fournisseurs.show', $achat->fournisseur) }}" class="flex items-center gap-2 text-lg font-semibold hover:underline">
                        <x-avatar :nom="$achat->fournisseur->nom" taille="sm" /> {{ $achat->fournisseur->nom }}
                    </a>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-badge :statut="$achat->statut" />
                    @unless ($annule)<x-badge :statut="$achat->statut_paiement" />@endunless
                </div>
            </div>
            <dl class="mt-6 grid gap-4 sm:grid-cols-3">
                <div class="rounded-controle bg-fond p-4"><dt class="text-sm text-texte-doux">Total</dt><dd class="chiffres text-2xl font-semibold">{{ format_ar($achat->total) }}</dd></div>
                <div class="rounded-controle bg-fond p-4"><dt class="text-sm text-texte-doux">Payé</dt><dd class="chiffres text-2xl font-semibold text-succes-texte">{{ format_ar($achat->montant_paye) }}</dd></div>
                <div class="rounded-controle bg-fond p-4"><dt class="text-sm text-texte-doux">Reste à payer</dt>
                    <dd @class(['chiffres text-2xl font-semibold', 'text-danger-texte' => $peutPayer, 'text-texte-doux' => ! $peutPayer])>{{ format_ar($annule ? 0 : $achat->reste_a_payer) }}</dd></div>
            </dl>
            @if ($achat->notes)
                <p class="mt-4 text-sm text-texte-doux"><span class="font-medium text-texte">Notes :</span> {{ $achat->notes }}</p>
            @endif
        </x-carte>

        {{-- Paiements --}}
        <x-carte titre="Paiements" :padding="false">
            @if ($achat->paiements->isEmpty())
                <x-etat-vide icone="banknote" titre="Aucun paiement" texte="Achat entièrement à crédit." />
            @else
                <ul class="divide-y divide-bordure" role="list">
                    @foreach ($achat->paiements as $paiement)
                        @include('paiements._element')
                    @endforeach
                </ul>
            @endif
        </x-carte>

        {{-- Lignes --}}
        <x-carte titre="Produits reçus" class="lg:col-span-2" :padding="false">
            <x-tableau :colonnes="[
                'produit' => 'Produit',
                'quantite' => ['libelle' => 'Quantité', 'alignement' => 'droite'],
                'prix' => ['libelle' => 'Prix d\'achat', 'alignement' => 'droite'],
                'total' => ['libelle' => 'Total', 'alignement' => 'droite'],
            ]" class="border-0 shadow-none">
                @foreach ($achat->lignes as $ligne)
                    <x-tableau.ligne>
                        <x-tableau.cellule libelle="Produit" principale>
                            <a href="{{ route('produits.show', $ligne->produit) }}" class="font-medium hover:underline">{{ $ligne->produit->nom }}</a>
                            <span class="block text-xs font-normal text-texte-doux">{{ $ligne->produit->reference }}</span>
                        </x-tableau.cellule>
                        <x-tableau.cellule libelle="Quantité" alignement="droite">{{ format_quantite($ligne->quantite, $ligne->produit->unite?->abreviation) }}</x-tableau.cellule>
                        <x-tableau.cellule libelle="Prix d'achat" alignement="droite">{{ format_ar($ligne->prix_achat) }}</x-tableau.cellule>
                        <x-tableau.cellule libelle="Total" alignement="droite" class="font-semibold">{{ format_ar($ligne->total) }}</x-tableau.cellule>
                    </x-tableau.ligne>
                @endforeach
            </x-tableau>
        </x-carte>

        {{-- Mouvements de stock générés --}}
        <x-carte titre="Mouvements de stock" :padding="false">
            <ul class="divide-y divide-bordure" role="list">
                @foreach ($achat->mouvementsStock->sortBy('id') as $mouvement)
                    <li class="flex items-center justify-between gap-3 px-carte py-3 text-sm">
                        <div class="min-w-0">
                            <x-badge :statut="$mouvement->type" />
                            <p class="mt-1 truncate text-xs text-texte-doux">{{ $achat->lignes->firstWhere('produit_id', $mouvement->produit_id)?->produit->nom }}</p>
                        </div>
                        <p @class(['chiffres font-semibold', 'text-succes-texte' => $mouvement->sens->value === 'entree', 'text-danger-texte' => $mouvement->sens->value === 'sortie'])>
                            {{ $mouvement->sens->value === 'entree' ? '+' : '−' }}{{ format_quantite($mouvement->quantite) }}
                        </p>
                    </li>
                @endforeach
            </ul>
        </x-carte>
    </div>

    {{-- Paiement ultérieur --}}
    @if ($peutPayer && auth()->user()->can('paiements.gerer'))
        <x-modal id="modale-paiement" titre="Ajouter un paiement" taille="sm"
                 :description="'Reste à payer : '.format_ar($achat->reste_a_payer)">
            <form method="POST" action="{{ route('achats.paiements.store', $achat) }}" x-chargement-envoi novalidate class="space-y-4">
                @csrf
                <x-champ nom="montant" label="Montant" type="number" min="1" :max="(float) $achat->reste_a_payer" step="1" inputmode="numeric"
                         suffixe="Ar" montant :valeur="old('montant', (float) $achat->reste_a_payer)" requis />
                <x-select nom="mode" label="Mode de paiement" :valeur="old('mode', 'especes')"
                          :options="collect($modes)->mapWithKeys(fn ($m) => [$m->value => $m->libelle()])" requis />
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
    @if (! $annule && auth()->user()->can('achats.annuler'))
        <x-modal id="modale-annulation" titre="Annuler l'achat {{ $achat->numero }} ?" taille="sm" role="alertdialog"
                 description="Les quantités reçues seront retirées du stock. Impossible si une partie a déjà été vendue.">
            <form method="POST" action="{{ route('achats.annuler', $achat) }}" x-chargement-envoi novalidate class="space-y-4">
                @csrf
                <x-textarea nom="motif" label="Motif de l'annulation" :lignes="3" maxlength="255" requis
                            placeholder="Ex. erreur de saisie, marchandise refusée…" />
                <div class="-mx-6 -mb-6 flex flex-col-reverse gap-3 border-t border-bordure px-6 py-4 sm:flex-row sm:justify-end">
                    <x-bouton type="button" variante="secondaire" autofocus x-on:click="$dispatch('fermer-modal', 'modale-annulation')">Retour</x-bouton>
                    <x-bouton variante="danger" icone="ban">Annuler l'achat</x-bouton>
                </div>
            </form>
        </x-modal>
        @error('motif')
            <div x-data x-init="$nextTick(() => $dispatch('ouvrir-modal', 'modale-annulation'))"></div>
        @enderror
    @endif
@endsection
