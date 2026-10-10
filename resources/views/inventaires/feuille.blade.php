{{--
    Feuille de comptage A4 (DomPDF) : produits par catégorie, case « compté » à remplir à la main.
    Le stock théorique n'apparaît pas (comptage à l'aveugle). Tables HTML et couleurs hexadécimales (CLAUDE.md §7).
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Feuille de comptage {{ $inventaire->numero }}</title>
    <style>
        @page { margin: 14mm 13mm 18mm; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 9.5pt; color: #111826; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: middle; }
        .doux { color: #5b6475; }
        .droite { text-align: right; }
        .entreprise { font-size: 13pt; font-weight: bold; color: {{ $accent['fonce'] }}; }
        .titre { font-size: 17pt; font-weight: bold; text-align: right; }
        .categorie { margin-top: 6mm; padding: 2mm 2.5mm; background: {{ $accent['doux'] }}; color: {{ $accent['sombre'] }}; font-weight: bold; border-bottom: 0.5mm solid {{ $accent['principal'] }}; }
        .lignes th { font-size: 8pt; color: #5b6475; text-align: left; padding: 1.8mm 2.5mm; border-bottom: 0.3mm solid #e2e5ea; }
        .lignes td { padding: 2.6mm 2.5mm; border-bottom: 0.2mm solid #eceef2; }
        .case { border: 0.3mm solid #b9bfca; border-radius: 1mm; height: 6mm; width: 28mm; margin-left: auto; }
        .lignes td { padding-top: 1.6mm; padding-bottom: 1.6mm; }
        .pied { position: fixed; bottom: -10mm; left: 0; width: 100%; font-size: 7.5pt; color: #5b6475; }
        .signature { height: 14mm; border-bottom: 0.3mm solid #b9bfca; }
    </style>
</head>
<body>
    <div class="pied">
        <table><tr>
            <td>{{ $entreprise['nom'] }} · Feuille de comptage {{ $inventaire->numero }}</td>
            <td class="droite">Compté par : ............................................</td>
        </tr></table>
    </div>

    <table>
        <tr>
            <td style="width: 55%;">
                <div class="entreprise">{{ $entreprise['nom'] }}</div>
                <div class="doux">{{ $inventaire->lignes->count() }} produit(s) · ouvert le {{ $inventaire->date_inventaire->translatedFormat('j F Y') }} par {{ $inventaire->utilisateur->nom }}</div>
                @if ($inventaire->notes)<div class="doux">{{ $inventaire->notes }}</div>@endif
            </td>
            <td>
                <div class="titre">FEUILLE DE COMPTAGE</div>
                <div class="droite"><strong>{{ $inventaire->numero }}</strong></div>
            </td>
        </tr>
    </table>

    @foreach ($groupes as $categorie => $lignes)
        <div class="categorie">{{ $categorie }} <span style="font-weight: normal;">({{ $lignes->count() }})</span></div>
        <table class="lignes">
            <thead>
                <tr>
                    <th style="width: 18%;">Référence</th>
                    <th>Produit</th>
                    <th style="width: 10%;">Unité</th>
                    <th class="droite" style="width: 22%;">Quantité comptée</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lignes as $ligne)
                    <tr>
                        <td class="doux">{{ $ligne->produit->reference }}</td>
                        <td>{{ $ligne->produit->nom }}</td>
                        <td class="doux">{{ $ligne->produit->unite?->abreviation }}</td>
                        <td class="droite"><div class="case"></div></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    <table style="margin-top: 12mm;">
        <tr>
            <td style="width: 45%;"><span class="doux">Compté par</span><div class="signature"></div></td>
            <td style="width: 10%;"></td>
            <td style="width: 45%;"><span class="doux">Vérifié par</span><div class="signature"></div></td>
        </tr>
    </table>
</body>
</html>
