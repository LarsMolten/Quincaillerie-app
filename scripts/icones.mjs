/**
 * Génère les icônes Lucide utilisées par l'application dans resources/icones/*.svg,
 * à partir du paquet « lucide » déjà installé (aucune ressource externe).
 *
 * Ajouter une icône : ajouter son nom (kebab-case, voir lucide.dev) à ICONES puis lancer « npm run icones ».
 */
import { icons } from 'lucide';
import { mkdirSync, readdirSync, rmSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const ICONES = [
    // Navigation et structure
    'menu', 'panel-left', 'house', 'layout-dashboard', 'x', 'ellipsis', 'ellipsis-vertical',
    'chevron-down', 'chevron-up', 'chevron-left', 'chevron-right', 'chevrons-up-down', 'external-link',
    // Actions
    'plus', 'circle-plus', 'pencil', 'trash-2', 'eye', 'eye-off', 'search', 'filter', 'download', 'upload',
    'printer', 'copy', 'refresh-cw', 'undo-2', 'save', 'log-in', 'log-out',
    // États et retours
    'check', 'circle-check', 'circle-alert', 'triangle-alert', 'info', 'circle-x', 'loader-circle', 'inbox', 'ban',
    'arrow-up', 'arrow-down', 'arrow-up-right', 'arrow-down-right', 'trending-up', 'trending-down',
    // Thème et compte
    'sun', 'moon', 'monitor', 'bell', 'user', 'users', 'settings', 'shield', 'key-round', 'lock', 'scroll-text',
    // Métier
    'package', 'boxes', 'warehouse', 'tags', 'ruler', 'truck', 'store', 'shopping-cart', 'receipt', 'file-text',
    'wallet', 'banknote', 'credit-card', 'hand-coins', 'percent', 'chart-column', 'chart-line', 'clipboard-list',
    'archive', 'history', 'barcode', 'scan-barcode', 'calendar', 'phone', 'mail', 'map-pin', 'smartphone',
    'hammer', 'wrench',
    // Caisse
    'shopping-basket', 'minus', 'keyboard', 'wifi-off', 'landmark', 'circle-check-big', 'user-round-search',
    // Factures
    'send', 'share-2', 'message-circle',
];

const ATTRIBUTS = 'xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
    + 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';

const pascal = (nom) => nom.replace(/(^|-)([a-z0-9])/g, (_, __, lettre) => lettre.toUpperCase());
const echapper = (valeur) => String(valeur).replace(/&/g, '&amp;').replace(/"/g, '&quot;');

const dossier = join(import.meta.dirname, '..', 'resources', 'icones');
mkdirSync(dossier, { recursive: true });
readdirSync(dossier).filter((f) => f.endsWith('.svg')).forEach((f) => rmSync(join(dossier, f)));

const absentes = [];
for (const nom of ICONES) {
    const noeuds = icons[pascal(nom)];
    if (!noeuds) {
        absentes.push(nom);
        continue;
    }
    const enfants = noeuds
        .map(([balise, attributs]) => `<${balise} ${Object.entries(attributs).map(([c, v]) => `${c}="${echapper(v)}"`).join(' ')}/>`)
        .join('');
    writeFileSync(join(dossier, `${nom}.svg`), `<svg ${ATTRIBUTS}>${enfants}</svg>\n`);
}

if (absentes.length) {
    console.error(`Icônes introuvables dans lucide : ${absentes.join(', ')}`);
    process.exit(1);
}
console.log(`${ICONES.length} icônes générées dans resources/icones.`);
