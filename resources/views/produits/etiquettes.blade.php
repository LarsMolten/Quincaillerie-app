{{--
    Planche d'étiquettes PDF (DomPDF) : A4, 3 colonnes × 8 lignes.
    DomPDF ne gère ni flexbox, ni grid, ni OKLCH : tables HTML et couleurs hexadécimales (CLAUDE.md §7).
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Étiquettes</title>
    <style>
        @page { margin: 8mm 6mm; }
        /* Helvetica : police PDF standard, non intégrée au fichier (PDF léger), accents français pris en charge */
        body { font-family: Helvetica, Arial, sans-serif; color: #111826; margin: 0; }
        table.planche { width: 100%; border-collapse: separate; border-spacing: 2mm; table-layout: fixed; }
        td.etiquette { width: 33.33%; height: 31mm; border: 0.3mm solid #e2e5ea; border-radius: 2mm; padding: 2mm 2.5mm; vertical-align: top; overflow: hidden; }
        .entreprise { font-size: 6pt; color: {{ $accent['principal'] }}; font-weight: bold; text-transform: uppercase; letter-spacing: 0.3pt; }
        .nom { font-size: 8pt; font-weight: bold; line-height: 1.15; height: 18pt; overflow: hidden; margin-top: 0.5mm; }
        .prix { font-size: 12pt; font-weight: bold; margin-top: 0.5mm; }
        .code { text-align: center; margin-top: 1mm; }
        .code img { height: 9mm; max-width: 100%; }
        .reference { font-size: 6pt; color: #5b6475; text-align: center; letter-spacing: 0.5pt; }
        .saut { page-break-after: always; }
    </style>
</head>
<body>
    @foreach ($pages as $page)
        <table class="planche @unless ($loop->last) saut @endunless">
            @foreach ($page->values()->chunk(3) as $ligne)
                <tr>
                    @foreach ($ligne as $etiquette)
                        <td class="etiquette">
                            <div class="entreprise">{{ $entreprise }}</div>
                            <div class="nom">{{ \Illuminate\Support\Str::limit($etiquette['produit']->nom, 60) }}</div>
                            <div class="prix">{{ format_ar($etiquette['produit']->prix_vente) }}<span style="font-size: 7pt; font-weight: normal; color: #5b6475;"> / {{ $etiquette['produit']->unite->abreviation }}</span></div>
                            @if ($etiquette['code'])
                                <div class="code"><img src="data:image/png;base64,{{ $etiquette['code'] }}" alt=""></div>
                            @endif
                            <div class="reference">{{ $etiquette['produit']->code_barres ?: $etiquette['produit']->reference }}</div>
                        </td>
                    @endforeach
                    @for ($i = $ligne->count(); $i < 3; $i++)
                        <td></td>
                    @endfor
                </tr>
            @endforeach
        </table>
    @endforeach
</body>
</html>
