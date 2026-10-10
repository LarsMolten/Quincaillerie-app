{{--
    Onglet Apparence : thème par défaut (nouveaux comptes et page de connexion) et couleur d'accent
    (écran et documents PDF). La couleur choisie est prévisualisée aussitôt, enregistrée au clic sur « Enregistrer ».
--}}
@php
    $theme = old('theme_defaut', $valeurs['theme_defaut']);
    $accent = old('couleur_accent', $valeurs['couleur_accent']);
@endphp
<form method="POST" action="{{ route('parametres.update', 'apparence') }}" x-chargement-envoi novalidate class="space-y-8"
      x-data="{
          accent: @js($accent),
          initial: document.documentElement.dataset.accent,
          enregistre: false,
          // Page quittée sans enregistrer (navigation partielle) : l'accent d'origine revient
          destroy() { if (! this.enregistre) document.documentElement.dataset.accent = this.initial; },
      }"
      x-effect="document.documentElement.dataset.accent = accent"
      x-on:submit="enregistre = true">
    @csrf
    @method('PUT')

    <fieldset class="space-y-3">
        <legend class="text-sm font-semibold text-texte">Thème par défaut</legend>
        <p class="text-sm text-texte-doux">Appliqué à la page de connexion et aux nouveaux comptes. Chacun peut ensuite choisir le sien avec le bouton de l'en-tête.</p>
        <div class="grid gap-3 sm:grid-cols-3">
            @foreach (\App\Enums\PreferenceTheme::cases() as $choix)
                <label class="flex min-h-14 cursor-pointer items-center gap-3 rounded-controle border border-bordure-forte px-4 py-3 text-sm transition-colors duration-150 has-checked:border-primaire has-checked:bg-primaire-doux has-focus-visible:outline-2 has-focus-visible:outline-anneau">
                    <input type="radio" name="theme_defaut" value="{{ $choix->value }}" @checked($theme === $choix->value) class="sr-only">
                    <x-icone :nom="$choix->icone()" class="text-texte-doux" />
                    <span class="font-medium text-texte">{{ $choix->libelle() }}</span>
                </label>
            @endforeach
        </div>
    </fieldset>

    <fieldset class="space-y-3">
        <legend class="text-sm font-semibold text-texte">Couleur d'accent</legend>
        <p class="text-sm text-texte-doux">Boutons, liens, éléments actifs et en-têtes des documents PDF.</p>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5">
            @foreach ($accents as $choix)
                <label class="flex cursor-pointer flex-col items-center gap-2 rounded-controle border border-bordure-forte p-3 text-sm transition-colors duration-150 has-checked:border-texte has-checked:bg-fond has-focus-visible:outline-2 has-focus-visible:outline-anneau">
                    <input type="radio" name="couleur_accent" value="{{ $choix->value }}" x-model="accent" class="sr-only">
                    {{-- Pastille : jetons redéfinis localement par data-accent (aucune couleur codée en dur) --}}
                    <span data-accent="{{ $choix->value }}" class="flex h-10 w-full items-center justify-center rounded-lg bg-primaire text-primaire-texte">
                        <x-icone nom="check" taille="size-5" x-show="accent === '{{ $choix->value }}'" />
                    </span>
                    <span class="font-medium text-texte">{{ $choix->libelle() }}</span>
                </label>
            @endforeach
        </div>
    </fieldset>

    {{-- Aperçu en direct des composants avec l'accent choisi --}}
    <div class="rounded-carte border border-bordure bg-fond p-4">
        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-texte-doux">Aperçu</p>
        <div class="flex flex-wrap items-center gap-3">
            <x-bouton type="button" icone="plus" tabindex="-1">Bouton principal</x-bouton>
            <a href="#" class="text-sm font-medium text-lien underline" tabindex="-1" x-on:click.prevent>Lien</a>
            <span class="rounded-full bg-primaire-doux px-3 py-1 text-xs font-medium text-lien">Élément actif</span>
        </div>
    </div>

    <div class="flex justify-end border-t border-bordure pt-5">
        <x-bouton icone="save">Enregistrer</x-bouton>
    </div>
</form>
