/**
 * Champ de recherche avec raccourci clavier (« / » ou Ctrl+K) qui lui donne le focus.
 * La palette de commandes complète reprendra Ctrl+K plus tard.
 */
export default function (Alpine) {
    Alpine.data('recherche', () => ({
        init() {
            window.addEventListener('keydown', (e) => {
                const actif = document.activeElement;
                const saisieEnCours = ['INPUT', 'TEXTAREA', 'SELECT'].includes(actif?.tagName) || actif?.isContentEditable;

                if (((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') || (e.key === '/' && !saisieEnCours)) {
                    e.preventDefault();
                    this.$refs.champ.focus();
                    this.$refs.champ.select();
                }
            });
        },
    }));
}
