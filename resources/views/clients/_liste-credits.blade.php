{{-- Tableau des crédits (rechargé seul lors d'une recherche : en-tête X-Fragment) --}}
@php
    $tranches = [
        'recente' => ['< 30 jours', 'info'],
        'moyenne' => ['30–60 jours', 'alerte'],
        'ancienne' => ['> 60 jours', 'danger'],
    ];
@endphp

@if ($credits->isEmpty())
    <x-carte>
        @if ($recherche !== '' || $anciennete)
            <x-etat-vide icone="search" titre="Aucun crédit trouvé" texte="Aucun client débiteur ne correspond à cette recherche.">
                <x-bouton :href="route('clients.credits')" variante="secondaire" icone="x" data-liste-lien>Effacer les filtres</x-bouton>
            </x-etat-vide>
        @else
            <x-etat-vide icone="circle-check" titre="Aucun crédit en cours" texte="Tous les clients ont réglé leurs achats." />
        @endif
    </x-carte>
@else
    <x-tableau
        legende="Clients ayant une créance, du montant le plus élevé au plus faible"
        :pagination="$credits"
        :colonnes="[
            'client' => 'Client',
            'ventes' => ['libelle' => 'Ventes impayées', 'alignement' => 'droite'],
            'depuis' => 'Plus ancienne',
            'anciennete' => 'Ancienneté',
            'plafond' => ['libelle' => 'Plafond', 'alignement' => 'droite'],
            'creance' => ['libelle' => 'Créance', 'alignement' => 'droite'],
        ]"
    >
        @foreach ($credits as $client)
            @php([$libelle, $couleur] = $tranches[\App\Http\Controllers\ClientController::tranche($client->jours)])
            <x-tableau.ligne>
                <x-tableau.cellule libelle="Client" principale>
                    <div class="flex items-center gap-3">
                        <x-avatar :nom="$client->nom" />
                        <span class="min-w-0">
                            <a href="{{ route('clients.show', $client) }}" class="block truncate font-medium text-texte hover:underline">{{ $client->nom }}</a>
                            @if ($client->telephone)<span class="chiffres block text-xs font-normal text-texte-doux">{{ $client->telephone }}</span>@endif
                        </span>
                    </div>
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Ventes impayées" alignement="droite">{{ $client->ventes_impayees }}</x-tableau.cellule>
                <x-tableau.cellule libelle="Plus ancienne" class="whitespace-nowrap text-texte-doux">
                    {{ \Illuminate\Support\Carbon::parse($client->plus_ancienne)->translatedFormat('j M Y') }}
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Ancienneté">
                    <x-badge :couleur="$couleur" title="Plus ancienne vente impayée : il y a {{ $client->jours }} jour(s)">{{ $libelle }}</x-badge>
                    <span class="sr-only">({{ $client->jours }} jours)</span>
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Plafond" alignement="droite" class="text-texte-doux">
                    {{ $client->plafond_credit !== null ? format_ar($client->plafond_credit) : '—' }}
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Créance" alignement="droite">
                    <span class="font-semibold text-danger-texte">{{ format_ar($client->creance) }}</span>
                </x-tableau.cellule>
            </x-tableau.ligne>
        @endforeach
    </x-tableau>
@endif
