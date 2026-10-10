{{--
    Chronologie du journal (rechargée seule lors d'un filtrage : en-tête X-Fragment), groupée par jour.
    Chaque entrée : auteur, action en phrase, heure, adresse IP ; détail repliable (anciennes / nouvelles valeurs, motif…).
--}}
@php
    use App\Support\LibelleJournal;

    // Classes complètes (Tailwind ne détecte pas les noms construits dynamiquement)
    $pastilles = [
        'succes' => 'bg-succes-doux text-succes-texte',
        'info' => 'bg-info-doux text-info-texte',
        'danger' => 'bg-danger-doux text-danger-texte',
        'alerte' => 'bg-alerte-doux text-alerte-texte',
        'neutre' => 'bg-neutre-doux text-neutre-texte',
    ];
    $jours = $entrees->getCollection()->groupBy(fn ($e) => $e->created_at->toDateString());
@endphp

<p class="mb-4 text-sm text-texte-doux">
    Période : {{ $periode->libelle() }} ·
    <span class="chiffres font-medium text-texte">{{ $entrees->total() }}</span> action{{ $entrees->total() > 1 ? 's' : '' }}
</p>

@if ($entrees->isEmpty())
    <x-carte>
        <x-etat-vide icone="scroll-text" titre="Aucune activité" texte="Aucune action ne correspond à ces filtres sur cette période.">
            <x-bouton :href="route('journal.index')" variante="secondaire" icone="x" data-liste-lien>Effacer les filtres</x-bouton>
        </x-etat-vide>
    </x-carte>
@else
    <div class="space-y-6">
        @foreach ($jours as $jour => $liste)
            @php
                $date = \Illuminate\Support\Carbon::parse($jour);
                $titreJour = $date->isToday() ? 'Aujourd\'hui' : ($date->isYesterday() ? 'Hier' : ucfirst($date->translatedFormat('l j F Y')));
            @endphp
            <section aria-labelledby="jour-{{ $jour }}">
                <h2 id="jour-{{ $jour }}" class="sticky top-16 z-[1] mb-2 inline-block rounded-full bg-fond py-1 pr-3 text-xs font-semibold uppercase tracking-wider text-texte-doux">
                    {{ $titreJour }}
                </h2>
                <x-carte :padding="false">
                    <ol class="divide-y divide-bordure" role="list">
                        @foreach ($liste as $entree)
                            @php
                                $p = LibelleJournal::presenter($entree);
                                $details = $entree->details ?? [];
                                $anciennes = $details['anciennes'] ?? [];
                                $nouvelles = $details['nouvelles'] ?? [];
                                $autres = collect($details)->except(['objet', 'nom', 'numero', 'anciennes', 'nouvelles']);
                                $champs = array_values(array_unique([...array_keys($anciennes), ...array_keys($nouvelles)]));
                                $auteur = $entree->utilisateur?->nom ?? 'Système';
                            @endphp
                            <li class="flex gap-3 px-4 py-3.5 sm:px-5">
                                <span class="mt-0.5 grid size-9 shrink-0 place-items-center rounded-full {{ $pastilles[$p['couleur']] ?? $pastilles['neutre'] }}">
                                    <x-icone :nom="$p['icone']" taille="size-4" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-col gap-0.5 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
                                        <p class="text-sm text-texte">
                                            <span class="font-semibold">{{ $auteur }}</span>
                                            {{ $p['verbe'] }}
                                            @if ($p['objet'])<span class="font-medium">{{ $p['objet'] }}</span>@endif
                                        </p>
                                        <p class="chiffres shrink-0 text-xs text-texte-doux">
                                            <time datetime="{{ $entree->created_at->toIso8601String() }}">{{ $entree->created_at->format('H:i') }}</time>
                                            @if ($entree->adresse_ip) · {{ $entree->adresse_ip }} @endif
                                        </p>
                                    </div>
                                    @if ($entree->utilisateur?->role)
                                        <p class="text-xs text-texte-doux">{{ $entree->utilisateur->role->nom }}</p>
                                    @endif

                                    @if ($champs || $autres->isNotEmpty())
                                        <details class="group mt-2">
                                            <summary class="inline-flex min-h-8 cursor-pointer list-none items-center gap-1 rounded-controle text-xs font-medium text-lien hover:underline [&::-webkit-details-marker]:hidden">
                                                <x-icone nom="chevron-right" taille="size-3.5" class="transition-transform duration-150 group-open:rotate-90" />
                                                Détail
                                            </summary>
                                            <div class="mt-2 overflow-x-auto rounded-controle border border-bordure">
                                                <table class="w-full text-left text-xs">
                                                    @if ($champs)
                                                        <thead class="bg-fond text-texte-doux">
                                                            <tr>
                                                                <th scope="col" class="px-3 py-2 font-medium">Champ</th>
                                                                @if ($anciennes)<th scope="col" class="px-3 py-2 font-medium">Avant</th>@endif
                                                                @if ($nouvelles)<th scope="col" class="px-3 py-2 font-medium">{{ $anciennes ? 'Après' : 'Valeur' }}</th>@endif
                                                            </tr>
                                                        </thead>
                                                    @endif
                                                    <tbody class="divide-y divide-bordure">
                                                        @foreach ($champs as $champ)
                                                            <tr>
                                                                <th scope="row" class="px-3 py-2 font-medium text-texte-doux">{{ LibelleJournal::champ($champ) }}</th>
                                                                @if ($anciennes)<td class="px-3 py-2 break-all text-texte-doux line-through decoration-danger/60">{{ LibelleJournal::valeur($anciennes[$champ] ?? null, $champ) }}</td>@endif
                                                                @if ($nouvelles)<td class="px-3 py-2 break-all text-texte">{{ LibelleJournal::valeur($nouvelles[$champ] ?? null, $champ) }}</td>@endif
                                                            </tr>
                                                        @endforeach
                                                        @foreach ($autres as $cle => $valeur)
                                                            <tr>
                                                                <th scope="row" class="px-3 py-2 font-medium text-texte-doux">{{ LibelleJournal::champ((string) $cle) }}</th>
                                                                <td class="px-3 py-2 break-all text-texte" colspan="2">{{ LibelleJournal::valeur($valeur, (string) $cle) }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </details>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </x-carte>
            </section>
        @endforeach
    </div>

    <div class="mt-6">{{ $entrees->links() }}</div>
@endif
