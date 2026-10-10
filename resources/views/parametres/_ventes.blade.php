{{-- Onglet Ventes : remise maximale générale et par rôle (vide = générale), autorisation du stock négatif --}}
<form method="POST" action="{{ route('parametres.update', 'ventes') }}" x-chargement-envoi novalidate class="space-y-6">
    @csrf
    @method('PUT')

    <x-champ nom="remise_max_pourcentage" label="Remise maximale générale" type="number" min="0" max="100" step="0.5" inputmode="decimal"
             suffixe="%" montant :valeur="$valeurs['remise_max_pourcentage']" class="sm:max-w-48"
             aide="En % du montant de la vente. S'applique aux rôles sans remise propre." requis />

    <fieldset class="space-y-3">
        <legend class="text-sm font-semibold text-texte">Remise maximale par rôle</legend>
        <p class="text-sm text-texte-doux">Seuls les rôles ayant le droit « Accorder des remises » peuvent en faire. Laissez vide pour appliquer la remise générale.</p>
        <ul class="divide-y divide-bordure rounded-controle border border-bordure" role="list">
            @foreach ($roles as $role)
                @php($peutRemiser = $role->estAdministrateur() || $role->droits->contains('code', 'ventes.remise'))
                <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <label for="remise-role-{{ $role->id }}" class="min-w-0">
                        <span class="block text-sm font-medium text-texte">{{ $role->nom }}</span>
                        @unless ($peutRemiser)
                            <span class="block text-xs text-texte-doux">Sans droit de remise</span>
                        @endunless
                    </label>
                    <div class="relative sm:w-40">
                        <input id="remise-role-{{ $role->id }}" type="number" name="remises[{{ $role->id }}]" min="0" max="100" step="0.5" inputmode="decimal"
                               value="{{ old('remises.'.$role->id, $role->remise_max !== null ? (float) $role->remise_max : '') }}"
                               placeholder="Générale" @error('remises.'.$role->id) aria-invalid="true" @enderror
                               class="chiffres h-11 w-full rounded-controle border border-bordure-forte bg-surface pl-3 pr-9 text-right text-sm text-texte placeholder:text-texte-doux aria-invalid:border-danger">
                        <span class="pointer-events-none absolute inset-y-0 right-3 grid place-items-center text-sm text-texte-doux">%</span>
                    </div>
                    @error('remises.'.$role->id)<p class="text-sm font-medium text-danger-texte sm:hidden">{{ $message }}</p>@enderror
                </li>
            @endforeach
        </ul>
    </fieldset>

    <div class="border-t border-bordure pt-6">
        <x-interrupteur nom="stock_negatif_autorise" label="Autoriser la vente en stock négatif" :actif="$valeurs['stock_negatif_autorise']"
                        aide="Déconseillé : une vente peut alors dépasser le stock disponible (chaque cas est enregistré dans le journal)." />
    </div>

    <div class="flex justify-end border-t border-bordure pt-5">
        <x-bouton icone="save">Enregistrer</x-bouton>
    </div>
</form>
