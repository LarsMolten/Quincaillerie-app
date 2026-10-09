{{--
    Modale d'impression des étiquettes : quantité par produit, puis PDF dans un nouvel onglet.
    Ouverture : $dispatch('etiquettes-produits', { produits: ['12', '15'] })
--}}
<div x-data="{ produits: [], quantite: 1 }"
     x-on:etiquettes-produits.window="produits = $event.detail.produits; $dispatch('ouvrir-modal', 'modale-etiquettes')">
    <x-modal id="modale-etiquettes" titre="Imprimer des étiquettes" taille="sm"
             description="Planche A4 de 24 étiquettes avec nom, prix et code-barres.">
        <form method="GET" action="{{ route('produits.etiquettes') }}" target="_blank" class="space-y-5"
              x-on:submit="$nextTick(() => $dispatch('fermer-modal', 'modale-etiquettes'))">
            <template x-for="produit in produits" x-bind:key="produit">
                <input type="hidden" name="produits[]" x-bind:value="produit">
            </template>
            <p class="text-sm text-texte-doux">
                <span class="chiffres font-semibold text-texte" x-text="produits.length"></span>
                <span x-text="produits.length > 1 ? 'produits sélectionnés' : 'produit sélectionné'"></span>
            </p>
            <x-champ nom="quantite" id="etiquettes-quantite" label="Étiquettes par produit" type="number" min="1" max="100" step="1"
                     inputmode="numeric" montant x-model="quantite" aide="De 1 à 100." requis />
            <div class="-mx-6 -mb-6 flex flex-col-reverse gap-3 border-t border-bordure px-6 py-4 sm:flex-row sm:justify-end">
                <x-bouton type="button" variante="secondaire" x-on:click="$dispatch('fermer-modal', 'modale-etiquettes')">Annuler</x-bouton>
                <x-bouton icone="printer">Générer le PDF</x-bouton>
            </div>
        </form>
    </x-modal>
</div>
