{{-- Onglet Entreprise : identité des documents (factures, reçus, rapports) et logo (PNG ou JPEG, 1 Mo au plus) --}}
<form method="POST" action="{{ route('parametres.update', 'entreprise') }}" enctype="multipart/form-data" x-chargement-envoi novalidate
      x-data="{
          apercu: @js($logo),
          retirer: false,
          choisir(evenement) {
              const fichier = evenement.target.files[0];
              if (fichier) {
                  this.apercu = URL.createObjectURL(fichier);
                  this.retirer = false;
              }
          },
      }"
      class="space-y-5">
    @csrf
    @method('PUT')

    <x-champ nom="nom_entreprise" label="Nom de l'entreprise" :valeur="$valeurs['nom_entreprise']" maxlength="150" requis />
    <x-champ nom="adresse" label="Adresse" :valeur="$valeurs['adresse']" icone="map-pin" maxlength="255" />
    <div class="grid gap-4 sm:grid-cols-2">
        <x-champ nom="telephone" label="Téléphone" type="tel" :valeur="$valeurs['telephone']" icone="phone" inputmode="tel" />
        <x-champ nom="email" label="Email" type="email" :valeur="$valeurs['email']" icone="mail" />
    </div>
    <x-champ nom="nif_stat" label="NIF / STAT" :valeur="$valeurs['nif_stat']" maxlength="100" placeholder="Ex. NIF 4001234567 · STAT 46101 11 2020 0 01234"
             aide="Imprimé sur les factures et les reçus." />

    <fieldset class="space-y-3">
        <legend class="text-sm font-medium text-texte">Logo</legend>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
            <div class="grid h-24 w-40 shrink-0 place-items-center overflow-hidden rounded-controle border border-dashed border-bordure-forte bg-fond">
                <template x-if="apercu && ! retirer">
                    <img x-bind:src="apercu" alt="Logo de l'entreprise" class="max-h-20 max-w-36 object-contain">
                </template>
                <span x-show="! apercu || retirer" class="flex flex-col items-center gap-1 text-xs text-texte-doux">
                    <x-icone nom="image" />
                    Aucun logo
                </span>
            </div>
            <div class="space-y-2">
                <label for="champ-logo" class="sr-only">Choisir un logo</label>
                <input id="champ-logo" type="file" name="logo" accept="image/png,image/jpeg" x-on:change="choisir($event)"
                       aria-describedby="aide-logo"
                       class="block w-full text-sm text-texte-doux file:mr-3 file:h-11 file:cursor-pointer file:rounded-controle file:border file:border-bordure-forte file:bg-surface file:px-4 file:text-sm file:font-medium file:text-texte hover:file:bg-fond">
                <p id="aide-logo" class="text-sm text-texte-doux">PNG ou JPEG, 1 Mo et 2000 × 2000 pixels au plus. Sans logo, les documents affichent l'initiale de l'entreprise.</p>
                @error('logo')<p class="text-sm font-medium text-danger-texte">{{ $message }}</p>@enderror
                @if ($logo)
                    <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 text-sm text-texte">
                        <input type="hidden" name="retirer_logo" value="0">
                        <input type="checkbox" name="retirer_logo" value="1" x-model="retirer" class="size-4 rounded border-bordure-forte accent-primaire">
                        Retirer le logo actuel
                    </label>
                @endif
            </div>
        </div>
    </fieldset>

    <div class="flex justify-end border-t border-bordure pt-5">
        <x-bouton icone="save">Enregistrer</x-bouton>
    </div>
</form>
