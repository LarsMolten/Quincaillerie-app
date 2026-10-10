{{-- Carte « Stock faible » : produits au minimum ou en dessous, jauge de remplissage colorée --}}
@if ($produits->isEmpty())
    <x-etat-vide icone="circle-check" titre="Stock suffisant" texte="Aucun produit n'est sous son stock minimum." />
@else
    <ul class="space-y-3" aria-label="Produits en stock faible">
        @foreach ($produits as $produit)
            @php
                $couleur = $produit->remplissage <= 0 ? 'bg-danger' : ($produit->remplissage < 50 ? 'bg-alerte' : 'bg-primaire');
                $unite = $produit->unite?->abreviation;
            @endphp
            <li>
                <div class="mb-1 flex items-baseline justify-between gap-3 text-sm">
                    <a href="{{ route('produits.show', $produit) }}" class="min-w-0 truncate font-medium hover:underline">{{ $produit->nom }}</a>
                    <span class="chiffres shrink-0 text-xs text-texte-doux">{{ format_quantite($produit->stock_actuel, $unite) }} / {{ format_quantite($produit->stock_minimum, $unite) }}</span>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-neutre-doux" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $produit->remplissage }}"
                     aria-label="Stock de {{ $produit->nom }} : {{ $produit->remplissage }} % du minimum">
                    <div class="h-full rounded-full {{ $couleur }}" style="width: {{ max(2, $produit->remplissage) }}%"></div>
                </div>
            </li>
        @endforeach
    </ul>
@endif
