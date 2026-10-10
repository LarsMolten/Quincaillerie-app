{{-- Email d'envoi d'une facture (clients de messagerie : tables et couleurs hexadécimales, styles en ligne) --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Facture {{ $facture->numero }}</title>
</head>
<body style="margin: 0; padding: 0; background: #f6f7f9; font-family: Helvetica, Arial, sans-serif; color: #111826;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background: #f6f7f9; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 560px; background: #ffffff; border: 1px solid #e2e5ea; border-radius: 12px;">
                    <tr>
                        <td style="padding: 28px 32px 8px;">
                            <p style="margin: 0; font-size: 18px; font-weight: bold; color: #c25400;">{{ $entreprise['nom'] }}</p>
                            @if ($entreprise['adresse'])<p style="margin: 4px 0 0; font-size: 13px; color: #5b6475;">{{ $entreprise['adresse'] }}</p>@endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 16px 32px; font-size: 15px; line-height: 1.55;">
                            <p style="margin: 0 0 12px;">Bonjour{{ $vente->client && ! $vente->client->estComptoir() ? ' '.$vente->client->nom : '' }},</p>
                            @if ($messagePersonnel)
                                <p style="margin: 0 0 12px; white-space: pre-line;">{{ $messagePersonnel }}</p>
                            @else
                                <p style="margin: 0 0 12px;">Veuillez trouver ci-joint votre facture.</p>
                            @endif
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 16px 0; background: #fff3e6; border-radius: 8px;">
                                <tr>
                                    <td style="padding: 14px 16px; font-size: 14px; color: #8a3c00;">
                                        Facture <strong>{{ $facture->numero }}</strong><br>
                                        du {{ $facture->date_emission->translatedFormat('j F Y') }}
                                    </td>
                                    <td align="right" style="padding: 14px 16px; font-size: 20px; font-weight: bold; color: #8a3c00;">{{ format_ar($facture->total) }}</td>
                                </tr>
                            </table>
                            @if ((float) $vente->reste_a_payer > 0)
                                <p style="margin: 0 0 12px;">Reste à payer : <strong>{{ format_ar($vente->reste_a_payer) }}</strong>.</p>
                            @endif
                            <p style="margin: 0;">Merci de votre confiance.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 16px 32px 28px; border-top: 1px solid #eceef2; font-size: 12px; color: #8a909c;">
                            {{ $entreprise['nom'] }}@if ($entreprise['telephone']) · Tél. {{ $entreprise['telephone'] }}@endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
