{{-- Détail d'un client débiteur (fragment de la page Créances) : factures impayées et encaissement --}}
<ul class="divide-y divide-bordure rounded-controle border border-bordure bg-surface" aria-label="Factures impayées de {{ $client->nom }}">
    @forelse ($ventes as $vente)
        @php($jours = \App\Http\Controllers\CreanceController::jours($vente->date_vente))
        <li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3">
            <div class="min-w-0 flex-1">
                <a href="{{ route('ventes.show', $vente) }}" class="font-medium text-texte hover:underline">{{ $vente->facture?->numero ?? $vente->numero }}</a>
                <p class="text-xs text-texte-doux">
                    {{ $vente->date_vente->translatedFormat('j M Y') }} · il y a {{ $jours }} jour(s)
                    @if ($vente->facture) · vente {{ $vente->numero }}@endif
                </p>
            </div>
            <dl class="chiffres grid grid-cols-3 gap-x-4 text-right text-sm">
                <div><dt class="text-xs text-texte-doux">Total</dt><dd>{{ format_ar($vente->total) }}</dd></div>
                <div><dt class="text-xs text-texte-doux">Payé</dt><dd>{{ format_ar($vente->montant_paye) }}</dd></div>
                <div><dt class="text-xs text-texte-doux">Reste</dt><dd class="font-semibold text-danger-texte">{{ format_ar($vente->reste_a_payer) }}</dd></div>
            </dl>
            <x-bouton type="button" taille="sm" icone="hand-coins"
                      x-on:click="ouvrirReglement({{ \Illuminate\Support\Js::from([
                          'url' => route('paiements.ventes.store', $vente),
                          'document' => $vente->facture?->numero ?? $vente->numero,
                          'tiers' => $client->nom,
                          'reste' => (float) $vente->reste_a_payer,
                      ]) }})">
                Encaisser <span class="sr-only">{{ $vente->facture?->numero ?? $vente->numero }}</span>
            </x-bouton>
        </li>
    @empty
        <li class="px-4 py-3 text-sm text-texte-doux">Plus aucune facture impayée.</li>
    @endforelse
</ul>
