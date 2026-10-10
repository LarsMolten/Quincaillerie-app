{{-- Fiche client : coordonnées, plafond de crédit (barre), créance, ventes et factures, paiements --}}
@extends('layouts.app')

@section('titre', $client->nom)

@php
    $couleurBarre = match (true) {
        $utilisation === null => 'bg-neutre',
        $utilisation >= 100 => 'bg-danger',
        $utilisation >= 70 => 'bg-alerte',
        default => 'bg-succes',
    };
@endphp

@section('page')
    <x-entete-page :titre="$client->nom"
                   :fil="['Tableau de bord' => route('accueil'), 'Clients' => route('clients.index'), $client->nom => null]">
        <x-slot:actions>@include('clients._actions')</x-slot:actions>
    </x-entete-page>

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- Coordonnées --}}
        <x-carte class="lg:row-span-2">
            <div class="flex items-center gap-4">
                @if ($client->estComptoir())
                    <span class="grid size-16 shrink-0 place-items-center rounded-full bg-neutre-doux text-neutre-texte" aria-hidden="true"><x-icone nom="store" taille="size-7" /></span>
                @else
                    <x-avatar :nom="$client->nom" taille="lg" />
                @endif
                <div class="min-w-0">
                    <p class="truncate text-lg font-semibold">{{ $client->nom }}</p>
                    <div class="mt-1 flex flex-wrap gap-1.5">
                        <x-badge :couleur="$client->actif ? 'succes' : 'neutre'">{{ $client->actif ? 'Actif' : 'Inactif' }}</x-badge>
                        @if ($client->estComptoir())<x-badge couleur="info" :point="false">Ventes anonymes</x-badge>@endif
                    </div>
                </div>
            </div>
            <dl class="mt-6 space-y-4 text-sm">
                @foreach ([
                    ['phone', 'Téléphone', $client->telephone, $client->telephone ? 'tel:'.preg_replace('/[^0-9+]/', '', $client->telephone) : null],
                    ['mail', 'Email', $client->email, $client->email ? 'mailto:'.$client->email : null],
                    ['map-pin', 'Adresse', $client->adresse, null],
                    ['calendar', 'Client depuis', $client->created_at?->translatedFormat('j F Y'), null],
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

        {{-- Créance : grande carte --}}
        <article class="flex flex-col justify-between gap-4 rounded-carte border border-bordure bg-surface p-carte shadow-doux">
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-medium text-texte-doux">Créance totale</p>
                <span class="grid size-10 place-items-center rounded-controle bg-primaire-doux text-lien"><x-icone nom="banknote" /></span>
            </div>
            <p @class(['chiffres text-4xl font-semibold tracking-tight', 'text-danger-texte' => $creance > 0, 'text-succes-texte' => $creance <= 0])>{{ format_ar($creance) }}</p>
            <p class="text-sm text-texte-doux">
                @if ($creance > 0)
                    <span class="chiffres font-medium text-texte">{{ $ventesImpayees }}</span> vente{{ $ventesImpayees > 1 ? 's' : '' }} non soldée{{ $ventesImpayees > 1 ? 's' : '' }}.
                @else
                    Aucune somme due.
                @endif
            </p>
        </article>

        {{-- Plafond de crédit --}}
        <x-carte titre="Plafond de crédit">
            @if ($plafond === null)
                <p class="flex items-start gap-2 text-sm text-texte-doux">
                    <x-icone nom="lock" taille="size-4" class="mt-0.5" />
                    {{ $client->estComptoir() ? 'Client comptoir : crédit jamais autorisé.' : 'Crédit non autorisé : aucun plafond défini.' }}
                </p>
            @else
                <p class="chiffres text-sm"><span class="text-2xl font-semibold">{{ format_ar($creance) }}</span> <span class="text-texte-doux">/ {{ format_ar($plafond) }}</span></p>
                <div class="mt-3 h-3 overflow-hidden rounded-full bg-neutre-doux" role="progressbar" aria-label="Crédit utilisé"
                     aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ (int) round(min(100, $utilisation)) }}"
                     aria-valuetext="{{ format_ar($creance) }} utilisés sur {{ format_ar($plafond) }}">
                    <div class="h-full rounded-full {{ $couleurBarre }} transition-[width] duration-200" style="width: {{ (int) round(min(100, $utilisation)) }}%"></div>
                </div>
                <p class="mt-2 text-sm text-texte-doux">
                    @if ($utilisation >= 100)
                        <span class="font-medium text-danger-texte">Plafond atteint : nouvelle vente à crédit refusée.</span>
                    @else
                        Encore <span class="chiffres font-medium text-texte">{{ format_ar($plafond - $creance) }}</span> disponibles ({{ number_format($utilisation, 0, ',', ' ') }} % utilisé).
                    @endif
                </p>
            @endif
        </x-carte>

        <x-carte-stat libelle="Total des achats" :valeur="format_ar($totalAchats)" icone="shopping-cart" />
        <x-carte-stat libelle="Nombre de ventes" :valeur="$nombreVentes" icone="receipt" />

        {{-- Ventes et factures --}}
        <x-carte titre="Ventes et factures" class="lg:col-span-2" :padding="false">
            @if ($ventes->isEmpty())
                <x-etat-vide icone="receipt" titre="Aucune vente" texte="Les achats de ce client apparaîtront ici." />
            @else
                <ul class="divide-y divide-bordure" role="list">
                    @foreach ($ventes as $vente)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-carte py-3 text-sm">
                            <div>
                                <p class="font-medium">{{ $vente->numero }}</p>
                                <p class="text-xs text-texte-doux">
                                    {{ $vente->date_vente->translatedFormat('j M Y, H:i') }}
                                    @if ($vente->facture) · Facture {{ $vente->facture->numero }}@endif
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <x-badge :statut="$vente->statut" />
                                @if ($vente->statut->value === 'validee')
                                    <x-badge :couleur="$vente->est_soldee ? 'succes' : 'alerte'">{{ $vente->est_soldee ? 'Soldée' : 'Non soldée' }}</x-badge>
                                @endif
                            </div>
                            <div class="text-right">
                                <p class="chiffres font-semibold">{{ format_ar($vente->total) }}</p>
                                @if ((float) $vente->reste_a_payer > 0)
                                    <p class="chiffres text-xs text-danger-texte">Reste {{ format_ar($vente->reste_a_payer) }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
                @if ($ventes->hasPages())<div class="border-t border-bordure px-4 py-3">{{ $ventes->links() }}</div>@endif
            @endif
        </x-carte>

        {{-- Paiements --}}
        <x-carte titre="Paiements reçus" description="Les 15 derniers" :padding="false">
            @if ($paiements->isEmpty())
                <x-etat-vide icone="banknote" titre="Aucun paiement" texte="Les règlements de ce client apparaîtront ici." />
            @else
                <ul class="divide-y divide-bordure" role="list">
                    @foreach ($paiements as $paiement)
                        <li class="flex items-center justify-between gap-3 px-carte py-3 text-sm">
                            <div class="min-w-0">
                                <p class="font-medium">{{ $paiement->payable->numero }}</p>
                                <p class="text-xs text-texte-doux">{{ $paiement->numero }} · {{ $paiement->date_paiement->translatedFormat('j M Y') }} · {{ $paiement->mode->libelle() }}</p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <p class="chiffres font-semibold">{{ format_ar($paiement->montant) }}</p>
                                @droit('paiements.gerer')
                                    <a href="{{ route('paiements.recu', $paiement) }}" target="_blank" x-data x-ouvrir-pdf
                                       class="grid size-11 place-items-center rounded-controle text-texte-doux transition-colors duration-150 hover:bg-fond hover:text-texte"
                                       title="Reçu {{ $paiement->numero }}">
                                        <x-icone nom="printer" taille="size-4" />
                                        <span class="sr-only">Imprimer le reçu {{ $paiement->numero }}</span>
                                    </a>
                                @enddroit
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-carte>
    </div>

    @include('clients._formulaire')
@endsection
