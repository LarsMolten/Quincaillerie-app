{{--
    Bon d'achat PDF (DomPDF) : tables HTML et couleurs hexadécimales (pas de flexbox, grid ni OKLCH, CLAUDE.md §7).
    Police Helvetica (standard PDF, non intégrée : fichier léger).
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bon d'achat {{ $achat->numero }}</title>
    <style>
        @page { margin: 14mm 12mm; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 9.5pt; color: #111826; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        .entete td { vertical-align: top; }
        .entreprise { font-size: 15pt; font-weight: bold; }
        .accent { color: #fe7802; }
        .doux { color: #5b6475; }
        .titre { font-size: 18pt; font-weight: bold; text-align: right; letter-spacing: 0.5pt; }
        .bloc { border: 0.3mm solid #e2e5ea; border-radius: 2mm; padding: 3mm 4mm; }
        .lignes th { background: #fff3e6; color: #8a3c00; font-size: 8.5pt; text-align: left; padding: 2.2mm 2mm; border-bottom: 0.4mm solid #fe7802; }
        .lignes td { padding: 2mm; border-bottom: 0.2mm solid #e2e5ea; }
        .droite { text-align: right; }
        .totaux td { padding: 1.4mm 2mm; }
        .total-general td { font-size: 12pt; font-weight: bold; border-top: 0.4mm solid #111826; padding-top: 2.5mm; }
        .annule { position: fixed; top: 105mm; left: 0; width: 100%; text-align: center; font-size: 60pt; font-weight: bold; color: #f4c7c3; z-index: -1; }
        .signature { height: 22mm; border-bottom: 0.3mm solid #b9bfca; }
    </style>
</head>
<body>
    @if ($achat->statut === \App\Enums\StatutAchat::Annule)
        <div class="annule">ANNULÉ</div>
    @endif

    <table class="entete">
        <tr>
            <td style="width: 55%;">
                <div class="entreprise accent">{{ $entreprise['nom'] }}</div>
                <div class="doux">
                    @if ($entreprise['adresse']){{ $entreprise['adresse'] }}<br>@endif
                    @if ($entreprise['telephone'])Tél. {{ $entreprise['telephone'] }}@endif
                    @if ($entreprise['email']) · {{ $entreprise['email'] }}@endif
                    @if ($entreprise['nif_stat'])<br>NIF/STAT : {{ $entreprise['nif_stat'] }}@endif
                </div>
            </td>
            <td style="width: 45%;">
                <div class="titre">BON D'ACHAT</div>
                <div class="droite"><strong>{{ $achat->numero }}</strong><br><span class="doux">Date : {{ $achat->date_achat->translatedFormat('j F Y') }}</span></div>
            </td>
        </tr>
    </table>

    <table style="margin-top: 7mm;">
        <tr>
            <td class="bloc" style="width: 55%;">
                <span class="doux">Fournisseur</span><br>
                <strong>{{ $achat->fournisseur->nom }}</strong><br>
                @if ($achat->fournisseur->contact){{ $achat->fournisseur->contact }}<br>@endif
                Tél. {{ $achat->fournisseur->telephone }}
                @if ($achat->fournisseur->adresse)<br>{{ $achat->fournisseur->adresse }}@endif
            </td>
            <td style="width: 5%;"></td>
            <td class="bloc" style="width: 40%;">
                <span class="doux">Saisi par</span><br><strong>{{ $achat->utilisateur->nom }}</strong><br>
                <span class="doux">Statut :</span> {{ $achat->statut->libelle() }}
                @unless ($achat->statut === \App\Enums\StatutAchat::Annule) — {{ $achat->statut_paiement->libelle() }}@endunless
            </td>
        </tr>
    </table>

    <table class="lignes" style="margin-top: 7mm;">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th>Désignation</th>
                <th class="droite" style="width: 14%;">Quantité</th>
                <th class="droite" style="width: 17%;">Prix unitaire</th>
                <th class="droite" style="width: 18%;">Montant</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($achat->lignes as $ligne)
                <tr>
                    <td class="doux">{{ $loop->iteration }}</td>
                    <td>{{ $ligne->produit->nom }}<br><span class="doux" style="font-size: 8pt;">{{ $ligne->produit->reference }}</span></td>
                    <td class="droite">{{ format_quantite($ligne->quantite, $ligne->produit->unite?->abreviation) }}</td>
                    <td class="droite">{{ format_ar($ligne->prix_achat) }}</td>
                    <td class="droite">{{ format_ar($ligne->total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="margin-top: 5mm;">
        <tr>
            <td style="width: 55%; vertical-align: top;">
                @if ($achat->paiements->isNotEmpty())
                    <span class="doux">Paiements</span><br>
                    @foreach ($achat->paiements as $paiement)
                        {{ $paiement->date_paiement->translatedFormat('j M Y') }} — {{ $paiement->mode->libelle() }} : {{ format_ar($paiement->montant) }}<br>
                    @endforeach
                @endif
                @if ($achat->notes)<br><span class="doux">Notes :</span> {{ $achat->notes }}@endif
            </td>
            <td style="width: 45%;">
                <table class="totaux">
                    <tr class="total-general"><td>Total</td><td class="droite">{{ format_ar($achat->total) }}</td></tr>
                    <tr><td class="doux">Payé</td><td class="droite">{{ format_ar($achat->montant_paye) }}</td></tr>
                    <tr><td><strong>Reste à payer</strong></td><td class="droite"><strong>{{ format_ar($achat->statut === \App\Enums\StatutAchat::Annule ? 0 : $achat->reste_a_payer) }}</strong></td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table style="margin-top: 14mm;">
        <tr>
            <td style="width: 45%;"><span class="doux">Reçu par (magasin)</span><div class="signature"></div></td>
            <td style="width: 10%;"></td>
            <td style="width: 45%;"><span class="doux">Livré par (fournisseur)</span><div class="signature"></div></td>
        </tr>
    </table>
</body>
</html>
