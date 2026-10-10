{{--
    Bon de retour A4 (DomPDF) : tables HTML et couleurs hexadécimales (pas de flexbox, grid ni OKLCH, CLAUDE.md §7),
    même identité que les factures et bons d'achat. Données : RetourService::pdf().
--}}
@php
    $client = $retour->type === \App\Enums\TypeRetour::Client;
    $annule = $retour->statut === \App\Enums\StatutRetour::Annule;
    $initiale = mb_strtoupper(mb_substr((string) $entreprise['nom'], 0, 1));
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bon de retour {{ $retour->numero }}</title>
    <style>
        @page { margin: 16mm 15mm 22mm; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 9.5pt; color: #111826; margin: 0; line-height: 1.35; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }
        .doux { color: #5b6475; }
        .petit { font-size: 8pt; }
        .droite { text-align: right; }
        .monogramme { width: 13mm; height: 13mm; background: #fe7802; color: #ffffff; font-size: 18pt; font-weight: bold; text-align: center; line-height: 13mm; border-radius: 3mm; }
        .logo { max-height: 16mm; max-width: 45mm; }
        .entreprise { font-size: 15pt; font-weight: bold; color: #c25400; }
        .titre { font-size: 20pt; font-weight: bold; letter-spacing: 1pt; white-space: nowrap; }
        .numero { font-size: 11pt; font-weight: bold; }
        .etiquette { font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.6pt; color: #8a909c; margin-bottom: 1.5mm; }
        .bloc { border: 0.3mm solid #e2e5ea; border-radius: 2mm; padding: 4mm 5mm; }
        .lignes th { background: #fff3e6; color: #8a3c00; font-size: 8pt; text-align: left; padding: 2.5mm; border-bottom: 0.5mm solid #fe7802; }
        .lignes th.droite { text-align: right; }
        .lignes td { padding: 2.6mm 2.5mm; border-bottom: 0.2mm solid #eceef2; }
        .lignes tr.bande td { background: #fafbfc; }
        .totaux td { padding: 1.5mm 2.5mm; }
        .total td { background: #fff3e6; color: #8a3c00; font-size: 13pt; font-weight: bold; padding: 3mm 2.5mm; border-top: 0.5mm solid #fe7802; }
        .filigrane { position: fixed; top: 110mm; left: 0; width: 100%; text-align: center; font-size: 72pt; font-weight: bold; color: #f6d2ce; z-index: -1; }
        .pied { position: fixed; bottom: -12mm; left: 0; width: 100%; border-top: 0.3mm solid #e2e5ea; padding-top: 2.5mm; font-size: 7.5pt; color: #5b6475; text-align: center; }
        .signature { height: 20mm; border-bottom: 0.3mm solid #b9bfca; }
        .code-barres { height: 10mm; }
    </style>
</head>
<body>
    @if ($annule)
        <div class="filigrane">ANNULÉ</div>
    @endif

    <div class="pied">
        {{ $entreprise['nom'] }}@if ($entreprise['adresse']) · {{ $entreprise['adresse'] }}@endif @if ($entreprise['nif_stat']) · NIF/STAT {{ $entreprise['nif_stat'] }}@endif
    </div>

    <table>
        <tr>
            <td style="width: 50%;">
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
                        </td>
                    </tr>
                </table>
            </td>
            <td class="droite">
                <div class="titre">BON DE RETOUR</div>
                <div class="numero">{{ $retour->numero }}</div>
                <div class="doux">{{ $client ? 'Retour client' : 'Retour fournisseur' }} · {{ $retour->date_retour->translatedFormat('j F Y') }}</div>
                <div style="margin-top: 2mm;"><img src="data:image/png;base64,{{ $codeBarres }}" alt="" class="code-barres"></div>
            </td>
        </tr>
    </table>

    <table style="margin-top: 10mm;">
        <tr>
            <td style="width: 49%;" class="bloc">
                <div class="etiquette">{{ $client ? 'Client' : 'Fournisseur' }}</div>
                <strong>{{ $tiers ?? '—' }}</strong>
                <div class="doux" style="margin-top: 2mm;">Motif : {{ $retour->motif }}</div>
            </td>
            <td style="width: 2%;"></td>
            <td style="width: 49%;" class="bloc">
                <div class="etiquette">Document d'origine</div>
                @if ($client)
                    <strong>{{ $document->facture?->numero ?? $document->numero }}</strong>
                    @if ($document->facture)<div class="doux">Vente {{ $document->numero }}</div>@endif
                    <div class="doux">Du {{ $document->date_vente->translatedFormat('j F Y') }}</div>
                @else
                    <strong>{{ $document->numero }}</strong>
                    <div class="doux">Du {{ $document->date_achat->translatedFormat('j F Y') }}</div>
                @endif
                <div class="doux">Saisi par {{ $retour->utilisateur->nom }}</div>
            </td>
        </tr>
    </table>

    <table class="lignes" style="margin-top: 10mm;">
        <thead>
            <tr>
                <th style="width: 6%;">#</th>
                <th>Désignation</th>
                <th class="droite" style="width: 15%;">Quantité</th>
                <th class="droite" style="width: 17%;">Prix unitaire</th>
                <th class="droite" style="width: 18%;">Montant</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($retour->lignes as $ligne)
                <tr @class(['bande' => $loop->even])>
                    <td class="doux">{{ $loop->iteration }}</td>
                    <td>{{ $ligne->produit->nom }}<br><span class="doux petit">{{ $ligne->produit->reference }}</span></td>
                    <td class="droite">{{ format_quantite($ligne->quantite, $ligne->produit->unite?->abreviation) }}</td>
                    <td class="droite">{{ format_ar($ligne->prix_unitaire) }}</td>
                    <td class="droite">{{ format_ar($ligne->total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="margin-top: 8mm;">
        <tr>
            <td style="width: 50%;"></td>
            <td style="width: 50%;">
                <table class="totaux">
                    <tr class="total"><td>Montant du retour</td><td class="droite">{{ format_ar($retour->total) }}</td></tr>
                    <tr><td class="doux">{{ $client ? 'Déduit de la créance' : 'Déduit de la dette' }}</td><td class="droite">{{ format_ar($retour->montant_avoir) }}</td></tr>
                    <tr>
                        <td><strong>{{ $client ? 'Remboursé au client' : 'Remboursé par le fournisseur' }}</strong></td>
                        <td class="droite"><strong>{{ format_ar($retour->montant_rembourse) }}</strong></td>
                    </tr>
                    @if ($retour->mode_remboursement)
                        <tr><td class="doux">Mode de remboursement</td><td class="droite">{{ $retour->mode_remboursement->libelle() }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <table style="margin-top: 14mm;">
        <tr>
            <td style="width: 45%;"><span class="doux">{{ $client ? 'Reçu par (magasin)' : 'Remis par (magasin)' }}</span><div class="signature"></div></td>
            <td style="width: 10%;"></td>
            <td style="width: 45%;"><span class="doux">{{ $client ? 'Rendu par (client)' : 'Reçu par (fournisseur)' }}</span><div class="signature"></div></td>
        </tr>
    </table>
</body>
</html>
