<?php

namespace App\Mail;

use App\Models\Facture;
use App\Models\Parametre;
use App\Services\FactureService;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Facture envoyée au client, PDF A4 en pièce jointe.
 */
class FactureMail extends Mailable
{
    public function __construct(
        public readonly Facture $facture,
        public readonly ?string $messagePersonnel,
        private readonly FactureService $factures,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Facture {$this->facture->numero} — ".Parametre::valeur('nom_entreprise', config('app.name')),
        );
    }

    public function content(): Content
    {
        $this->facture->loadMissing('vente.client');

        return new Content(
            view: 'emails.facture',
            with: [
                'facture' => $this->facture,
                'vente' => $this->facture->vente,
                'messagePersonnel' => $this->messagePersonnel,
                'entreprise' => [
                    'nom' => Parametre::valeur('nom_entreprise', config('app.name')),
                    'adresse' => Parametre::valeur('adresse'),
                    'telephone' => Parametre::valeur('telephone'),
                ],
            ],
        );
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        $pdf = $this->factures->pdf($this->facture);

        return [
            Attachment::fromData(fn () => $pdf->output(), $this->factures->nomFichier($this->facture))->withMime('application/pdf'),
        ];
    }
}
