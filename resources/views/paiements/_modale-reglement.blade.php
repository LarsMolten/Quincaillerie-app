{{--
    Modale de paiement des pages Créances et Dettes fournisseurs (composant Alpine « reglements »).
    Variables : $modes (ModePaiement::encaissements()), $verbe (« Encaisser » ou « Régler »).
--}}
<x-modal id="modale-reglement" titre="{{ $verbe }}" taille="sm"
         titre-dynamique="'{{ $verbe }} ' + reglement.document">
    <form class="space-y-4" novalidate x-on:submit.prevent="enregistrer()" id="formulaire-reglement">
        <p class="text-sm text-texte-doux">
            <span x-text="reglement.tiers"></span> · Reste à payer :
            <span class="chiffres font-semibold text-texte" x-text="new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(reglement.reste) + ' Ar'"></span>
        </p>

        <div>
            <label for="reglement-montant" class="mb-1.5 block text-sm font-medium">Montant <span class="text-danger-texte" aria-hidden="true">*</span></label>
            <div class="relative">
                <input id="reglement-montant" type="number" min="1" step="1" inputmode="numeric" required x-model="reglement.montant"
                       x-bind:max="reglement.reste" x-bind:aria-invalid="(!! reglement.erreurs.montant).toString()" aria-describedby="reglement-montant-erreur"
                       class="chiffres h-11 w-full rounded-controle border border-bordure-forte bg-surface pr-10 pl-3 text-right text-sm text-texte aria-invalid:border-danger">
                <span class="pointer-events-none absolute inset-y-0 right-3 grid place-items-center text-sm text-texte-doux">Ar</span>
            </div>
            <p id="reglement-montant-erreur" x-show="reglement.erreurs.montant" x-cloak class="mt-1.5 text-sm text-danger-texte" x-text="reglement.erreurs.montant?.[0]"></p>
            <button type="button" class="mt-1.5 text-sm font-medium text-lien hover:underline" x-on:click="reglement.montant = String(Math.round(reglement.reste))">Tout régler</button>
        </div>

        <div>
            <label for="reglement-mode" class="mb-1.5 block text-sm font-medium">Mode de paiement <span class="text-danger-texte" aria-hidden="true">*</span></label>
            <select id="reglement-mode" x-model="reglement.mode" required aria-describedby="reglement-mode-erreur"
                    class="h-11 w-full rounded-controle border border-bordure-forte bg-surface px-3 text-sm text-texte">
                @foreach ($modes as $mode)
                    <option value="{{ $mode->value }}">{{ $mode->libelle() }}</option>
                @endforeach
            </select>
            <p id="reglement-mode-erreur" x-show="reglement.erreurs.mode" x-cloak class="mt-1.5 text-sm text-danger-texte" x-text="reglement.erreurs.mode?.[0]"></p>
        </div>

        <div>
            <label for="reglement-reference" class="mb-1.5 block text-sm font-medium">Référence <span class="font-normal text-texte-doux">(facultatif)</span></label>
            <input id="reglement-reference" type="text" maxlength="100" x-model="reglement.reference" placeholder="N° de chèque, de transaction…"
                   aria-describedby="reglement-reference-erreur"
                   class="h-11 w-full rounded-controle border border-bordure-forte bg-surface px-3 text-sm text-texte">
            <p id="reglement-reference-erreur" x-show="reglement.erreurs.reference" x-cloak class="mt-1.5 text-sm text-danger-texte" x-text="reglement.erreurs.reference?.[0]"></p>
        </div>

        <div>
            <label for="reglement-date" class="mb-1.5 block text-sm font-medium">Date <span class="text-danger-texte" aria-hidden="true">*</span></label>
            <input id="reglement-date" type="date" required x-model="reglement.date" max="{{ today()->toDateString() }}"
                   x-bind:aria-invalid="(!! reglement.erreurs.date_paiement).toString()" aria-describedby="reglement-date-erreur"
                   class="h-11 w-full rounded-controle border border-bordure-forte bg-surface px-3 text-sm text-texte aria-invalid:border-danger">
            <p id="reglement-date-erreur" x-show="reglement.erreurs.date_paiement" x-cloak class="mt-1.5 text-sm text-danger-texte" x-text="reglement.erreurs.date_paiement?.[0]"></p>
        </div>
    </form>
    <x-slot:pied>
        <x-bouton type="button" variante="secondaire" x-on:click="$dispatch('fermer-modal', 'modale-reglement')">Annuler</x-bouton>
        <x-bouton type="submit" form="formulaire-reglement" icone="save" x-bind:disabled="reglement.enCours"
                  x-bind:data-chargement="reglement.enCours.toString()" x-bind:aria-busy="reglement.enCours.toString()">Enregistrer le paiement</x-bouton>
    </x-slot:pied>
</x-modal>
