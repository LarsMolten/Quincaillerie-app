{{-- Fiche fournisseur : coordonnées, dette totale, historique des achats et des paiements --}}
@extends('layouts.app')

@section('titre', $fournisseur->nom)

@section('page')
    <x-entete-page :titre="$fournisseur->nom"
                   :fil="['Tableau de bord' => route('accueil'), 'Fournisseurs' => route('fournisseurs.index'), $fournisseur->nom => null]">
        <x-slot:actions>@include('fournisseurs._actions')</x-slot:actions>
    </x-entete-page>

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- Coordonnées --}}
        <x-carte class="lg:row-span-2">
            <div class="flex items-center gap-4">
                <x-avatar :nom="$fournisseur->nom" taille="lg" />
                <div class="min-w-0">
                    <p class="truncate text-lg font-semibold">{{ $fournisseur->nom }}</p>
                    <x-badge :couleur="$fournisseur->actif ? 'succes' : 'neutre'" class="mt-1">{{ $fournisseur->actif ? 'Actif' : 'Inactif' }}</x-badge>
                </div>
            </div>
            <dl class="mt-6 space-y-4 text-sm">
                @foreach ([
                    ['user', 'Contact', $fournisseur->contact, null],
                    ['phone', 'Téléphone', $fournisseur->telephone, 'tel:'.preg_replace('/[^0-9+]/', '', $fournisseur->telephone)],
                    ['mail', 'Email', $fournisseur->email, $fournisseur->email ? 'mailto:'.$fournisseur->email : null],
                    ['map-pin', 'Adresse', $fournisseur->adresse, null],
                ] as [$icone, $libelle, $valeur, $lien])
                    <div class="flex gap-3">
                        <x-icone :nom="$icone" taille="size-4" class="mt-0.5 text-texte-doux" />
                        <div class="min-w-0">
                            <dt class="text-texte-doux">{{ $libelle }}</dt>
                            <dd class="break-words font-medium">
                                @if ($valeur && $lien)<a href="{{ $lien }}" class="text-lien hover:underline">{{ $valeur }}</a>@else{{ $valeur ?: '—' }}@endif
                            </dd>
                        </div>
                    </div>
                @endforeach
            </dl>
        </x-carte>

        {{-- Dette : grande carte --}}
        <article class="flex flex-col justify-between gap-4 rounded-carte border border-bordure bg-surface p-carte shadow-doux lg:col-span-2">
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-medium text-texte-doux">Dette totale envers ce fournisseur</p>
                <span class="grid size-10 place-items-center rounded-controle bg-primaire-doux text-lien"><x-icone nom="wallet" /></span>
            </div>
            <p @class(['chiffres text-4xl font-semibold tracking-tight', 'text-danger-texte' => $dette > 0, 'text-succes-texte' => $dette <= 0])>
                {{ format_ar($dette) }}
            </p>
            <p class="text-sm text-texte-doux">
                @if ($dette > 0)
                    <span class="chiffres font-medium text-texte">{{ $achatsNonSoldes }}</span> achat{{ $achatsNonSoldes > 1 ? 's' : '' }} non soldé{{ $achatsNonSoldes > 1 ? 's' : '' }} (somme des restes à payer).
                @else
                    Tous les achats sont réglés.
                @endif
            </p>
        </article>

        <x-carte-stat libelle="Total acheté" :valeur="format_ar($totalAchete)" icone="truck" />
        <x-carte-stat libelle="Nombre d'achats" :valeur="$nombreAchats" icone="receipt" />

        {{-- Historique des achats --}}
        <x-carte titre="Historique des achats" class="lg:col-span-2" :padding="false">
            @if ($achats->isEmpty())
                <x-etat-vide icone="truck" titre="Aucun achat" texte="Les achats auprès de ce fournisseur apparaîtront ici." />
            @else
                <ul class="divide-y divide-bordure" role="list">
                    @foreach ($achats as $achat)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-carte py-3 text-sm">
                            <div>
                                <p class="font-medium">{{ $achat->numero }}</p>
                                <p class="text-xs text-texte-doux">{{ $achat->date_achat->translatedFormat('j M Y') }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <x-badge :statut="$achat->statut" />
                                @if ($achat->statut->value === 'valide')
                                    <x-badge :couleur="$achat->est_soldee ? 'succes' : 'alerte'">{{ $achat->est_soldee ? 'Soldé' : 'Non soldé' }}</x-badge>
                                @endif
                            </div>
                            <div class="text-right">
                                <p class="chiffres font-semibold">{{ format_ar($achat->total) }}</p>
                                @if ((float) $achat->reste_a_payer > 0)
                                    <p class="chiffres text-xs text-danger-texte">Reste {{ format_ar($achat->reste_a_payer) }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
                @if ($achats->hasPages())<div class="border-t border-bordure px-4 py-3">{{ $achats->links() }}</div>@endif
            @endif
        </x-carte>

        {{-- Historique des paiements --}}
        <x-carte titre="Paiements versés" description="Les 15 derniers" :padding="false">
            @if ($paiements->isEmpty())
                <x-etat-vide icone="banknote" titre="Aucun paiement" texte="Les règlements de ce fournisseur apparaîtront ici." />
            @else
                <ul class="divide-y divide-bordure" role="list">
                    @foreach ($paiements as $paiement)
                        <li class="flex items-center justify-between gap-3 px-carte py-3 text-sm">
                            <div class="min-w-0">
                                <p class="font-medium">{{ $paiement->payable->numero }}</p>
                                <p class="text-xs text-texte-doux">{{ $paiement->date_paiement->translatedFormat('j M Y') }} · {{ $paiement->mode->libelle() }}</p>
                            </div>
                            <p class="chiffres font-semibold">{{ format_ar($paiement->montant) }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-carte>
    </div>

    @include('fournisseurs._formulaire')
@endsection
