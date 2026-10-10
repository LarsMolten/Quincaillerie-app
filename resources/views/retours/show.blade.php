{{-- Détail d'un retour : document d'origine, motif, lignes, règlement (avoir / remboursement), mouvements, annulation --}}
@extends('layouts.app')

@php
    $client = $retour->type === \App\Enums\TypeRetour::Client;
    $annule = $retour->statut === \App\Enums\StatutRetour::Annule;
    $document = $retour->document();
    $tiers = $client ? $document?->client : $document?->fournisseur;
    $liste = $client ? 'retours.clients' : 'retours.fournisseurs';
@endphp

@section('titre', 'Retour '.$retour->numero)

@section('page')
    <x-entete-page :titre="'Retour '.$retour->numero"
                   :description="'Le '.$retour->date_retour->translatedFormat('j F Y').' · saisi par '.$retour->utilisateur->nom"
                   :fil="['Tableau de bord' => route('accueil'), ($client ? 'Retours clients' : 'Retours fournisseurs') => route($liste), $retour->numero => null]">
        <x-slot:actions>
            <x-bouton :href="route('retours.bon', $retour)" target="_blank" x-data x-ouvrir-pdf variante="secondaire" icone="printer">Bon de retour</x-bouton>
            @unless ($annule)
                <x-bouton type="button" variante="danger" icone="ban" x-data x-on:click="$dispatch('ouvrir-modal', 'modale-annulation')">Annuler le retour</x-bouton>
            @endunless
        </x-slot:actions>
    </x-entete-page>

    @if ($annule)
        <div role="note" class="mb-6 flex items-start gap-3 rounded-carte border border-danger bg-danger-doux p-4 text-sm text-danger-texte">
            <x-icone nom="ban" class="mt-0.5" />
            <div>
                <p class="font-semibold">Retour annulé — le stock et le reste à payer du document ont été rétablis.</p>
                @if ($annulation)
                    <p>Le {{ $annulation->created_at->translatedFormat('j F Y à H:i') }} par {{ $annulation->utilisateur?->nom ?? 'le système' }} : « {{ $annulation->details['motif'] ?? '' }} »</p>
                @endif
                @if ((float) $retour->montant_rembourse > 0)
                    <p class="mt-1 font-medium">{{ format_ar($retour->montant_rembourse) }} avaient été remboursés : {{ $client ? 'à récupérer auprès du client' : 'à reverser au fournisseur' }}.</p>
                @endif
            </div>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <x-carte class="lg:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm text-texte-doux">{{ $client ? 'Client' : 'Fournisseur' }}</p>
                    <p class="flex items-center gap-2 text-lg font-semibold"><x-avatar :nom="$tiers?->nom ?? '—'" taille="sm" /> {{ $tiers?->nom ?? '—' }}</p>
                    <p class="mt-1 text-sm text-texte-doux">
                        {{ $client ? 'Vente' : 'Achat' }} d'origine :
                        <a href="{{ $client ? route('ventes.show', $document) : route('achats.show', $document) }}" class="font-medium text-lien hover:underline">
                            {{ $client ? ($document->facture?->numero ?? $document->numero).' · '.$document->numero : $document->numero }}
                        </a>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-badge :statut="$retour->type" />
                    <x-badge :statut="$retour->statut" />
                </div>
            </div>
            <dl class="mt-6 grid gap-4 sm:grid-cols-3">
                <div class="rounded-controle bg-fond p-4"><dt class="text-sm text-texte-doux">Montant du retour</dt><dd class="chiffres text-2xl font-semibold">{{ format_ar($retour->total) }}</dd></div>
                <div class="rounded-controle bg-fond p-4"><dt class="text-sm text-texte-doux">{{ $client ? 'Déduit de la créance' : 'Déduit de la dette' }}</dt><dd class="chiffres text-2xl font-semibold">{{ format_ar($retour->montant_avoir) }}</dd></div>
                <div class="rounded-controle bg-fond p-4"><dt class="text-sm text-texte-doux">{{ $client ? 'Remboursé au client' : 'Remboursé par le fournisseur' }}</dt>
                    <dd class="chiffres text-2xl font-semibold">{{ format_ar($retour->montant_rembourse) }}</dd>
                    @if ($retour->mode_remboursement)<dd class="text-sm text-texte-doux">{{ $retour->mode_remboursement->libelle() }}</dd>@endif
                </div>
            </dl>
            <p class="mt-4 text-sm"><span class="font-medium">Motif :</span> <span class="text-texte-doux">{{ $retour->motif }}</span></p>
        </x-carte>

        {{-- Mouvements de stock générés --}}
        <x-carte titre="Mouvements de stock" :padding="false">
            <ul class="divide-y divide-bordure" role="list">
                @foreach ($retour->mouvementsStock->sortBy('id') as $mouvement)
                    <li class="flex items-center justify-between gap-3 px-carte py-3 text-sm">
                        <div class="min-w-0">
                            <x-badge :statut="$mouvement->type" />
                            <p class="mt-1 truncate text-xs text-texte-doux">{{ $retour->lignes->firstWhere('produit_id', $mouvement->produit_id)?->produit->nom }}</p>
                        </div>
                        <p @class(['chiffres font-semibold', 'text-succes-texte' => $mouvement->sens->value === 'entree', 'text-danger-texte' => $mouvement->sens->value === 'sortie'])>
                            {{ $mouvement->sens->value === 'entree' ? '+' : '−' }}{{ format_quantite($mouvement->quantite) }}
                        </p>
                    </li>
                @endforeach
            </ul>
        </x-carte>

        <x-carte titre="Produits retournés" class="lg:col-span-3" :padding="false">
            <x-tableau :colonnes="[
                'produit' => 'Produit',
                'quantite' => ['libelle' => 'Quantité', 'alignement' => 'droite'],
                'prix' => ['libelle' => 'Prix unitaire', 'alignement' => 'droite'],
                'total' => ['libelle' => 'Total', 'alignement' => 'droite'],
            ]" class="border-0 shadow-none">
                @foreach ($retour->lignes as $ligne)
                    <x-tableau.ligne>
                        <x-tableau.cellule libelle="Produit" principale>
                            <a href="{{ route('produits.show', $ligne->produit) }}" class="font-medium hover:underline">{{ $ligne->produit->nom }}</a>
                            <span class="block text-xs font-normal text-texte-doux">{{ $ligne->produit->reference }}</span>
                        </x-tableau.cellule>
                        <x-tableau.cellule libelle="Quantité" alignement="droite">{{ format_quantite($ligne->quantite, $ligne->produit->unite?->abreviation) }}</x-tableau.cellule>
                        <x-tableau.cellule libelle="Prix unitaire" alignement="droite">{{ format_ar($ligne->prix_unitaire) }}</x-tableau.cellule>
                        <x-tableau.cellule libelle="Total" alignement="droite" class="font-semibold">{{ format_ar($ligne->total) }}</x-tableau.cellule>
                    </x-tableau.ligne>
                @endforeach
            </x-tableau>
        </x-carte>
    </div>

    {{-- Annulation avec motif --}}
    @unless ($annule)
        <x-modal id="modale-annulation" titre="Annuler le retour {{ $retour->numero }} ?" taille="sm" role="alertdialog"
                 :description="$client ? 'Les produits ressortiront du stock et le montant déduit sera de nouveau dû par le client.' : 'Les produits reviendront en stock et le montant déduit sera de nouveau dû au fournisseur.'">
            <form method="POST" action="{{ route('retours.annuler', $retour) }}" x-chargement-envoi novalidate class="space-y-4">
                @csrf
                <x-textarea nom="motif" label="Motif de l'annulation" :lignes="3" maxlength="255" requis
                            placeholder="Ex. retour saisi par erreur, produit finalement repris…" />
                <div class="-mx-6 -mb-6 flex flex-col-reverse gap-3 border-t border-bordure px-6 py-4 sm:flex-row sm:justify-end">
                    <x-bouton type="button" variante="secondaire" autofocus x-on:click="$dispatch('fermer-modal', 'modale-annulation')">Retour</x-bouton>
                    <x-bouton variante="danger" icone="ban">Annuler le retour</x-bouton>
                </div>
            </form>
        </x-modal>
        @error('motif')
            <div x-data x-init="$nextTick(() => $dispatch('ouvrir-modal', 'modale-annulation'))"></div>
        @enderror
    @endunless
@endsection
