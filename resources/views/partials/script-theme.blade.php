{{--
    Script de thème exécuté dans le <head>, avant le premier rendu : applique la classe « dark »
    sans flash. Ordre de priorité : préférence enregistrée (utilisateur connecté) → localStorage → auto.
    Doit rester en ligne (un module Vite est différé et provoquerait un flash).
--}}
<script>
    (function () {
        var modes = ['clair', 'sombre', 'auto'];
        var mode = @json(auth()->user()?->preference_theme?->value);

        try {
            if (mode) {
                localStorage.setItem('theme', mode);
            } else {
                mode = localStorage.getItem('theme');
            }
        } catch (e) {}

        if (modes.indexOf(mode) === -1) {
            mode = 'auto';
        }

        var sombre = mode === 'sombre' || (mode === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.dataset.theme = mode;
        document.documentElement.classList.toggle('dark', sombre);
    })();
</script>
