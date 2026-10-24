import test from 'node:test';
import assert from 'node:assert/strict';
import { assertLazyFeatures, initialAssets } from '../../tools/testing/feature-bundles.mjs';

test('initial graph follows shared static dependencies once and excludes dynamic features', () => {
    const manifest = {
        'resources/js/app.js': { file: 'app.js', imports: ['shared', 'other'], css: ['app.css'], dynamicImports: ['chart'] },
        shared: { file: 'shared.js', css: ['shared.css'] },
        other: { file: 'other.js', imports: ['shared'] },
        chart: { file: 'chart-abc.js' },
    };
    assert.deepEqual(assertLazyFeatures(manifest), ['app.css', 'app.js', 'other.js', 'shared.css', 'shared.js']);
    manifest.shared.imports = ['chart'];
    assert.throws(() => assertLazyFeatures(manifest), /Heavy features.*chart-abc/);
});

test('an incomplete build manifest cannot silently pass the graph check', () => {
    assert.throws(() => initialAssets({}), /Manifest entry missing/);
});

test('source maps detect heavy modules merged into an innocently named entry', () => {
    const manifest = { 'resources/js/app.js': { file: 'app.js' } };
    assert.throws(() => assertLazyFeatures(manifest, () => ['../../node_modules/leaflet/dist/leaflet-src.js']), /Heavy modules/);
    assert.throws(() => assertLazyFeatures(manifest, () => ['../../resources/js/alpine/char-editor.js']), /Heavy modules/);
    assert.deepEqual(assertLazyFeatures(manifest, () => ['../../resources/js/features/index.js']), ['app.js']);
});
