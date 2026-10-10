{{--
    Facture A4 (DomPDF) : tables HTML et couleurs hexadécimales (pas de flexbox, grid ni OKLCH, CLAUDE.md §7),
    même identité que l'application : accent orange, typographie sobre (Helvetica, non intégrée), beaucoup d'espace.
    Données : FactureService::donnees().
--}}
@php
    $initiale = mb_strtoupper(mb_substr((string) $entreprise['nom'], 0, 1));
    $paiement = $vente->statut_paiement;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Facture {{ $facture->numero }}</title>
    <style>
        @page { margin: 16mm 15mm 22mm; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 9.5pt; color: #111826; margin: 0; line-height: 1.35; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }
        .doux { color: #5b6475; }
        .petit { font-size: 8pt; }
        .droite { text-align: right; }
        .centre { text-align: center; }
        .monogramme { width: 13mm; height: 13mm; background: #fe7802; color: #ffffff; font-size: 18pt; font-weight: bold; text-align: center; line-height: 13mm; border-radius: 3mm; }
        .logo { max-height: 16mm; max-width: 45mm; }
        .entreprise { font-size: 15pt; font-weight: bold; color: #c25400; }
        .titre { font-size: 24pt; font-weight: bold; letter-spacing: 1pt; color: #111826; }
        .numero { font-size: 11pt; font-weight: bold; }
        .etiquette { font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.6pt; color: #8a909c; margin-bottom: 1.5mm; }
        .bloc { border: 0.3mm solid #e2e5ea; border-radius: 2mm; padding: 4mm 5mm; }
        .lignes th { background: #fff3e6; color: #8a3c00; font-size: 8pt; text-align: left; padding: 2.5mm 2.5mm; border-bottom: 0.5mm solid #fe7802; }
        .lignes th.droite { text-align: right; }
        .lignes td { padding: 2.6mm 2.5mm; border-bottom: 0.2mm solid #eceef2; }
        .lignes tr.bande td { background: #fafbfc; }
        .totaux td { padding: 1.5mm 2.5mm; }
        .a-payer td { background: #fff3e6; color: #8a3c00; font-size: 14pt; font-weight: bold; padding: 3.5mm 2.5mm; border-top: 0.5mm solid #fe7802; }
        .badge { display: inline-block; padding: 0.8mm 2.5mm; border-radius: 3mm; font-size: 8pt; font-weight: bold; }
        .badge-succes { background: #e3f6ea; color: #116133; }
        .badge-alerte { background: #fff2d9; color: #8a5100; }
        .badge-danger { background: #fde7e5; color: #a3261b; }
        .filigrane { position: fixed; top: 110mm; left: 0; width: 100%; text-align: center; font-size: 72pt; font-weight: bold; color: #f6d2ce; z-index: -1; }
        .pied { position: fixed; bottom: -12mm; left: 0; width: 100%; border-top: 0.3mm solid #e2e5ea; padding-top: 2.5mm; font-size: 7.5pt; color: #5b6475; text-align: center; }
        .code-barres { height: 10mm; }
    </style>
</head>
<body>
    @if ($annulee)
        <div class="filigrane">ANNULÉE</div>
    @endif

    <div class="pied">
        @if ($entreprise['pied']){{ $entreprise['pied'] }}<br>@endif
        {{ $entreprise['nom'] }}@if ($entreprise['adresse']) · {{ $entreprise['adresse'] }}@endif @if ($entreprise['nif_stat']) · NIF/STAT {{ $entreprise['nif_stat'] }}@endif
    </div>

    {{-- En-tête : entreprise à gauche, facture à droite --}}
    <table>
        <tr>
            <td style="width: 55%;">
                <table>
                    <tr>
                        <td style="width: 18mm;">
                            @if ($entreprise['logo'])
                                <img src="{{ $entreprise['logo'] }}" alt="" class="logo">
                            @else
                                <div class="monogramme">{{ $initiale }}</div>
                            @endif
                        </td>
                        <td>
                            <div class="entreprise">{{ $entreprise['nom'] }}</div>
                            @if ($entreprise['adresse'])<div class="doux">{{ $entreprise['adresse'] }}</div>@endif
                            @if ($entreprise['telephone'])<div class="doux">Tél. {{ $entreprise['telephone'] }}</div>@endif
                            @if ($entreprise['email'])<div class="doux">{{ $entreprise['email'] }}</div>@endif
                            @if ($entreprise['nif_stat'])<div class="doux">NIF/STAT : {{ $entreprise['nif_stat'] }}</div>@endif
                        </td>
                    </tr>
                </table>
            </td>
            <td class="droite">
                <div class="titre">FACTURE</div>
                <div class="numero">{{ $facture->numero }}</div>
                <div class="doux">Émise le {{ $facture->date_emission->translatedFormat('j F Y à H:i') }}</div>
                <div style="margin-top: 2mm;"><img src="data:image/png;base64,{{ $codeBarres }}" alt="" class="code-barres"></div>
            </td>
        </tr>
    </table>

    {{-- Client / vente --}}
    <table style="margin-top: 10mm;">
        <tr>
            <td style="width: 49%;" class="bloc">
                <div class="etiquette">Facturé à</div>
                <strong>{{ $vente->client?->nom ?? '—' }}</strong>
                @if ($vente->client?->telephone)<div class="doux">Tél. {{ $vente->client->telephone }}</div>@endif
                @if ($vente->client?->adresse)<div class="doux">{{ $vente->client->adresse }}</div>@endif
                @if ($vente->client?->email)<div class="doux">{{ $vente->client->email }}</div>@endif
            </td>
            <td style="width: 2%;"></td>
            <td style="width: 49%;" class="bloc">
                <div class="etiquette">Vente</div>
                <table>
                    <tr><td class="doux">Numéro</td><td class="droite">{{ $vente->numero }}</td></tr>
                    <tr><td class="doux">Vendeur</td><td class="droite">{{ $vente->utilisateur->nom }}</td></tr>
                    <tr><td class="doux">Paiement</td><td class="droite">{{ $vente->mode_paiement->libelle() }}</td></tr>
                    <tr>
                        <td class="doux">Statut</td>
                        <td class="droite">
                            @if ($annulee)
                                <span class="badge badge-danger">Annulée</span>
                            @else
                                <span class="badge badge-{{ $paiement->couleur() }}">{{ $paiement->libelle() }}</span>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Lignes à bandes légères --}}
    <table class="lignes" style="margin-top: 10mm;">
        <thead>
            <tr>
                <th style="width: 6%;">#</th>
                <th>Désignation</th>
                <th class="droite" style="width: 13%;">Quantité</th>
                <th class="droite" style="width: 15%;">Prix unitaire</th>
                <th class="droite" style="width: 12%;">Remise</th>
                <th class="droite" style="width: 16%;">Montant</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($vente->lignes as $ligne)
                <tr @class(['bande' => $loop->even])>
                    <td class="doux">{{ $loop->iteration }}</td>
                    <td>{{ $ligne->produit->nom }}<br><span class="doux petit">{{ $ligne->produit->reference }}</span></td>
                    <td class="droite">{{ format_quantite($ligne->quantite, $ligne->produit->unite?->abreviation) }}</td>
                    <td class="droite">{{ format_ar($ligne->prix_unitaire) }}</td>
                    <td class="droite doux">{{ (float) $ligne->remise > 0 ? '- '.format_ar($ligne->remise) : '—' }}</td>
                    <td class="droite">{{ format_ar($ligne->total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Totaux --}}
    <table style="margin-top: 8mm;">
        <tr>
            <td style="width: 50%;">
                @if ($vente->paiements->isNotEmpty())
                    <div class="etiquette">Règlements</div>
                    @foreach ($vente->paiements as $reglement)
                        <div class="petit">{{ $reglement->date_paiement->translatedFormat('j M Y') }} — {{ $reglement->mode->libelle() }} : {{ format_ar($reglement->montant) }}</div>
                    @endforeach
                @endif
            </td>
            <td style="width: 50%;">
                <table class="totaux">
                    <tr><td class="doux">Sous-total</td><td class="droite">{{ format_ar($vente->sous_total) }}</td></tr>
                    <tr><td class="doux">Remise</td><td class="droite">{{ (float) $vente->remise > 0 ? '- '.format_ar($vente->remise) : '—' }}</td></tr>
                    <tr class="a-payer"><td>Total à payer</td><td class="droite">{{ format_ar($vente->total) }}</td></tr>
                    @if ($tva)
                        <tr><td class="doux petit">dont total HT</td><td class="droite doux petit">{{ format_ar($tva['ht']) }}</td></tr>
                        <tr><td class="doux petit">dont TVA {{ str_replace('.', ',', (string) $tva['taux']) }} %</td><td class="droite doux petit">{{ format_ar($tva['montant']) }}</td></tr>
                    @endif
                    <tr><td class="doux">Montant payé</td><td class="droite">{{ format_ar($vente->montant_paye) }}</td></tr>
                    <tr><td><strong>Reste à payer</strong></td><td class="droite"><strong>{{ format_ar($annulee ? 0 : $vente->reste_a_payer) }}</strong></td></tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
