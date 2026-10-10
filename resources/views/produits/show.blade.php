{{-- Fiche produit en « bento grid » : identité, stock (jauge), ventes 30 jours, prix, mouvements, ventes et achats --}}
@extends('layouts.app')

@section('titre', $produit->nom)

@php
    $unite = $produit->unite->abreviation;
    $stock = (float) $produit->stock_actuel;
    $minimum = (float) $produit->stock_minimum;
    // Jauge : pleine à deux fois le stock minimum (ou au stock actuel s'il n'y a pas de minimum)
    $plafond = max($minimum * 2, $stock, 1);
    $remplissage = (int) round(max(0, min(1, $stock / $plafond)) * 100);
    $couleurJauge = ['succes' => 'bg-succes', 'alerte' => 'bg-alerte', 'danger' => 'bg-danger'][$produit->etat_stock->couleur()] ?? 'bg-neutre';
    $marge = (float) $produit->prix_vente - (float) $produit->prix_achat;
    $tauxMarge = (float) $produit->prix_achat > 0 ? $marge / (float) $produit->prix_achat * 100 : null;
@endphp

@section('page')
    <x-entete-page :titre="$produit->nom"
                   :fil="['Tableau de bord' => route('accueil'), 'Produits' => route('produits.index'), $produit->reference => null]">
        <x-slot:actions>
            @droit('produits.modifier')
                <x-bouton type="button" variante="secondaire" icone="pencil" x-data
                          x-on:click="$dispatch('editer-produit', {{ \Illuminate\Support\Js::from($produit->pourFormulaire()) }})">Modifier</x-bouton>
            @enddroit
            <x-bouton type="button" variante="secondaire" icone="printer" x-data
                      x-on:click="$dispatch('etiquettes-produits', { produits: ['{{ $produit->id }}'] })">Étiquettes</x-bouton>
            @droit('produits.desactiver')
                <form method="POST" action="{{ route('produits.statut', $produit) }}" x-chargement-envoi>
                    @csrf
                    @method('PATCH')
                    <x-bouton :variante="$produit->actif ? 'danger' : 'primaire'" :icone="$produit->actif ? 'ban' : 'check'">
                        {{ $produit->actif ? 'Désactiver' : 'Réactiver' }}
                    </x-bouton>
                </form>
            @enddroit
        </x-slot:actions>
    </x-entete-page>

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- Identité --}}
        <x-carte class="lg:col-span-2">
            <div class="flex flex-col gap-5 sm:flex-row">
                <div class="grid size-36 shrink-0 place-items-center overflow-hidden rounded-carte border border-bordure bg-fond text-texte-doux">
                    @if ($produit->image)
                        <img src="{{ route('produits.photo', [$produit, 'v' => $produit->updated_at?->timestamp]) }}" alt="Photo de {{ $produit->nom }}" class="size-full object-cover">
                    @else
                        <x-icone nom="package" taille="size-12" />
                    @endif
                </div>
                <div class="min-w-0 flex-1 space-y-3">
                    <div class="flex flex-wrap gap-2">
                        <x-badge :statut="$produit->etat_stock" />
                        <x-badge :couleur="$produit->actif ? 'succes' : 'neutre'">{{ $produit->actif ? 'Actif' : 'Inactif' }}</x-badge>
                        <x-badge couleur="info" :point="false">{{ $produit->categorie->nom }}</x-badge>
                    </div>
                    <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                        <div><dt class="text-texte-doux">Référence</dt><dd class="font-medium">{{ $produit->reference }}</dd></div>
                        <div><dt class="text-texte-doux">Unité de vente</dt><dd class="font-medium">{{ $produit->unite->nom }} ({{ $unite }})</dd></div>
                        <div class="sm:col-span-2"><dt class="text-texte-doux">Description</dt><dd>{{ $produit->description ?: '—' }}</dd></div>
                    </dl>
                    @if ($codeBarresSvg)
                        {{-- Toujours foncé sur fond clair (lisible par un scanner, même en mode sombre) : .clair force les jetons clairs --}}
                        <div class="clair inline-flex flex-col items-center rounded-controle border border-bordure bg-surface px-4 pb-1 pt-3 text-texte" aria-label="Code-barres {{ $produit->code_barres }}" role="img">
                            <div class="h-10 [&>svg]:h-10 [&>svg]:w-auto" aria-hidden="true">{!! $codeBarresSvg !!}</div>
                            <span class="chiffres mt-1 text-xs tracking-widest">{{ $produit->code_barres }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </x-carte>

        {{-- Stock et jauge --}}
        <x-carte titre="Stock">
            <p class="chiffres text-3xl font-semibold tracking-tight">{{ format_quantite($stock, $unite) }}</p>
            <div class="mt-4" role="meter" aria-label="Niveau de stock" aria-valuemin="0" aria-valuemax="{{ $plafond }}" aria-valuenow="{{ $stock }}"
                 aria-valuetext="{{ format_quantite($stock, $unite) }}, {{ $produit->etat_stock->libelle() }}">
                <div class="h-3 overflow-hidden rounded-full bg-neutre-doux">
                    <div class="h-full rounded-full {{ $couleurJauge }} transition-[width] duration-200" style="width: {{ $remplissage }}%"></div>
                </div>
                @if ($minimum > 0)
                    <div class="relative mt-1 h-4 text-xs text-texte-doux">
                        <span class="absolute -translate-x-1/2" style="left: {{ (int) round($minimum / $plafond * 100) }}%">▲ min.</span>
                    </div>
                @endif
            </div>
            <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                <div><dt class="text-texte-doux">Stock minimum</dt><dd class="chiffres font-medium">{{ format_quantite($minimum, $unite) }}</dd></div>
                <div><dt class="text-texte-doux">Valeur (achat)</dt><dd class="chiffres font-medium">{{ format_ar(max(0, $stock) * (float) $produit->prix_achat) }}</dd></div>
            </dl>
        </x-carte>

        {{-- Ventes sur 30 jours --}}
        <x-carte-stat libelle="Vendu sur 30 jours" :valeur="format_quantite($vendu30, $unite)" :tendance="$tendance"
                      periode="vs 30 jours précédents" :serie="$serie" icone="chart-line" />

        {{-- Prix et marge --}}
        <x-carte titre="Prix et marge">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-texte-doux">Prix d'achat</dt><dd class="chiffres font-medium">{{ format_ar($produit->prix_achat) }}</dd></div>
                <div class="flex justify-between"><dt class="text-texte-doux">Prix de vente</dt><dd class="chiffres font-semibold">{{ format_ar($produit->prix_vente) }}</dd></div>
                <div class="flex justify-between"><dt class="text-texte-doux">Prix de gros</dt><dd class="chiffres font-medium">{{ $produit->prix_gros !== null ? format_ar($produit->prix_gros) : '—' }}</dd></div>
                <div @class(['flex justify-between border-t border-bordure pt-2', 'text-succes-texte' => $marge >= 0, 'text-danger-texte' => $marge < 0])>
                    <dt>Marge unitaire</dt>
                    <dd class="chiffres font-semibold">{{ format_ar($marge) }}@if ($tauxMarge !== null) ({{ number_format($tauxMarge, 1, ',', ' ') }} %)@endif</dd>
                </div>
            </dl>
        </x-carte>

        {{-- Historique des mouvements --}}
        <x-carte titre="Mouvements de stock" description="Les 15 derniers" class="lg:col-span-1 lg:row-span-2" :padding="false">
            @droit('stock.voir')
                <x-slot:actions><x-bouton variante="fantome" taille="sm" :href="route('stock.mouvements', ['produit' => $produit->id])">Tout voir</x-bouton></x-slot:actions>
            @enddroit
            @if ($mouvements->isEmpty())
                <x-etat-vide icone="history" titre="Aucun mouvement" texte="Les entrées et sorties de stock apparaîtront ici." />
            @else
                <ul class="divide-y divide-bordure" role="list">
                    @foreach ($mouvements as $mouvement)
                        <li class="flex items-center justify-between gap-3 px-carte py-3 text-sm">
                            <div class="min-w-0">
                                <x-badge :statut="$mouvement->type" />
                                <p class="mt-1 text-xs text-texte-doux">{{ $mouvement->created_at->translatedFormat('j M Y, H:i') }} · {{ $mouvement->utilisateur->nom }}</p>
                                @if ($mouvement->motif)<p class="truncate text-xs text-texte-doux">{{ $mouvement->motif }}</p>@endif
                            </div>
                            <div class="text-right">
                                <p @class(['chiffres font-semibold', 'text-succes-texte' => $mouvement->sens->value === 'entree', 'text-danger-texte' => $mouvement->sens->value === 'sortie'])>
                                    {{ $mouvement->sens->value === 'entree' ? '+' : '−' }}{{ format_quantite($mouvement->quantite) }}
                                </p>
                                <p class="chiffres text-xs text-texte-doux">→ {{ format_quantite($mouvement->stock_apres) }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-carte>

        {{-- Dernières ventes --}}
        <x-carte titre="Dernières ventes" :padding="false" class="lg:col-span-2">
            @if ($dernieresVentes->isEmpty())
                <x-etat-vide icone="receipt" titre="Aucune vente" texte="Ce produit n'a pas encore été vendu." />
            @else
                <ul class="divide-y divide-bordure" role="list">
                    @foreach ($dernieresVentes as $ligne)
                        <li class="flex items-center justify-between gap-3 px-carte py-3 text-sm">
                            <div><p class="font-medium">{{ $ligne->vente->numero }}</p><p class="text-xs text-texte-doux">{{ $ligne->vente->date_vente->translatedFormat('j M Y, H:i') }}</p></div>
                            <div class="text-right"><p class="chiffres">{{ format_quantite($ligne->quantite, $unite) }} × {{ format_ar($ligne->prix_unitaire) }}</p><p class="chiffres font-semibold">{{ format_ar($ligne->total) }}</p></div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-carte>

        {{-- Derniers achats --}}
        <x-carte titre="Derniers achats" :padding="false" class="lg:col-span-2">
            @if ($derniersAchats->isEmpty())
                <x-etat-vide icone="truck" titre="Aucun achat" texte="Aucun approvisionnement enregistré pour ce produit." />
            @else
                <ul class="divide-y divide-bordure" role="list">
                    @foreach ($derniersAchats as $ligne)
                        <li class="flex items-center justify-between gap-3 px-carte py-3 text-sm">
                            <div><p class="font-medium">{{ $ligne->achat->numero }} · {{ $ligne->achat->fournisseur->nom }}</p><p class="text-xs text-texte-doux">{{ $ligne->achat->date_achat->translatedFormat('j M Y') }}</p></div>
                            <div class="text-right"><p class="chiffres">{{ format_quantite($ligne->quantite, $unite) }} × {{ format_ar($ligne->prix_achat) }}</p><p class="chiffres font-semibold">{{ format_ar($ligne->total) }}</p></div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-carte>
    </div>

    {{-- Panneau de saisie : seulement pour qui peut créer ou modifier --}}
    @if (auth()->user()->can('produits.creer') || auth()->user()->can('produits.modifier'))
        @include('produits._formulaire')
    @endif
    @include('produits._etiquettes')
@endsection
