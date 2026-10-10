{{-- Retours d'une vente ou d'un achat (fiches) : variable $retours (collection de Retour) --}}
@if ($retours->isNotEmpty())
    <x-carte titre="Retours" class="lg:col-span-3" :padding="false">
        <ul class="divide-y divide-bordure" role="list">
            @foreach ($retours->sortByDesc('id') as $retour)
                <li class="flex flex-wrap items-center justify-between gap-3 px-carte py-3 text-sm">
                    <div class="min-w-0">
                        <a href="{{ route('retours.show', $retour) }}" class="font-medium hover:underline">{{ $retour->numero }}</a>
                        <p class="text-xs text-texte-doux">{{ $retour->date_retour->translatedFormat('j M Y') }} · {{ $retour->motif }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <x-badge :statut="$retour->statut" />
                        <p class="chiffres font-semibold">{{ format_ar($retour->total) }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    </x-carte>
@endif
