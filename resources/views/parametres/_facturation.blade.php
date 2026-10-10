{{-- Onglet Facturation : préfixes de numérotation (aperçu du prochain format), TVA, format d'impression par défaut, pied de facture --}}
<form method="POST" action="{{ route('parametres.update', 'facturation') }}" x-chargement-envoi novalidate class="space-y-6"
      x-data="{ prefixes: {{ \Illuminate\Support\Js::from($prefixes->mapWithKeys(fn ($p) => [$p['cle'] => old($p['cle'], $p['valeur'])])) }} }">
    @csrf
    @method('PUT')

    <fieldset class="space-y-4">
        <legend class="text-sm font-semibold text-texte">Numérotation des documents</legend>
        <p class="flex items-start gap-2 rounded-controle bg-alerte-doux px-3 py-2 text-sm text-alerte-texte">
            <x-icone nom="triangle-alert" taille="size-4" class="mt-0.5 shrink-0" />
            Changer un préfixe fait repartir la numérotation à 00001 pour le nouveau préfixe. Les documents déjà émis gardent leur numéro.
        </p>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($prefixes as $prefixe)
                <div>
                    <x-champ :nom="$prefixe['cle']" :label="$prefixe['libelle']" :valeur="$prefixe['valeur']" maxlength="5" autocomplete="off"
                             class="uppercase" x-model="prefixes['{{ $prefixe['cle'] }}']" requis />
                    <p class="mt-1 text-xs text-texte-doux">
                        Ex. <span class="chiffres font-medium text-texte" x-text="(prefixes['{{ $prefixe['cle'] }}'] || '{{ $prefixe['defaut'] }}').toUpperCase() + '-{{ now()->year }}-00001'">{{ $prefixe['valeur'] }}-{{ now()->year }}-00001</span>
                    </p>
                </div>
            @endforeach
        </div>
    </fieldset>

    <div class="grid gap-4 border-t border-bordure pt-6 sm:grid-cols-2">
        <x-champ nom="taux_tva" label="Taux de TVA" type="number" min="0" max="100" step="0.01" inputmode="decimal" suffixe="%" montant
                 :valeur="$valeurs['taux_tva']" aide="0 si l'entreprise n'est pas assujettie : la TVA n'apparaît pas sur les factures." requis />

        <fieldset>
            <legend class="mb-1.5 text-sm font-medium text-texte">Format de facture par défaut</legend>
            @php($format = old('format_facture', $valeurs['format_facture']))
            <div class="grid grid-cols-2 gap-2">
                @foreach (['a4' => ['A4', 'file-text', 'Page complète'], 'ticket' => ['Ticket', 'receipt', 'Imprimante 80 mm']] as $code => [$libelle, $icone, $detail])
                    <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-controle border border-bordure-forte px-3 py-2.5 text-sm transition-colors duration-150 has-checked:border-primaire has-checked:bg-primaire-doux">
                        <input type="radio" name="format_facture" value="{{ $code }}" @checked($format === $code) class="sr-only">
                        <x-icone :nom="$icone" taille="size-5" class="text-texte-doux" />
                        <span><span class="block font-medium text-texte">{{ $libelle }}</span><span class="block text-xs text-texte-doux">{{ $detail }}</span></span>
                    </label>
                @endforeach
            </div>
        </fieldset>
    </div>

    <x-textarea nom="pied_de_facture" label="Pied de facture" :valeur="$valeurs['pied_de_facture']" :lignes="3" maxlength="500"
                aide="Mention imprimée en bas des factures, tickets et reçus (conditions de vente, remerciements…)." />

    <div class="flex justify-end border-t border-bordure pt-5">
        <x-bouton icone="save">Enregistrer</x-bouton>
    </div>
</form>
