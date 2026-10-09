{{--
    Squelette de chargement (pulsation désactivée si « réduire les animations »).

    Props :
    - type   : texte (défaut) | carte | ligne | stat
    - lignes : nombre de lignes (texte) ou de rangées (ligne), défaut 3
    - libelle : texte annoncé aux lecteurs d'écran (défaut "Chargement…")

    Exemple : <x-squelette type="ligne" :lignes="5" />
--}}
@props(['type' => 'texte', 'lignes' => 3, 'libelle' => 'Chargement…'])

@php($bloc = 'animate-squelette rounded-md bg-neutre-doux')

<div role="status" aria-label="{{ $libelle }}" {{ $attributes }}>
    @switch($type)
        @case('carte')
            <div class="space-y-4 rounded-carte border border-bordure bg-surface p-carte shadow-doux" aria-hidden="true">
                <div class="{{ $bloc }} h-5 w-2/5"></div>
                <div class="{{ $bloc }} h-32 w-full"></div>
                <div class="{{ $bloc }} h-4 w-3/4"></div>
            </div>
            @break

        @case('stat')
            <div class="space-y-3 rounded-carte border border-bordure bg-surface p-carte shadow-doux" aria-hidden="true">
                <div class="{{ $bloc }} h-4 w-1/3"></div>
                <div class="{{ $bloc }} h-8 w-1/2"></div>
                <div class="{{ $bloc }} h-4 w-2/3"></div>
            </div>
            @break

        @case('ligne')
            <div class="divide-y divide-bordure rounded-carte border border-bordure bg-surface" aria-hidden="true">
                @for ($i = 0; $i < $lignes; $i++)
                    <div class="flex items-center gap-4 px-4 py-3.5">
                        <div class="{{ $bloc }} size-9 shrink-0 rounded-controle"></div>
                        <div class="{{ $bloc }} h-4 flex-1"></div>
                        <div class="{{ $bloc }} hidden h-4 w-24 sm:block"></div>
                        <div class="{{ $bloc }} h-6 w-16 rounded-full"></div>
                    </div>
                @endfor
            </div>
            @break

        @default
            <div class="space-y-2.5" aria-hidden="true">
                @for ($i = 0; $i < $lignes; $i++)
                    <div class="{{ $bloc }} h-4 {{ $i === $lignes - 1 ? 'w-3/5' : 'w-full' }}"></div>
                @endfor
            </div>
    @endswitch
</div>
