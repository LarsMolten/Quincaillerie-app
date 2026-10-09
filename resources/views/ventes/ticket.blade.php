{{--
    Ticket de caisse 80 mm (DomPDF) : tables HTML et couleurs hexadécimales (pas de flexbox, grid ni OKLCH, CLAUDE.md §7).
    Police Helvetica (standard PDF, non intégrée : fichier léger). La hauteur du papier est calculée par le contrôleur.
--}}
@php($annulee = $vente->statut === \App\Enums\StatutVente::Annulee)
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Ticket {{ $vente->numero }}</title>
    <style>
        @page { margin: 4mm 4mm; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 8pt; color: #111826; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        .centre { text-align: center; }
        .droite { text-align: right; }
        .doux { color: #5b6475; }
        .entreprise { font-size: 12pt; font-weight: bold; color: #c25400; }
        .separateur { border-top: 0.3mm dashed #8a909c; margin: 2.5mm 0; }
        .lignes td { padding: 0.8mm 0; vertical-align: top; }
        .lignes .detail td { padding-top: 0; color: #5b6475; font-size: 7.5pt; }
        .total td { font-size: 12pt; font-weight: bold; padding-top: 1.5mm; }
        .annulee { border: 0.5mm solid #b42318; color: #b42318; font-weight: bold; font-size: 11pt; text-align: center; padding: 1mm; margin-bottom: 2mm; }
    </style>
</head>
<body>
    @if ($annulee)
        <div class="annulee">VENTE ANNULÉE</div>
    @endif

    <div class="centre">
        <div class="entreprise">{{ $entreprise['nom'] }}</div>
        @if ($entreprise['adresse'])<div class="doux">{{ $entreprise['adresse'] }}</div>@endif
        @if ($entreprise['telephone'])<div class="doux">Tél. {{ $entreprise['telephone'] }}</div>@endif
        @if ($entreprise['nif_stat'])<div class="doux">NIF/STAT : {{ $entreprise['nif_stat'] }}</div>@endif
    </div>

    <div class="separateur"></div>

    <table>
        <tr><td><strong>{{ $vente->numero }}</strong></td><td class="droite">{{ $vente->date_vente->format('d/m/Y H:i') }}</td></tr>
        @if ($vente->facture)<tr><td class="doux">Facture</td><td class="droite">{{ $vente->facture->numero }}</td></tr>@endif
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
            <tr><td>Remise</td><td class="droite">− {{ format_ar($vente->remise) }}</td></tr>
        @endif
        <tr class="total"><td>TOTAL</td><td class="droite">{{ format_ar($vente->total) }}</td></tr>
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

    @if ($entreprise['pied'])
        <p class="centre doux">{{ $entreprise['pied'] }}</p>
    @endif
    <p class="centre">Merci de votre visite !</p>
</body>
</html>
