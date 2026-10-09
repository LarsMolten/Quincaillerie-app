{{--
    Panneau de création / modification d'un client.
    Ouverture : $dispatch('nouveau-client') ou $dispatch('editer-client', { cible, comptoir, nom, telephone, email, adresse, plafond_credit }).
    Client comptoir : nom et plafond verrouillés (ventes anonymes, jamais de crédit).
--}}
@php
    $formulaire = old('_formulaire');
    $cibleInitiale = $formulaire && $formulaire !== 'nouveau' ? (int) $formulaire : null;
    $champs = ['nom', 'telephone', 'email', 'adresse', 'plafond_credit'];
    $comptoirInitial = $cibleInitiale && \App\Models\Client::find($cibleInitiale)?->estComptoir();
@endphp

<div
    x-data="{
        cible: @js($cibleInitiale),
        comptoir: @js((bool) $comptoirInitial),
        @foreach ($champs as $champ) {{ $champ }}: @js((string) old($champ, '')), @endforeach
        get action() { return this.cible ? @js(url('clients')) + '/' + this.cible : @js(route('clients.store')) },
        remplir(donnees = {}) {
            this.cible = donnees.cible ?? null;
            this.comptoir = donnees.comptoir ?? false;
            @foreach ($champs as $champ) this.{{ $champ }} = donnees.{{ $champ }} ?? ''; @endforeach
            this.$dispatch('ouvrir-modal', 'panneau-client');
        },
        init() {
            @if ($errors->any() && $formulaire)
                this.$nextTick(() => this.$dispatch('ouvrir-modal', 'panneau-client'));
            @endif
        },
    }"
    x-on:nouveau-client.window="remplir()"
    x-on:editer-client.window="remplir($event.detail)"
>
    <x-panneau id="panneau-client" titre="Client" titre-dynamique="cible ? 'Modifier le client' : 'Nouveau client'">
        <form id="formulaire-client" method="POST" x-bind:action="action" action="{{ route('clients.store') }}"
              x-chargement-envoi novalidate class="space-y-5">
            @csrf
            <input type="hidden" name="_method" value="PUT" x-bind:disabled="! cible" @disabled(! $cibleInitiale)>
            <input type="hidden" name="_formulaire" x-bind:value="cible ?? 'nouveau'" value="{{ $cibleInitiale ?? 'nouveau' }}">

            <template x-if="comptoir">
                <p class="flex items-start gap-2 rounded-controle bg-info-doux px-3 py-2 text-sm text-info-texte">
                    <x-icone nom="info" taille="size-4" class="mt-0.5" />
                    Client par défaut des ventes anonymes : son nom ne change pas et il n'a jamais de crédit.
                </p>
            </template>

            <x-champ nom="nom" label="Nom" x-model="nom" maxlength="150" placeholder="Ex. Rakoto Hery" x-bind:readonly="comptoir" requis />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-champ nom="telephone" label="Téléphone" type="tel" x-model="telephone" icone="phone" inputmode="tel" placeholder="034 12 345 67" />
                <x-champ nom="email" label="Email" type="email" x-model="email" icone="mail" />
            </div>
            <x-textarea nom="adresse" label="Adresse" x-model="adresse" :lignes="2" maxlength="255" />
            <template x-if="! comptoir">
                <x-champ nom="plafond_credit" label="Plafond de crédit" type="number" min="0" step="1000" inputmode="numeric"
                         suffixe="Ar" montant x-model="plafond_credit"
                         aide="Montant maximum de créance autorisé. Laissez vide pour n'autoriser aucun crédit." />
            </template>
        </form>

        <x-slot:pied>
            <x-bouton type="button" variante="secondaire" x-on:click="$dispatch('fermer-modal', 'panneau-client')">Annuler</x-bouton>
            <x-bouton form="formulaire-client" icone="save">Enregistrer</x-bouton>
        </x-slot:pied>
    </x-panneau>
</div>
