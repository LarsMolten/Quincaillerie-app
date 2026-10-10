{{--
    Mise en page commune des rapports PDF (DomPDF) : tables HTML et couleurs hexadécimales (pas de flexbox, grid ni OKLCH,
    CLAUDE.md §7), accent orange, Helvetica, en-tête entreprise / titre / période, pied paginé.
    Signe moins : trait d'union ASCII (le signe U+2212 n'existe pas dans Helvetica).
    Variables : $titre, $periode, $filtres, $entreprise ; section « contenu ».
--}}
@php($initiale = mb_strtoupper(mb_substr((string) $entreprise['nom'], 0, 1)))
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $titre }} — {{ $periode->libelle() }}</title>
    <style>
        @page { margin: 14mm 12mm 16mm; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 8.5pt; color: #111826; margin: 0; line-height: 1.3; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }
        .doux { color: #5b6475; }
        .droite { text-align: right; }
        .monogramme { width: 11mm; height: 11mm; background: #fe7802; color: #ffffff; font-size: 15pt; font-weight: bold; text-align: center; line-height: 11mm; border-radius: 2.5mm; }
        .logo { max-height: 13mm; max-width: 40mm; }
        .entreprise { font-size: 12pt; font-weight: bold; color: #c25400; }
        .titre { font-size: 16pt; font-weight: bold; }
        h2 { font-size: 10.5pt; margin: 6mm 0 2mm; color: #8a3c00; border-bottom: 0.5mm solid #fe7802; padding-bottom: 1mm; }
        .cartes td { border: 0.3mm solid #e2e5ea; border-radius: 2mm; padding: 2.5mm 3mm; width: 25%; }
        .cartes .valeur { font-size: 12pt; font-weight: bold; }
        .lignes th { background: #fff3e6; color: #8a3c00; font-size: 7.5pt; text-align: left; padding: 1.8mm 2mm; border-bottom: 0.4mm solid #fe7802; }
        .lignes th.droite { text-align: right; }
        .lignes td { padding: 1.6mm 2mm; border-bottom: 0.2mm solid #eceef2; }
        .lignes tr.bande td { background: #fafbfc; }
        .lignes tr.total td { font-weight: bold; border-top: 0.4mm solid #111826; }
        .barre { height: 2.6mm; background: #fe7802; border-radius: 1mm; }
        .negatif { color: #a3261b; }
        .pied { position: fixed; bottom: -9mm; left: 0; width: 100%; font-size: 7pt; color: #5b6475; }
        .pied .page:after { content: counter(page); }
    </style>
</head>
<body>
    <div class="pied">
        <table><tr>
            <td>{{ $entreprise['nom'] }} · {{ $titre }} · {{ $periode->libelle() }}</td>
            <td class="droite">Édité le {{ now()->translatedFormat('j F Y à H:i') }} · page <span class="page"></span></td>
        </tr></table>
    </div>

    <table>
        <tr>
            <td style="width: 55%;">
                <table><tr>
                    <td style="width: 15mm;">
                        @if ($entreprise['logo'])<img src="{{ $entreprise['logo'] }}" alt="" class="logo">@else<div class="monogramme">{{ $initiale }}</div>@endif
                    </td>
                    <td>
                        <div class="entreprise">{{ $entreprise['nom'] }}</div>
                        @if ($entreprise['adresse'])<div class="doux">{{ $entreprise['adresse'] }}</div>@endif
                    </td>
                </tr></table>
            </td>
            <td class="droite">
                <div class="titre">{{ mb_strtoupper($titre) }}</div>
                <div><strong>Période : {{ $periode->libelle() }}</strong></div>
                @foreach ($filtres as $nom => $valeur)<div class="doux">{{ $nom }} : {{ $valeur }}</div>@endforeach
            </td>
        </tr>
    </table>

    @yield('contenu')
</body>
</html>
