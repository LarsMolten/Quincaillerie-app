{{-- Synthèse (total, anneau par catégorie) et tableau des dépenses : rechargés ensemble (en-tête X-Fragment) --}}
@php
    $series = $repartition->map(fn ($r) => ['libelle' => $r['categorie']->libelle(), 'valeur' => $r['total'], 'jeton' => $r['categorie']->jeton()])->values();
    $filtresActifs = $filtres['categorie'] || $filtres['recherche'] !== '' || $filtres['du'] || $filtres['au'] || $filtres['periode'] !== 'mois';
@endphp

<div class="mb-6 grid gap-4 lg:grid-cols-3">
    {{-- Total de la sélection --}}
    <x-carte class="flex flex-col justify-between">
        <div>
            <p class="text-sm font-medium text-texte-doux">{{ $filtres['categorie'] ? 'Total '.\App\Enums\CategorieDepense::from($filtres['categorie'])->libelle() : 'Total des dépenses' }}</p>
            <p class="chiffres mt-2 text-3xl font-semibold tracking-tight">{{ format_ar($synthese['total']) }}</p>
        </div>
        <dl class="mt-6 grid grid-cols-2 gap-3 text-sm">
            <div class="rounded-controle bg-fond p-3"><dt class="text-texte-doux">Dépenses</dt><dd class="chiffres text-lg font-semibold">{{ $synthese['nombre'] }}</dd></div>
            <div class="rounded-controle bg-fond p-3"><dt class="text-texte-doux">Moyenne</dt><dd class="chiffres text-lg font-semibold">{{ format_ar($synthese['moyenne']) }}</dd></div>
        </dl>
    </x-carte>

    {{-- Répartition par catégorie --}}
    <x-carte titre="Répartition par catégorie" class="lg:col-span-2">
        @if ($repartition->isEmpty())
            <p class="py-8 text-center text-sm text-texte-doux">Aucune dépense sur cette période.</p>
        @else
            <div class="grid items-center gap-6 sm:grid-cols-[13rem_1fr]">
                <div class="relative mx-auto size-52" x-data="graphiqueAnneau({ series: {{ \Illuminate\Support\Js::from($series) }} })">
                    <canvas x-ref="canvas" role="img" aria-label="Graphique en anneau de la répartition des dépenses par catégorie (détail dans la liste)"></canvas>
                    <div class="pointer-events-none absolute inset-0 grid place-items-center text-center">
                        <div>
                            <p class="text-xs text-texte-doux">Total</p>
                            <p class="chiffres text-base font-semibold">{{ format_ar($synthese['totalPeriode']) }}</p>
                        </div>
                    </div>
                </div>
                <ul class="space-y-2 text-sm" aria-label="Montants par catégorie">
                    @foreach ($repartition as $ligne)
                        <li class="flex items-center gap-3">
                            <span class="size-3 shrink-0 rounded-full {{ $ligne['categorie']->classePastille() }}" aria-hidden="true"></span>
                            <span class="flex-1">{{ $ligne['categorie']->libelle() }} <span class="text-texte-doux">({{ $ligne['nombre'] }})</span></span>
                            <span class="chiffres font-medium">{{ format_ar($ligne['total']) }}</span>
                            <span class="chiffres w-14 text-right text-texte-doux">{{ number_format($ligne['part'], 1, ',', ' ') }} %</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </x-carte>
</div>

@if ($depenses->isEmpty())
    <x-carte>
        @if ($filtresActifs)
            <x-etat-vide icone="search" titre="Aucune dépense trouvée" texte="Aucune dépense ne correspond à ces filtres.">
                <x-bouton :href="route('depenses.index')" variante="secondaire" icone="x" data-liste-lien>Effacer les filtres</x-bouton>
            </x-etat-vide>
        @else
            <x-etat-vide icone="wallet" titre="Aucune dépense ce mois-ci" texte="Enregistrez les salaires, le loyer, l’électricité… pour suivre le bénéfice réel.">
                <x-bouton type="button" icone="plus" x-on:click="nouvelle()">Nouvelle dépense</x-bouton>
            </x-etat-vide>
        @endif
    </x-carte>
@else
    <x-tableau
        legende="Liste des dépenses, de la plus récente à la plus ancienne"
        :pagination="$depenses"
        :colonnes="[
            'date' => 'Date',
            'categorie' => 'Catégorie',
            'libelle' => 'Libellé',
            'mode' => 'Mode',
            'montant' => ['libelle' => 'Montant', 'alignement' => 'droite'],
            'actions' => ['libelle' => 'Actions', 'masque' => true, 'alignement' => 'droite'],
        ]"
    >
        @foreach ($depenses as $depense)
            @php
                $donnees = [
                    'url' => route('depenses.update', $depense),
                    'categorie' => $depense->categorie->value,
                    'libelle' => $depense->libelle,
                    'montant' => (float) $depense->montant,
                    'date_depense' => $depense->date_depense->toDateString(),
                    'mode_paiement' => $depense->mode_paiement->value,
                    'notes' => $depense->notes,
                ];
            @endphp
            <x-tableau.ligne>
                <x-tableau.cellule libelle="Date" class="whitespace-nowrap text-texte-doux">{{ $depense->date_depense->translatedFormat('j M Y') }}</x-tableau.cellule>
                <x-tableau.cellule libelle="Catégorie">
                    <span class="inline-flex items-center gap-2">
                        <span class="grid size-8 place-items-center rounded-full {{ $depense->categorie->classesIcone() }}"><x-icone :nom="$depense->categorie->icone()" taille="size-4" /></span>
                        {{ $depense->categorie->libelle() }}
                    </span>
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Libellé" principale>
                    <span class="font-medium">{{ $depense->libelle }}</span>
                    <span class="block max-w-80 truncate text-xs font-normal text-texte-doux">{{ $depense->notes ? $depense->notes.' · ' : '' }}{{ $depense->utilisateur->nom }}</span>
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Mode"><x-badge :statut="$depense->mode_paiement" /></x-tableau.cellule>
                <x-tableau.cellule libelle="Montant" alignement="droite" class="font-semibold">{{ format_ar($depense->montant) }}</x-tableau.cellule>
                <x-tableau.cellule libelle="Actions" alignement="droite">
                    <x-menu-actions :libelle="'Actions pour la dépense '.$depense->libelle">
                        <x-menu-actions.element icone="pencil" x-on:click="$dispatch('depense-modifier', {{ \Illuminate\Support\Js::from($donnees) }})">Modifier</x-menu-actions.element>
                        @if ($estAdministrateur)
                            <x-menu-actions.element icone="trash-2" danger
                                                    x-on:click="$dispatch('depense-supprimer', {{ \Illuminate\Support\Js::from(['url' => route('depenses.destroy', $depense), 'libelle' => $depense->libelle, 'montant' => format_ar($depense->montant)]) }})">Supprimer</x-menu-actions.element>
                        @endif
                    </x-menu-actions>
                </x-tableau.cellule>
            </x-tableau.ligne>
        @endforeach
    </x-tableau>
@endif
