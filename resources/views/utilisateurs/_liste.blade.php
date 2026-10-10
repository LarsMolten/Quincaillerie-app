{{-- Tableau des comptes (rechargé seul lors d'une recherche : en-tête X-Fragment) --}}
@if ($utilisateurs->isEmpty())
    <x-carte>
        <x-etat-vide icone="search" titre="Aucun compte trouvé"
                     :texte="$recherche !== '' ? 'Aucun résultat pour « '.$recherche.' ».' : 'Aucun compte ne correspond à ces filtres.'">
            <x-bouton :href="route('utilisateurs.index')" variante="secondaire" icone="x" data-liste-lien>Effacer les filtres</x-bouton>
        </x-etat-vide>
    </x-carte>
@else
    <x-tableau
        legende="Liste des comptes utilisateurs"
        :pagination="$utilisateurs"
        :colonnes="[
            'nom' => 'Utilisateur',
            'role' => 'Rôle',
            'telephone' => 'Téléphone',
            'connexion' => 'Dernière connexion',
            'statut' => 'Statut',
            'actions' => ['libelle' => 'Actions', 'masque' => true, 'alignement' => 'droite'],
        ]"
    >
        @foreach ($utilisateurs as $utilisateur)
            <x-tableau.ligne @class(['opacity-75' => ! $utilisateur->actif])>
                <x-tableau.cellule libelle="Utilisateur" principale>
                    <div class="flex items-center gap-3">
                        <x-avatar :nom="$utilisateur->nom" />
                        <span class="min-w-0">
                            <span class="block truncate font-medium text-texte">
                                {{ $utilisateur->nom }}
                                @if ($utilisateur->is(auth()->user()))<span class="text-xs font-normal text-texte-doux">(vous)</span>@endif
                            </span>
                            <span class="block truncate text-xs font-normal text-texte-doux">{{ $utilisateur->email }}</span>
                        </span>
                    </div>
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Rôle">
                    <x-badge :couleur="$utilisateur->estAdministrateur() ? 'info' : 'neutre'" :point="false">{{ $utilisateur->role->nom }}</x-badge>
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Téléphone" class="chiffres whitespace-nowrap">{{ $utilisateur->telephone ?? '—' }}</x-tableau.cellule>
                <x-tableau.cellule libelle="Dernière connexion" class="whitespace-nowrap text-texte-doux">
                    @if ($utilisateur->derniere_connexion)
                        <time datetime="{{ \Illuminate\Support\Carbon::parse($utilisateur->derniere_connexion)->toIso8601String() }}"
                              title="{{ \Illuminate\Support\Carbon::parse($utilisateur->derniere_connexion)->format('d/m/Y H:i') }}">
                            {{ \Illuminate\Support\Carbon::parse($utilisateur->derniere_connexion)->diffForHumans() }}
                        </time>
                    @else
                        Jamais
                    @endif
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Statut">
                    <x-badge :couleur="$utilisateur->actif ? 'succes' : 'neutre'">{{ $utilisateur->actif ? 'Actif' : 'Désactivé' }}</x-badge>
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Actions" alignement="droite">@include('utilisateurs._actions')</x-tableau.cellule>
            </x-tableau.ligne>
        @endforeach
    </x-tableau>
@endif
