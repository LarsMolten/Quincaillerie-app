{{--
    Facture au format ticket de caisse 80 mm (DomPDF) : tables HTML et couleurs hexadécimales
    (pas de flexbox, grid ni OKLCH, CLAUDE.md §7). Police Helvetica (standard PDF, non intégrée : fichier léger).
    Données : FactureService::donnees() ; hauteur du papier calculée par FactureService::pdf().
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Ticket {{ $facture->numero }}</title>
    <style>
        @page { margin: 4mm 4mm; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 8pt; color: #111826; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        .centre { text-align: center; }
        .droite { text-align: right; }
        .doux { color: #5b6475; }
        .entreprise { font-size: 12pt; font-weight: bold; color: {{ $accent['fonce'] }}; }
        .logo { max-height: 14mm; max-width: 40mm; margin-bottom: 1mm; }
        .separateur { border-top: 0.3mm dashed #8a909c; margin: 2.5mm 0; }
        .lignes td { padding: 0.8mm 0; vertical-align: top; }
        .lignes .detail td { padding-top: 0; color: #5b6475; font-size: 7.5pt; }
        .total td { font-size: 12pt; font-weight: bold; padding-top: 1.5mm; }
        .annulee { border: 0.5mm solid #b42318; color: #b42318; font-weight: bold; font-size: 11pt; text-align: center; padding: 1mm; margin-bottom: 2mm; }
        .code-barres { height: 9mm; }
    </style>
</head>
<body>
    @if ($annulee)
        <div class="annulee">FACTURE ANNULÉE</div>
    @endif

    <div class="centre">
        @if ($entreprise['logo'])<img src="{{ $entreprise['logo'] }}" alt="" class="logo"><br>@endif
        <div class="entreprise">{{ $entreprise['nom'] }}</div>
        @if ($entreprise['adresse'])<div class="doux">{{ $entreprise['adresse'] }}</div>@endif
        @if ($entreprise['telephone'])<div class="doux">Tél. {{ $entreprise['telephone'] }}</div>@endif
        @if ($entreprise['nif_stat'])<div class="doux">NIF/STAT : {{ $entreprise['nif_stat'] }}</div>@endif
    </div>

    <div class="separateur"></div>

    <table>
        <tr><td><strong>Facture {{ $facture->numero }}</strong></td><td class="droite">{{ $facture->date_emission->format('d/m/Y H:i') }}</td></tr>
        <tr><td class="doux">Vente</td><td class="droite">{{ $vente->numero }}</td></tr>
        <tr><td class="doux">Client</td><td class="droite">{{ $vente->client?->nom ?? '—' }}</td></tr>
        <tr><td class="doux">Vendeur</td><td class="droite">{{ $vente->utilisateur->nom }}</td></tr>
    </table>

    <div class="separateur"></div>

    <table class="lignes">
        @foreach ($vente->lignes as $ligne)
            <tr><td colspan="2">{{ $ligne->produit->nom }}</td></tr>
            <tr class="detail">
                <td>{{ format_quantite($ligne->quantite, $ligne->produit->unite?->abreviation) }} × {{ format_ar($ligne->prix_unitaire) }}@if ((float) $ligne->remise > 0) (remise {{ format_ar($ligne->remise) }})@endif</td>
                <td class="droite">{{ format_ar($ligne->total) }}</td>
            </tr>
        @endforeach
    </table>

    <div class="separateur"></div>

    <table>
        @if ((float) $vente->remise > 0)
            <tr><td>Sous-total</td><td class="droite">{{ format_ar($vente->sous_total) }}</td></tr>
            <tr><td>Remise</td><td class="droite">- {{ format_ar($vente->remise) }}</td></tr>
        @endif
        <tr class="total"><td>TOTAL</td><td class="droite">{{ format_ar($vente->total) }}</td></tr>
        @if ($tva)
            <tr><td class="doux">dont total HT</td><td class="droite doux">{{ format_ar($tva['ht']) }}</td></tr>
            <tr><td class="doux">dont TVA {{ str_replace('.', ',', (string) $tva['taux']) }} %</td><td class="droite doux">{{ format_ar($tva['montant']) }}</td></tr>
        @endif
        <tr><td class="doux">Mode de paiement</td><td class="droite">{{ $vente->mode_paiement->libelle() }}</td></tr>
        @if ($vente->montant_recu !== null)
            <tr><td class="doux">Reçu</td><td class="droite">{{ format_ar($vente->montant_recu) }}</td></tr>
            @if ((float) $vente->monnaie_rendue > 0)
                <tr><td><strong>Monnaie rendue</strong></td><td class="droite"><strong>{{ format_ar($vente->monnaie_rendue) }}</strong></td></tr>
            @endif
        @endif
        @if (! $annulee && (float) $vente->reste_a_payer > 0)
            <tr><td><strong>Reste à payer</strong></td><td class="droite"><strong>{{ format_ar($vente->reste_a_payer) }}</strong></td></tr>
        @endif
    </table>

    <div class="separateur"></div>

    <div class="centre">
        <img src="data:image/png;base64,{{ $codeBarres }}" alt="" class="code-barres"><br>
        <span class="doux">{{ $facture->numero }}</span>
    </div>

    @if ($entreprise['pied'])
        <p class="centre doux">{{ $entreprise['pied'] }}</p>
    @endif
    <p class="centre">Merci de votre visite !</p>
</body>
</html>
