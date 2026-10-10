{{--
    Reçu de paiement A5 (DomPDF) : tables HTML et couleurs hexadécimales (pas de flexbox, grid ni OKLCH, CLAUDE.md §7),
    même identité que les factures : accent orange, Helvetica, beaucoup d'espace.
    Données : PaiementService::donnees().
--}}
@php
    $initiale = mb_strtoupper(mb_substr((string) $entreprise['nom'], 0, 1));
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Reçu {{ $paiement->numero }}</title>
    <style>
        @page { margin: 12mm 12mm 18mm; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 9pt; color: #111826; margin: 0; line-height: 1.35; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; }
        .doux { color: #5b6475; }
        .droite { text-align: right; }
        .centre { text-align: center; }
        .monogramme { width: 11mm; height: 11mm; background: {{ $accent['principal'] }}; color: #ffffff; font-size: 15pt; font-weight: bold; text-align: center; line-height: 11mm; border-radius: 2.5mm; }
        .logo { max-height: 13mm; max-width: 36mm; }
        .entreprise { font-size: 12pt; font-weight: bold; color: {{ $accent['fonce'] }}; }
        .titre { font-size: 13pt; font-weight: bold; letter-spacing: 0.6pt; white-space: nowrap; }
        .numero { font-size: 10pt; font-weight: bold; }
        .etiquette { font-size: 7pt; text-transform: uppercase; letter-spacing: 0.6pt; color: #8a909c; margin-bottom: 1mm; }
        .bloc { border: 0.3mm solid #e2e5ea; border-radius: 2mm; padding: 3mm 4mm; }
        .montant { background: {{ $accent['doux'] }}; border-top: 0.5mm solid {{ $accent['principal'] }}; padding: 5mm 4mm; }
        .montant .valeur { font-size: 20pt; font-weight: bold; color: {{ $accent['sombre'] }}; }
        .situation td { padding: 1.4mm 0; border-bottom: 0.2mm solid #eceef2; }
        .situation tr.fin td { border-bottom: 0; font-weight: bold; font-size: 10pt; }
        .annule { border: 0.5mm solid #b42318; color: #b42318; font-weight: bold; text-align: center; padding: 1.5mm; margin-bottom: 4mm; }
        .pied { position: fixed; bottom: -10mm; left: 0; width: 100%; border-top: 0.3mm solid #e2e5ea; padding-top: 2mm; font-size: 7pt; color: #5b6475; text-align: center; }
        .code-barres { height: 9mm; }
    </style>
</head>
<body>
    <div class="pied">
        @if ($estVente && $entreprise['pied']){{ $entreprise['pied'] }}<br>@endif
        {{ $entreprise['nom'] }}@if ($entreprise['adresse']) · {{ $entreprise['adresse'] }}@endif @if ($entreprise['nif_stat']) · NIF/STAT {{ $entreprise['nif_stat'] }}@endif
    </div>

    {{-- En-tête : entreprise à gauche, reçu à droite --}}
    <table>
        <tr>
            <td style="width: 50%;">
                <table>
                    <tr>
                        <td style="width: 15mm;">
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
                <div class="titre">{{ $estVente ? 'REÇU DE PAIEMENT' : 'PAIEMENT FOURNISSEUR' }}</div>
                <div class="numero">{{ $paiement->numero }}</div>
                <div class="doux">{{ $paiement->date_paiement->translatedFormat('j F Y à H:i') }}</div>
                <div style="margin-top: 1.5mm;"><img src="data:image/png;base64,{{ $codeBarres }}" alt="" class="code-barres"></div>
            </td>
        </tr>
    </table>

    @if ($annule)
        <div class="annule" style="margin-top: 6mm;">DOCUMENT {{ $document->numero }} ANNULÉ</div>
    @endif

    {{-- Tiers et document réglé --}}
    <table style="margin-top: 7mm;">
        <tr>
            <td style="width: 49%;" class="bloc">
                <div class="etiquette">{{ $estVente ? 'Reçu de' : 'Versé à' }}</div>
                <strong>{{ $tiers ?? '—' }}</strong>
            </td>
            <td style="width: 2%;"></td>
            <td style="width: 49%;" class="bloc">
                <div class="etiquette">{{ $estVente ? 'En règlement de la facture' : 'En règlement de l\'achat' }}</div>
                <strong>{{ $reference }}</strong>
                @if ($estVente && $reference !== $document->numero)<div class="doux">Vente {{ $document->numero }}</div>@endif
            </td>
        </tr>
    </table>

    {{-- Montant --}}
    <table style="margin-top: 7mm;">
        <tr>
            <td class="montant">
                <div class="etiquette">Montant {{ $estVente ? 'reçu' : 'versé' }}</div>
                <div class="valeur">{{ format_ar($paiement->montant) }}</div>
                <div class="doux">{{ $paiement->mode->libelle() }}@if ($paiement->reference) · Réf. {{ $paiement->reference }}@endif</div>
            </td>
        </tr>
    </table>

    {{-- Situation du document --}}
    <table class="situation" style="margin-top: 6mm;">
        <tr><td class="doux">Total du document</td><td class="droite">{{ format_ar($total) }}</td></tr>
        <tr><td class="doux">Déjà réglé auparavant</td><td class="droite">{{ format_ar($avant) }}</td></tr>
        <tr><td class="doux">Ce paiement</td><td class="droite">{{ format_ar($paiement->montant) }}</td></tr>
        <tr class="fin"><td>Reste à payer après ce paiement</td><td class="droite">{{ $resteApres > 0 ? format_ar($resteApres) : 'Soldé' }}</td></tr>
    </table>

    <table style="margin-top: 10mm;">
        <tr>
            <td class="doux">{{ $estVente ? 'Encaissé' : 'Payé' }} par {{ $paiement->utilisateur->nom }}</td>
            <td class="droite doux">Signature</td>
        </tr>
    </table>
</body>
</html>
