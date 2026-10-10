{{-- Paiement dans la liste d'une fiche (vente, achat) : mode, date, auteur, montant et lien du reçu PDF --}}
<li class="flex items-center justify-between gap-3 px-carte py-3 text-sm">
    <div class="min-w-0">
        <p class="font-medium">{{ $paiement->mode->libelle() }}@if ($paiement->reference) · {{ $paiement->reference }}@endif</p>
        <p class="text-xs text-texte-doux">{{ $paiement->numero }} · {{ $paiement->date_paiement->translatedFormat('j M Y, H:i') }} · {{ $paiement->utilisateur->nom }}</p>
    </div>
    <div class="flex shrink-0 items-center gap-2">
        <p class="chiffres font-semibold">{{ format_ar($paiement->montant) }}</p>
        @droit('paiements.gerer')
            <a href="{{ route('paiements.recu', $paiement) }}" target="_blank" x-data x-ouvrir-pdf
               class="grid size-11 place-items-center rounded-controle text-texte-doux transition-colors duration-150 hover:bg-fond hover:text-texte"
               title="Reçu {{ $paiement->numero }}">
                <x-icone nom="printer" taille="size-4" />
                <span class="sr-only">Imprimer le reçu {{ $paiement->numero }}</span>
            </a>
        @enddroit
    </div>
</li>
