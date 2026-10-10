{{-- Détail d'un fournisseur (fragment de la page Dettes fournisseurs) : achats impayés et règlement --}}
<ul class="divide-y divide-bordure rounded-controle border border-bordure bg-surface" aria-label="Achats impayés auprès de {{ $fournisseur->nom }}">
    @forelse ($achats as $achat)
        @php($jours = \App\Http\Controllers\CreanceController::jours($achat->date_achat))
        <li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3">
            <div class="min-w-0 flex-1">
                <a href="{{ route('achats.show', $achat) }}" class="font-medium text-texte hover:underline">{{ $achat->numero }}</a>
                <p class="text-xs text-texte-doux">{{ $achat->date_achat->translatedFormat('j M Y') }} · il y a {{ $jours }} jour(s)</p>
            </div>
            <dl class="chiffres grid grid-cols-3 gap-x-4 text-right text-sm">
                <div><dt class="text-xs text-texte-doux">Total</dt><dd>{{ format_ar($achat->total) }}</dd></div>
                <div><dt class="text-xs text-texte-doux">Payé</dt><dd>{{ format_ar($achat->montant_paye) }}</dd></div>
                <div><dt class="text-xs text-texte-doux">Reste</dt><dd class="font-semibold text-danger-texte">{{ format_ar($achat->reste_a_payer) }}</dd></div>
            </dl>
            <x-bouton type="button" taille="sm" icone="banknote"
                      x-on:click="ouvrirReglement({{ \Illuminate\Support\Js::from([
                          'url' => route('achats.paiements.store', $achat),
                          'document' => $achat->numero,
                          'tiers' => $fournisseur->nom,
                          'reste' => (float) $achat->reste_a_payer,
                      ]) }})">
                Régler <span class="sr-only">{{ $achat->numero }}</span>
            </x-bouton>
        </li>
    @empty
        <li class="px-4 py-3 text-sm text-texte-doux">Plus aucun achat impayé.</li>
    @endforelse
</ul>
