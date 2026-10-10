{{-- Carte « Top 5 produits du mois » : barres horizontales proportionnelles au chiffre d'affaires --}}
@if ($produits->isEmpty())
    <x-etat-vide icone="chart-column" titre="Aucune vente ce mois-ci" texte="Le classement apparaîtra dès les premières ventes." />
@else
    <ol class="space-y-3" aria-label="Produits les plus vendus ce mois-ci">
        @foreach ($produits as $produit)
            <li>
                <div class="mb-1 flex items-baseline justify-between gap-3 text-sm">
                    <a href="{{ route('produits.show', $produit->id) }}" class="min-w-0 truncate font-medium hover:underline">
                        <span class="chiffres text-texte-doux">{{ $loop->iteration }}.</span> {{ $produit->nom }}
                    </a>
                    <span class="chiffres shrink-0 font-semibold">{{ format_ar($produit->chiffre) }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-neutre-doux" aria-hidden="true">
                        <div class="h-full rounded-full bg-primaire" style="width: {{ max(2, $produit->part) }}%"></div>
                    </div>
                    <span class="chiffres w-20 text-right text-xs text-texte-doux">{{ format_quantite($produit->quantite, $produit->unite) }}</span>
                </div>
            </li>
        @endforeach
    </ol>
@endif
