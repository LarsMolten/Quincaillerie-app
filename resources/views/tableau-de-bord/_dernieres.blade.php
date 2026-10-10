{{-- Carte « Dernières ventes » : numéro, client, montant et état de paiement --}}
@if ($ventes->isEmpty())
    <x-etat-vide icone="receipt" titre="Aucune vente" texte="Les ventes de la caisse apparaîtront ici." />
@else
    <ul class="divide-y divide-bordure text-sm" aria-label="Dernières ventes">
        @foreach ($ventes as $vente)
            @php($annulee = $vente->statut === \App\Enums\StatutVente::Annulee)
            <li @class(['flex items-center justify-between gap-3 py-2.5', 'opacity-60' => $annulee])>
                <div class="min-w-0">
                    <a href="{{ route('ventes.show', $vente) }}" class="font-medium hover:underline">{{ $vente->facture?->numero ?? $vente->numero }}</a>
                    <p class="truncate text-xs text-texte-doux">{{ $vente->client?->nom ?? '—' }} · {{ $vente->date_vente->diffForHumans() }}</p>
                </div>
                <div class="flex shrink-0 flex-col items-end gap-1">
                    <span class="chiffres font-semibold">{{ format_ar($vente->total) }}</span>
                    @if ($annulee)
                        <x-badge :statut="$vente->statut" />
                    @else
                        <x-badge :statut="$vente->statut_paiement" />
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
@endif
