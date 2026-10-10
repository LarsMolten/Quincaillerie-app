{{-- Administration > Rôles et droits : un rôle par carte (comptes, droits, remise), création d'un rôle personnalisé --}}
@extends('layouts.app')

@section('titre', 'Rôles et droits')

@section('page')
    <x-entete-page titre="Rôles et droits" description="Ce que chaque rôle peut voir et faire dans l'application."
                   :fil="['Tableau de bord' => route('accueil'), 'Rôles et droits' => null]">
        <x-slot:actions>
            @droit('utilisateurs.gerer')
                <x-bouton :href="route('utilisateurs.index')" variante="secondaire" icone="users">Utilisateurs</x-bouton>
            @enddroit
            <x-bouton type="button" icone="plus" x-data x-on:click="$dispatch('nouveau-role')">Nouveau rôle</x-bouton>
        </x-slot:actions>
    </x-entete-page>

    <ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" role="list">
        @foreach ($roles as $role)
            @php
                $administrateur = $role->estAdministrateur();
                $nombreDroits = $administrateur ? $totalDroits : $role->droits_count;
                $part = $totalDroits > 0 ? round($nombreDroits / $totalDroits * 100) : 0;
                $supprimable = ! $role->estParDefaut() && $role->comptes === 0;
            @endphp
            <li>
                <x-carte class="flex h-full flex-col">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <span @class([
                                'grid size-11 shrink-0 place-items-center rounded-controle',
                                'bg-primaire-doux text-lien' => $administrateur,
                                'bg-neutre-doux text-neutre-texte' => ! $administrateur,
                            ])>
                                <x-icone :nom="$administrateur ? 'shield-check' : 'shield'" />
                            </span>
                            <div class="min-w-0">
                                <h2 class="truncate text-base font-semibold text-texte">
                                    <a href="{{ route('roles.show', $role) }}" class="hover:underline">{{ $role->nom }}</a>
                                </h2>
                                <x-badge :couleur="$role->estParDefaut() ? 'info' : 'neutre'" :point="false">
                                    {{ $role->estParDefaut() ? 'Par défaut' : 'Personnalisé' }}
                                </x-badge>
                            </div>
                        </div>
                        <x-menu-actions :libelle="'Actions pour le rôle '.$role->nom">
                            <x-menu-actions.element icone="shield" :href="route('roles.show', $role)">
                                {{ $administrateur ? 'Voir les droits' : 'Gérer les droits' }}
                            </x-menu-actions.element>
                            <x-menu-actions.element icone="pencil"
                                x-on:click="$dispatch('editer-role', {{ \Illuminate\Support\Js::from([
                                    'cible' => $role->id, 'nom' => $role->nom, 'description' => (string) $role->description, 'verrouille' => $role->estParDefaut(),
                                ]) }})">
                                Modifier
                            </x-menu-actions.element>
                            @droit('utilisateurs.gerer')
                                <x-menu-actions.element icone="users" :href="route('utilisateurs.index', ['role' => $role->id, 'statut' => 'tous'])">Voir les comptes</x-menu-actions.element>
                            @enddroit
                            @if ($supprimable)
                                <x-menu-actions.element icone="trash-2" danger x-on:click="$dispatch('ouvrir-modal', 'supprimer-role-{{ $role->id }}')">Supprimer</x-menu-actions.element>
                            @endif
                        </x-menu-actions>
                    </div>

                    <p class="mt-3 min-h-10 text-sm text-texte-doux">{{ $role->description ?: 'Aucune description.' }}</p>

                    <dl class="mt-4 grid grid-cols-3 gap-3 border-t border-bordure pt-4 text-sm">
                        <div>
                            <dt class="text-texte-doux">Comptes</dt>
                            <dd class="chiffres mt-0.5 font-semibold text-texte">{{ $role->comptes_actifs }}</dd>
                        </div>
                        <div>
                            <dt class="text-texte-doux">Droits</dt>
                            <dd class="chiffres mt-0.5 font-semibold text-texte">{{ $nombreDroits }}<span class="font-normal text-texte-doux">/{{ $totalDroits }}</span></dd>
                        </div>
                        <div>
                            <dt class="text-texte-doux">Remise max.</dt>
                            <dd class="chiffres mt-0.5 font-semibold text-texte">{{ str_replace('.', ',', (string) (float) $role->plafondRemise()) }} %</dd>
                        </div>
                    </dl>
                    {{-- Part des droits accordés --}}
                    <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-neutre-doux" role="progressbar" aria-label="Droits accordés"
                         aria-valuenow="{{ $part }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="h-full rounded-full bg-primaire" style="width: {{ $part }}%"></div>
                    </div>

                    <div class="mt-auto pt-5">
                        <x-bouton :href="route('roles.show', $role)" variante="secondaire" icone="shield" class="w-full">
                            {{ $administrateur ? 'Voir les droits' : 'Gérer les droits' }}
                        </x-bouton>
                    </div>
                </x-carte>

                @if ($supprimable)
                    <x-confirmation :id="'supprimer-role-'.$role->id" :action="route('roles.destroy', $role)"
                                    :titre="'Supprimer le rôle « '.$role->nom.' » ?'"
                                    message="Aucun compte n'a ce rôle. Il sera supprimé avec ses droits (opération enregistrée dans le journal)." />
                @endif
            </li>
        @endforeach
    </ul>

    <p class="mt-6 flex items-start gap-2 text-sm text-texte-doux">
        <x-icone nom="info" taille="size-4" class="mt-0.5 shrink-0" />
        La remise maximale de chaque rôle se règle dans Paramètres, onglet Ventes.
    </p>

    @include('roles._formulaire')
@endsection
