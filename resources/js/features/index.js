import { createFeatureLifecycle } from './lifecycle';
import { mountAlpineFeature } from './alpine';

const alpineFeature = (name, load, register) => ({
    selector: `[data-feature="${name}"]`, load,
    mount: (module, root) => mountAlpineFeature(module[register], root),
});

export const features = [
    { selector: '#map[data-member-map-enabled]', load: () => import('../mitglieder/karte'), mount: (m, root) => m.mountMemberMap(root) },
    { selector: '[data-maddraxiversum-map]', load: () => import('../maddraxiversum'), mount: (m, root, context) => m.mountMaddraxiversum(root, context) },
    { selector: '[data-poll-charts]:has(canvas)', load: () => import('../polls/charts'), mount: (m, root) => m.mountPollCharts(root) },
    { selector: '#admin-charts-config', load: () => import('../admin/charts'), mount: (m, root) => m.mountAdminCharts(root) },
    { selector: 'canvas[data-statistik-chart]', load: () => import('../statistik'), mount: (m, root) => m.mountStatistikChart(root) },
    { selector: '[data-three-d-viewer]', load: () => import('../three-d-viewer'), mount: (m, root) => m.initThreeDViewer(root, root.dataset.fileUrl, root.dataset.format) },
    { selector: '[data-rpg-combat]', load: () => import('../rpg-combats'), mount: (m, root) => m.mountCombat(root) },
    alpineFeature('char-editor', () => import('../alpine/char-editor'), 'registerCharEditor'),
    alpineFeature('rpg-checks', () => import('../alpine/rpg-checks'), 'registerRpgChecks'),
    alpineFeature('rpg-progression', () => import('../alpine/rpg-progression'), 'registerRpgProgression'),
];

export const featureLifecycle = createFeatureLifecycle(features);
