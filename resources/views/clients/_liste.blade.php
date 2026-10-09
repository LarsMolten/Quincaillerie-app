{{-- Tableau des clients (rechargé seul lors d'une recherche : en-tête X-Fragment) --}}
@if ($clients->isEmpty())
    <x-carte>
        @if ($recherche !== '' || $statut !== 'actifs')
            <x-etat-vide icone="search" titre="Aucun client trouvé"
                         :texte="$recherche !== '' ? 'Aucun résultat pour « '.$recherche.' ».' : 'Aucun client ne correspond à ce filtre.'">
                <x-bouton :href="route('clients.index')" variante="secondaire" icone="x" data-liste-lien>Effacer la recherche</x-bouton>
            </x-etat-vide>
        @else
            <x-etat-vide icone="users" titre="Aucun client" texte="Enregistrez vos clients réguliers pour suivre leurs achats et leurs crédits.">
                <x-bouton type="button" icone="plus" x-data x-on:click="$dispatch('nouveau-client')">Ajouter un client</x-bouton>
            </x-etat-vide>
        @endif
    </x-carte>
@else
    <x-tableau
        legende="Liste des clients"
        :pagination="$clients"
        :colonnes="[
            'nom' => ['libelle' => 'Client', 'triable' => true],
            'telephone' => 'Téléphone',
            'plafond' => ['libelle' => 'Plafond de crédit', 'alignement' => 'droite'],
            'creance' => ['libelle' => 'Créance', 'triable' => true, 'alignement' => 'droite'],
            'statut' => 'Statut',
            'actions' => ['libelle' => 'Actions', 'masque' => true, 'alignement' => 'droite'],
        ]"
    >
        @foreach ($clients as $client)
            <x-tableau.ligne @class(['opacity-75' => ! $client->actif])>
                <x-tableau.cellule libelle="Client" principale>
                    <div class="flex items-center gap-3">
                        @if ($client->estComptoir())
                            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-neutre-doux text-neutre-texte" aria-hidden="true"><x-icone nom="store" /></span>
                        @else
                            <x-avatar :nom="$client->nom" />
                        @endif
                        <span class="min-w-0">
                            <a href="{{ route('clients.show', $client) }}" class="block truncate font-medium text-texte hover:underline">{{ $client->nom }}</a>
                            @if ($client->email)<span class="block truncate text-xs font-normal text-texte-doux">{{ $client->email }}</span>@endif
                        </span>
                        @if ($client->estComptoir())<x-badge couleur="info" :point="false">Par défaut</x-badge>@endif
                    </div>
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Téléphone" class="chiffres whitespace-nowrap">{{ $client->telephone ?? '—' }}</x-tableau.cellule>
                <x-tableau.cellule libelle="Plafond de crédit" alignement="droite" class="text-texte-doux">
                    {{ $client->plafond_credit !== null && ! $client->estComptoir() ? format_ar($client->plafond_credit) : 'Pas de crédit' }}
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Créance" alignement="droite">
                    <span @class(['font-semibold text-danger-texte' => $client->creance > 0, 'text-texte-doux' => ! $client->creance])>{{ format_ar($client->creance ?? 0) }}</span>
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Statut">
                    <x-badge :couleur="$client->actif ? 'succes' : 'neutre'">{{ $client->actif ? 'Actif' : 'Inactif' }}</x-badge>
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Actions" alignement="droite">@include('clients._actions')</x-tableau.cellule>
            </x-tableau.ligne>
        @endforeach
    </x-tableau>
@endif
