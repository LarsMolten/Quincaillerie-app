{{--
    Reçu de paiement au format ticket 80 mm (DomPDF) : tables HTML et couleurs hexadécimales (CLAUDE.md §7).
    Données : PaiementService::donnees() ; hauteur du papier calculée par PaiementService::pdf().
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Reçu {{ $paiement->numero }}</title>
    <style>
        @page { margin: 4mm 4mm; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 8pt; color: #111826; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 0.6mm 0; vertical-align: top; }
        .centre { text-align: center; }
        .droite { text-align: right; }
        .doux { color: #5b6475; }
        .entreprise { font-size: 12pt; font-weight: bold; color: {{ $accent['fonce'] }}; }
        .logo { max-height: 14mm; max-width: 40mm; margin-bottom: 1mm; }
        .titre { font-size: 10pt; font-weight: bold; letter-spacing: 0.5pt; }
        .separateur { border-top: 0.3mm dashed #8a909c; margin: 2.5mm 0; }
        .montant { font-size: 15pt; font-weight: bold; }
        .fin td { font-weight: bold; font-size: 9pt; padding-top: 1.2mm; }
        .annule { border: 0.5mm solid #b42318; color: #b42318; font-weight: bold; text-align: center; padding: 1mm; margin-bottom: 2mm; }
        .code-barres { height: 9mm; }
    </style>
</head>
<body>
    @if ($annule)
        <div class="annule">DOCUMENT {{ $document->numero }} ANNULÉ</div>
    @endif

    <div class="centre">
        @if ($entreprise['logo'])<img src="{{ $entreprise['logo'] }}" alt="" class="logo"><br>@endif
        <div class="entreprise">{{ $entreprise['nom'] }}</div>
        @if ($entreprise['adresse'])<div class="doux">{{ $entreprise['adresse'] }}</div>@endif
        @if ($entreprise['telephone'])<div class="doux">Tél. {{ $entreprise['telephone'] }}</div>@endif
    </div>

    <div class="separateur"></div>

    <div class="centre">
        <div class="titre">{{ $estVente ? 'REÇU DE PAIEMENT' : 'PAIEMENT FOURNISSEUR' }}</div>
        <div><strong>{{ $paiement->numero }}</strong></div>
        <div class="doux">{{ $paiement->date_paiement->format('d/m/Y H:i') }}</div>
    </div>

    <div class="separateur"></div>

    <table>
        <tr><td class="doux">{{ $estVente ? 'Client' : 'Fournisseur' }}</td><td class="droite">{{ $tiers ?? '—' }}</td></tr>
        <tr><td class="doux">{{ $estVente ? 'Facture' : 'Achat' }}</td><td class="droite">{{ $reference }}</td></tr>
        <tr><td class="doux">Mode</td><td class="droite">{{ $paiement->mode->libelle() }}</td></tr>
        @if ($paiement->reference)
            <tr><td class="doux">Référence</td><td class="droite">{{ $paiement->reference }}</td></tr>
        @endif
    </table>

    <div class="separateur"></div>

    <div class="centre">
        <div class="doux">Montant {{ $estVente ? 'reçu' : 'versé' }}</div>
        <div class="montant">{{ format_ar($paiement->montant) }}</div>
    </div>

    <div class="separateur"></div>

    <table>
        <tr><td class="doux">Total du document</td><td class="droite">{{ format_ar($total) }}</td></tr>
        <tr><td class="doux">Déjà réglé</td><td class="droite">{{ format_ar($avant) }}</td></tr>
        <tr class="fin"><td>Reste à payer</td><td class="droite">{{ $resteApres > 0 ? format_ar($resteApres) : 'Soldé' }}</td></tr>
    </table>

    <div class="separateur"></div>

    <div class="centre">
        <div class="doux">{{ $estVente ? 'Encaissé' : 'Payé' }} par {{ $paiement->utilisateur->nom }}</div>
        <div style="margin-top: 2mm;"><img src="data:image/png;base64,{{ $codeBarres }}" alt="" class="code-barres"></div>
        @if ($estVente && $entreprise['pied'])<div class="doux" style="margin-top: 1.5mm;">{{ $entreprise['pied'] }}</div>@endif
    </div>
</body>
</html>
