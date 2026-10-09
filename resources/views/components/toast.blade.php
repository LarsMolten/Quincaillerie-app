{{--
    Conteneur global des notifications « toast » (inclus une fois dans layouts/base).
    Types : succes, erreur, alerte, info. Auto-fermeture (pause au survol ou au focus), empilement de 5 au plus.

    Déclenchement :
    - Laravel : return back()->with('succes', 'Produit enregistré.');
    - Alpine  : $dispatch('toast', { type: 'erreur', message: 'Stock insuffisant.' })
    - JS      : window.toast('info', 'Synchronisation terminée.')
--}}
<div
    x-data
    class="pointer-events-none fixed inset-x-0 top-0 z-50 flex flex-col items-center gap-2 p-4 sm:inset-x-auto sm:top-auto sm:bottom-0 sm:right-0 sm:items-end"
>
    {{-- Région annoncée poliment ; les erreurs ont leur propre role="alert" --}}
    <div aria-live="polite" class="contents">
        <template x-for="toast in $store.toasts.liste" x-bind:key="toast.id">
            <div
                x-bind:role="toast.type === 'erreur' ? 'alert' : 'status'"
                x-on:mouseenter="$store.toasts.pause(toast.id)"
                x-on:mouseleave="$store.toasts.reprendre(toast.id)"
                x-on:focusin="$store.toasts.pause(toast.id)"
                x-on:focusout="$store.toasts.reprendre(toast.id)"
                class="verre pointer-events-auto flex w-full max-w-sm animate-glissement items-start gap-3 rounded-controle border border-bordure p-4 shadow-eleve"
            >
                <span class="mt-0.5" aria-hidden="true">
                    <span x-show="toast.type === 'succes'" class="text-succes"><x-icone nom="circle-check" /></span>
                    <span x-show="toast.type === 'erreur'" class="text-danger"><x-icone nom="circle-x" /></span>
                    <span x-show="toast.type === 'alerte'" class="text-alerte"><x-icone nom="triangle-alert" /></span>
                    <span x-show="toast.type === 'info'" class="text-info"><x-icone nom="info" /></span>
                </span>
                <div class="flex-1 text-sm">
                    <p class="font-medium text-texte" x-text="toast.message"></p>
                    <template x-if="toast.lien">
                        <a x-bind:href="toast.lien.url" x-bind:target="toast.lien.nouvelOnglet ? '_blank' : null"
                           class="mt-1 inline-flex items-center gap-1 font-semibold text-lien underline-offset-4 hover:underline">
                            <span x-text="toast.lien.libelle"></span> <x-icone nom="external-link" taille="size-3.5" />
                        </a>
                    </template>
                </div>
                <button type="button" x-on:click="$store.toasts.fermer(toast.id)" aria-label="Fermer la notification"
                        class="-m-1.5 grid size-8 shrink-0 place-items-center rounded-lg text-texte-doux hover:bg-neutre-doux hover:text-texte pointer-coarse:size-11">
                    <x-icone nom="x" taille="size-4" />
                </button>
            </div>
        </template>
    </div>
</div>
