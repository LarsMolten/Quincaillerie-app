{{--
    Matrice des droits d'un rôle : un module par carte, un interrupteur par droit (case à cocher native en role="switch",
    utilisable sans JavaScript), « Tout activer / Tout retirer » par module, barre d'enregistrement collante.
    Administrateur : tous les droits, en lecture seule.
--}}
@extends('layouts.app')

@section('titre', 'Droits : '.$role->nom)

@section('page')
    @php
        $administrateur = $role->estAdministrateur();
    @endphp

    <x-entete-page :titre="$role->nom" :description="$role->description ?: 'Droits accordés aux comptes de ce rôle.'"
                   :fil="['Tableau de bord' => route('accueil'), 'Rôles et droits' => route('roles.index'), $role->nom => null]">
        <x-slot:actions>
            <x-bouton :href="route('roles.index')" variante="secondaire" icone="chevron-left">Tous les rôles</x-bouton>
        </x-slot:actions>
    </x-entete-page>

    @if ($administrateur)
        <p class="mb-6 flex items-start gap-2 rounded-carte bg-info-doux px-4 py-3 text-sm text-info-texte">
            <x-icone nom="shield-check" taille="size-5" class="shrink-0" />
            L'Administrateur a toujours tous les droits, y compris ceux ajoutés plus tard : ils ne sont pas modifiables.
        </p>
    @endif

    <form method="POST" action="{{ route('roles.droits', $role) }}" x-chargement-envoi
          x-data="{
              compte: {{ count($actifs) }},
              modifie: false,
              complets: {},
              maj() {
                  const cases = [...this.$root.querySelectorAll('input[name=\'droits[]\']')];
                  this.compte = cases.filter((c) => c.checked).length;
                  const complets = {};
                  for (const c of cases) {
                      complets[c.dataset.module] = (complets[c.dataset.module] ?? true) && c.checked;
                  }
                  this.complets = complets;
              },
              basculerModule(module) {
                  const etat = ! this.complets[module];
                  this.$root.querySelectorAll(`input[data-module='${module}']`).forEach((c) => (c.checked = etat));
                  this.modifie = true;
                  this.maj();
              },
          }"
          x-init="maj()" x-on:change="modifie = true; maj()">
        @csrf
        @method('PUT')

        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($modules as $code => $module)
                <x-carte>
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <h2 class="flex items-center gap-2.5 text-base font-semibold text-texte">
                            <span class="grid size-9 place-items-center rounded-controle bg-neutre-doux text-neutre-texte"><x-icone :nom="$module['icone']" taille="size-[1.125rem]" /></span>
                            {{ $module['libelle'] }}
                        </h2>
                        @unless ($administrateur)
                            <x-bouton type="button" variante="fantome" taille="sm" x-on:click="basculerModule('{{ $code }}')"
                                      x-text="complets['{{ $code }}'] ? 'Tout retirer' : 'Tout activer'">Tout activer</x-bouton>
                        @endunless
                    </div>
                    <ul class="divide-y divide-bordure" role="list">
                        @foreach ($module['droits'] as $droit)
                            <li>
                                <label class="flex min-h-14 cursor-pointer items-center justify-between gap-4 py-2 has-disabled:cursor-default">
                                    <span class="min-w-0">
                                        <span class="block text-sm font-medium text-texte">{{ $droit->libelle }}</span>
                                        <code class="block text-xs text-texte-doux">{{ $droit->code }}</code>
                                    </span>
                                    <input type="checkbox" role="switch" name="droits[]" value="{{ $droit->code }}" data-module="{{ $code }}"
                                           @checked(in_array($droit->code, $actifs, true)) @disabled($administrateur)
                                           class="relative h-7 w-12 shrink-0 cursor-pointer appearance-none rounded-full border border-bordure-forte bg-neutre-doux transition-colors duration-150 before:absolute before:top-1/2 before:left-0.5 before:size-5 before:-translate-y-1/2 before:rounded-full before:bg-surface before:shadow-doux before:ring-1 before:ring-bordure-forte before:transition-transform before:duration-150 checked:border-primaire checked:bg-primaire checked:before:translate-x-5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anneau disabled:cursor-default disabled:opacity-70">
                                </label>
                            </li>
                        @endforeach
                    </ul>
                </x-carte>
            @endforeach
        </div>

        @unless ($administrateur)
            {{-- Barre d'enregistrement : collée en bas de l'écran (au-dessus de la barre mobile) --}}
            <div class="verre sticky bottom-20 z-10 mt-6 flex flex-col gap-3 rounded-carte border border-bordure px-4 py-3 shadow-eleve sm:flex-row sm:items-center sm:justify-between lg:bottom-4">
                <p class="text-sm text-texte-doux" aria-live="polite">
                    <span class="chiffres font-semibold text-texte" x-text="compte">{{ count($actifs) }}</span> droit(s) sur {{ $totalDroits }}
                    <span x-show="modifie" x-cloak class="text-alerte-texte"> · modifications non enregistrées</span>
                </p>
                <div class="flex gap-2">
                    <x-bouton :href="route('roles.show', $role)" variante="secondaire" x-show="modifie" x-cloak>Annuler</x-bouton>
                    <x-bouton icone="save">Enregistrer les droits</x-bouton>
                </div>
            </div>
        @endunless
    </form>
@endsection
