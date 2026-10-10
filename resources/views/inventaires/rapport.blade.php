{{--
    Rapport d'écarts A4 (DomPDF) : stock théorique, compté, écart et sa valeur au prix d'achat, par catégorie,
    totaux et produits non comptés. Filigrane « EN COURS » tant que l'inventaire n'est pas validé.
    Écart d'un inventaire en cours : provisoire (sur le stock actuel) ; validé : définitif.
--}}
@php
    $valide = $inventaire->statut === \App\Enums\StatutInventaire::Valide;
    $comptees = $inventaire->lignes->whereNotNull('stock_compte');
    $nonComptees = $inventaire->lignes->whereNull('stock_compte');
    $valeur = fn ($ligne) => (float) $ligne->ecart * (float) $ligne->produit->prix_achat;
    $totalPlus = $comptees->filter(fn ($l) => (float) $l->ecart > 0)->sum($valeur);
    $totalMoins = $comptees->filter(fn ($l) => (float) $l->ecart < 0)->sum($valeur);
    // Trait d'union ASCII : le signe moins typographique (U+2212) n'existe pas dans Helvetica (DomPDF)
    $signe = fn (float $n) => ($n > 0 ? '+' : ($n < 0 ? '-' : '')).format_quantite(abs($n));
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Rapport d'écarts {{ $inventaire->numero }}</title>
    <style>
        @page { margin: 14mm 13mm 18mm; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 9pt; color: #111826; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }
        .doux { color: #5b6475; }
        .droite { text-align: right; }
        .entreprise { font-size: 13pt; font-weight: bold; color: {{ $accent['fonce'] }}; }
        .titre { font-size: 17pt; font-weight: bold; text-align: right; }
        .categorie { margin-top: 5mm; padding: 2mm 2.5mm; background: {{ $accent['doux'] }}; color: {{ $accent['sombre'] }}; font-weight: bold; border-bottom: 0.5mm solid {{ $accent['principal'] }}; }
        .lignes th { font-size: 7.5pt; color: #5b6475; text-align: left; padding: 1.6mm 2mm; border-bottom: 0.3mm solid #e2e5ea; }
        .lignes th.droite { text-align: right; }
        .lignes td { padding: 1.8mm 2mm; border-bottom: 0.2mm solid #eceef2; }
        .plus { color: #116133; font-weight: bold; }
        .moins { color: #a3261b; font-weight: bold; }
        .bloc { border: 0.3mm solid #e2e5ea; border-radius: 2mm; padding: 3mm 4mm; }
        .filigrane { position: fixed; top: 110mm; left: 0; width: 100%; text-align: center; font-size: 66pt; font-weight: bold; color: {{ $accent['moyen'] }}; z-index: -1; }
        .pied { position: fixed; bottom: -10mm; left: 0; width: 100%; font-size: 7.5pt; color: #5b6475; text-align: center; }
    </style>
</head>
<body>
    @unless ($valide)
        <div class="filigrane">EN COURS</div>
    @endunless
    <div class="pied">{{ $entreprise['nom'] }} · Rapport d'écarts {{ $inventaire->numero }} · {{ $valide ? 'écarts définitifs' : 'écarts provisoires (stock actuel)' }}</div>

    <table>
        <tr>
            <td style="width: 55%;">
                <div class="entreprise">{{ $entreprise['nom'] }}</div>
                <div class="doux">Ouvert le {{ $inventaire->date_inventaire->translatedFormat('j F Y') }} par {{ $inventaire->utilisateur->nom }} · {{ $inventaire->statut->libelle() }}</div>
                @if ($inventaire->notes)<div class="doux">{{ $inventaire->notes }}</div>@endif
            </td>
            <td>
                <div class="titre">RAPPORT D'ÉCARTS</div>
                <div class="droite"><strong>{{ $inventaire->numero }}</strong></div>
            </td>
        </tr>
    </table>

    <table style="margin-top: 6mm;">
        <tr>
            <td class="bloc" style="width: 32%;"><span class="doux">Produits comptés</span><br><strong>{{ $comptees->count() }} / {{ $inventaire->lignes->count() }}</strong></td>
            <td style="width: 2%;"></td>
            <td class="bloc" style="width: 32%;"><span class="doux">Écarts</span><br><strong>{{ $comptees->filter(fn ($l) => (float) $l->ecart != 0)->count() }}</strong> produit(s)</td>
            <td style="width: 2%;"></td>
            <td class="bloc" style="width: 32%;"><span class="doux">Valeur des écarts (prix d'achat)</span><br>
                <span class="plus">+{{ format_ar($totalPlus) }}</span> · <span class="moins">-{{ format_ar(abs($totalMoins)) }}</span></td>
        </tr>
    </table>

    @foreach ($groupes as $categorie => $lignes)
        @php($lignes = $lignes->whereNotNull('stock_compte'))
        @continue($lignes->isEmpty())
        <div class="categorie">{{ $categorie }}</div>
        <table class="lignes">
            <thead>
                <tr>
                    <th style="width: 15%;">Référence</th>
                    <th>Produit</th>
                    <th class="droite" style="width: 12%;">Théorique</th>
                    <th class="droite" style="width: 12%;">Compté</th>
                    <th class="droite" style="width: 12%;">Écart</th>
                    <th class="droite" style="width: 15%;">Valeur</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lignes as $ligne)
                    @php($ecart = (float) $ligne->ecart)
                    <tr>
                        <td class="doux">{{ $ligne->produit->reference }}</td>
                        <td>{{ $ligne->produit->nom }}</td>
                        <td class="droite">{{ format_quantite($ligne->stock_theorique, $ligne->produit->unite?->abreviation) }}</td>
                        <td class="droite">{{ format_quantite($ligne->stock_compte, $ligne->produit->unite?->abreviation) }}</td>
                        <td @class(['droite', 'plus' => $ecart > 0, 'moins' => $ecart < 0, 'doux' => $ecart == 0])>{{ $ecart == 0 ? '0' : $signe($ecart) }}</td>
                        <td @class(['droite', 'plus' => $ecart > 0, 'moins' => $ecart < 0, 'doux' => $ecart == 0])>{{ $ecart == 0 ? '—' : ($ecart > 0 ? '+' : '-').format_ar(abs($valeur($ligne))) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    @if ($nonComptees->isNotEmpty())
        <div class="categorie">Produits non comptés ({{ $nonComptees->count() }}) — stock inchangé</div>
        <table class="lignes">
            <tbody>
                @foreach ($nonComptees as $ligne)
                    <tr>
                        <td class="doux" style="width: 15%;">{{ $ligne->produit->reference }}</td>
                        <td>{{ $ligne->produit->nom }}</td>
                        <td class="droite doux" style="width: 20%;">{{ format_quantite($ligne->stock_theorique, $ligne->produit->unite?->abreviation) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
