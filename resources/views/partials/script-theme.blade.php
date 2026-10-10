{{--
    Script exécuté dans le <head>, avant le premier rendu (aucun flash) :
    - thème : préférence enregistrée (utilisateur connecté) → localStorage → thème par défaut (Paramètres > Apparence) ; pose la classe « dark » ;
    - barre latérale repliée (mémorisée) : pose data-barre="repliee" sur <html>.
    Doit rester en ligne (un module Vite est différé). La préférence est lue sans dépendre de la base
    (rescue) pour que la page d'erreur 500 s'affiche même si la base est indisponible.
--}}
<script>
    (function () {
        var html = document.documentElement;
        var modes = ['clair', 'sombre', 'auto'];
        var mode = @json(rescue(fn () => auth()->user()?->preference_theme?->value, null, false));
        var defaut = {!! json_encode((string) rescue(fn () => \App\Models\Parametre::valeur('theme_defaut', 'auto'), 'auto', false)) !!};

        try {
            if (mode) {
                localStorage.setItem('theme', mode);
            } else {
                mode = localStorage.getItem('theme');
            }
            if (localStorage.getItem('barre-repliee') === '1') {
                html.dataset.barre = 'repliee';
            }
        } catch (e) {}

        if (modes.indexOf(mode) === -1) {
            mode = modes.indexOf(defaut) === -1 ? 'auto' : defaut;
        }

        var sombre = mode === 'sombre' || (mode === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        html.dataset.theme = mode;
        html.classList.toggle('dark', sombre);
    })();
</script>
