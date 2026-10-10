{{-- Rapport financier : CA net, achats, dépenses, bénéfice, paiements, remboursements, créances et dettes --}}
@extends('layouts.app')

@section('titre', $titre)

@php
    $s = $donnees['synthese'];
    $serie = $donnees['serie'];
    $granularite = ['jour' => 'par jour', 'semaine' => 'par semaine', 'mois' => 'par mois'][$periode->granularite()];
    $comparaison = 'par rapport à la période précédente';
@endphp

@section('page')
    @include('rapports._entete')

    <section aria-label="Synthèse" class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-carte-stat libelle="Chiffre d'affaires net" :valeur="format_ar($s['ventes']['valeur'])" icone="shopping-cart" :tendance="$s['ventes']['tendance']" :periode="$comparaison">
            <span class="chiffres">{{ format_ar($s['brut']) }} vendus − {{ format_ar($s['retours']) }} retournés</span>
        </x-carte-stat>
        <x-carte-stat libelle="Bénéfice" :valeur="format_ar($s['benefice']['valeur'])" icone="trending-up" :tendance="$s['benefice']['tendance']" :periode="$comparaison">
            <span class="chiffres">Marge nette {{ $s['taux_marge'] !== null ? str_replace('.', ',', $s['taux_marge']).' %' : '—' }}</span>
        </x-carte-stat>
        <x-carte-stat libelle="Dépenses" :valeur="format_ar($s['depenses']['valeur'])" icone="wallet" :tendance="$s['depenses']['tendance']" :periode="$comparaison" inverse />
        <x-carte-stat libelle="Achats" :valeur="format_ar($s['achats']['valeur'])" icone="truck" :tendance="$s['achats']['tendance']" :periode="$comparaison" inverse />
    </section>

    <div class="grid gap-4 lg:grid-cols-3">
        <x-carte :titre="'Chiffre d’affaires et bénéfice '.$granularite" class="lg:col-span-2">
            <div class="h-72" x-data="graphiqueRapport({{ \Illuminate\Support\Js::from(['type' => 'line', 'libelles' => array_column($serie, 'libelle'), 'series' => [
                ['libelle' => 'CA net', 'valeurs' => array_column($serie, 'ventes'), 'jeton' => 'primaire'],
                ['libelle' => 'Bénéfice', 'valeurs' => array_column($serie, 'benefice'), 'jeton' => 'graphique-3'],
            ]]) }})">
                <canvas x-ref="canvas" role="img" aria-label="Courbes du chiffre d'affaires net et du bénéfice {{ $granularite }} (valeurs dans le tableau)"></canvas>
            </div>
            <p class="mt-2 flex gap-4 text-xs text-texte-doux">
                <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-primaire" aria-hidden="true"></span> CA net</span>
                <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-graphique-3" aria-hidden="true"></span> Bénéfice</span>
            </p>
            @include('rapports._tableau-serie', ['legende' => 'Chiffre d’affaires et bénéfice '.$granularite, 'colonnes' => ['libelle' => 'Période', 'ventes' => 'CA net', 'depenses' => 'Dépenses', 'benefice' => 'Bénéfice'], 'lignes' => $serie])
        </x-carte>

        <x-carte titre="Compte de résultat">
            <dl class="space-y-2 text-sm">
                @foreach ([
                    ['Ventes', $s['brut'], false], ['− Retours clients', -$s['retours'], false], ['= Chiffre d’affaires net', $s['ventes']['valeur'], true],
                    ['− Coût d’achat des produits vendus', -$s['cout'], false], ['= Marge brute', $s['marge_brute'], true],
                    ['− Dépenses', -$s['depenses']['valeur'], false], ['= Bénéfice', $s['benefice']['valeur'], true],
                ] as [$libelle, $montant, $total])
                    <div @class(['flex justify-between gap-3', 'border-t border-bordure pt-2 font-semibold' => $total])>
                        <dt @class(['text-texte-doux' => ! $total])>{{ $libelle }}</dt>
                        <dd @class(['chiffres', 'text-danger-texte' => $total && $montant < 0])>{{ format_ar($montant) }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-carte>

        <x-carte titre="Dépenses par catégorie">
            @if ($donnees['depensesParCategorie']->isEmpty())
                <p class="py-6 text-center text-sm text-texte-doux">Aucune dépense sur cette période.</p>
            @else
                <div class="mx-auto mb-4 size-40" x-data="graphiqueRapport({{ \Illuminate\Support\Js::from(['type' => 'doughnut', 'libelles' => $donnees['depensesParCategorie']->map(fn ($d) => $d->categorie->libelle()), 'series' => [['libelle' => 'Dépenses', 'valeurs' => $donnees['depensesParCategorie']->pluck('total'), 'jetons' => $donnees['depensesParCategorie']->map(fn ($d) => $d->categorie->jeton())]]]) }})">
                    <canvas x-ref="canvas" role="img" aria-label="Répartition des dépenses par catégorie (détail dans la liste)"></canvas>
                </div>
                <ul class="space-y-1.5 text-sm">
                    @foreach ($donnees['depensesParCategorie'] as $d)
                        <li class="flex items-center gap-2">
                            <span class="size-3 shrink-0 rounded-full {{ $d->categorie->classePastille() }}" aria-hidden="true"></span>
                            <span class="flex-1">{{ $d->categorie->libelle() }}</span>
                            <span class="chiffres font-medium">{{ format_ar($d->total) }}</span>
                            <span class="chiffres w-12 text-right text-texte-doux">{{ str_replace('.', ',', $d->part) }} %</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-carte>

        <x-carte titre="Paiements de la période">
            <table class="w-full text-sm">
                <thead><tr class="text-texte-doux"><th class="pb-2 text-left font-medium">Mode</th><th class="pb-2 text-right font-medium">Reçus</th><th class="pb-2 text-right font-medium">Versés</th></tr></thead>
                <tbody>
                    @foreach ($donnees['encaissements'] as $i => $e)
                        <tr class="border-t border-bordure">
                            <td class="py-1.5">{{ $e->mode->libelle() }}</td>
                            <td class="chiffres py-1.5 text-right">{{ format_ar($e->total) }}</td>
                            <td class="chiffres py-1.5 text-right text-texte-doux">{{ format_ar($donnees['decaissements'][$i]->total) }}</td>
                        </tr>
                    @endforeach
                    <tr class="border-t border-bordure font-semibold">
                        <td class="py-1.5">Total</td>
                        <td class="chiffres py-1.5 text-right">{{ format_ar($donnees['encaissements']->sum('total')) }}</td>
                        <td class="chiffres py-1.5 text-right">{{ format_ar($donnees['decaissements']->sum('total')) }}</td>
                    </tr>
                </tbody>
            </table>
            <p class="mt-3 text-xs text-texte-doux">Remboursements de retours : {{ format_ar($donnees['remboursements']['clients']) }} versés aux clients,
                {{ format_ar($donnees['remboursements']['fournisseurs']) }} reçus des fournisseurs.</p>
        </x-carte>

        <x-carte titre="Créances et dettes" description="Situation à ce jour">
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-controle bg-fond p-3"><dt class="text-texte-doux">Créances clients</dt><dd class="chiffres text-lg font-semibold">{{ format_ar($donnees['creances']['total']) }}</dd>
                    <dd class="chiffres text-xs text-texte-doux">{{ $donnees['creances']['debiteurs'] }} client(s) · {{ format_ar($donnees['creances']['plus_de_60']) }} &gt; 60 j</dd></div>
                <div class="rounded-controle bg-fond p-3"><dt class="text-texte-doux">Dettes fournisseurs</dt><dd class="chiffres text-lg font-semibold">{{ format_ar($donnees['dettes']['total']) }}</dd>
                    <dd class="chiffres text-xs text-texte-doux">{{ $donnees['dettes']['creanciers'] }} fournisseur(s)</dd></div>
            </dl>
            <ul class="mt-3 divide-y divide-bordure text-sm">
                @foreach ($donnees['creances']['principaux'] as $c)
                    <li class="flex justify-between gap-3 py-1.5"><span class="truncate">{{ $c->nom }}</span><span class="chiffres">{{ format_ar($c->du) }}</span></li>
                @endforeach
                @foreach ($donnees['dettes']['principaux'] as $d)
                    <li class="flex justify-between gap-3 py-1.5"><span class="truncate text-texte-doux">{{ $d->nom }} (fournisseur)</span><span class="chiffres text-texte-doux">{{ format_ar($d->du) }}</span></li>
                @endforeach
            </ul>
        </x-carte>
    </div>
@endsection
