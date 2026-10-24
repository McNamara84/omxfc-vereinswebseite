import { readFileSync } from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
import { gzipSync } from 'node:zlib';

export function initialAssets(manifest, entry = 'resources/js/app.js') {
    const visited = new Set();
    const assets = new Set();
    const visit = key => {
        if (visited.has(key)) return;
        visited.add(key);
        const chunk = manifest[key];
        if (!chunk) throw new Error(`Manifest entry missing: ${key}`);
        assets.add(chunk.file);
        for (const css of chunk.css ?? []) assets.add(css);
        for (const dependency of chunk.imports ?? []) visit(dependency);
    };
    visit(entry);
    return [...assets].sort();
}

export function assertLazyFeatures(manifest, readSources) {
    const initial = initialAssets(manifest);
    const heavy = /(?:leaflet|chart-|charts-|karte-|maddraxiversum-|char-editor-|rpg-checks-|rpg-progression-|rpg-combats-|three-d-viewer-)/;
    const eager = initial.filter(asset => asset.endsWith('.js') && heavy.test(asset));
    if (eager.length) throw new Error(`Heavy features in initial graph: ${eager.join(', ')}`);
    // Chunk names alone cannot detect a heavy module merged into app.js.
    if (readSources) {
        const heavySource = /(?:node_modules\/(?:leaflet(?:\.markercluster)?|chart\.js|three)\/|resources\/js\/(?:alpine\/(?:char-editor|rpg-checks|rpg-progression)|rpg-combats|three-d-viewer)\.js)/;
        const bundled = initial.filter(file => file.endsWith('.js')).flatMap(readSources).filter(source => heavySource.test(source.replaceAll('\\', '/')));
        if (bundled.length) throw new Error(`Heavy modules bundled into initial graph: ${bundled.join(', ')}`);
    }
    return initial;
}

if (process.argv[1] && import.meta.url === pathToFileURL(path.resolve(process.argv[1])).href) {
    const directory = path.resolve(process.argv[2] ?? 'public/build');
    const manifest = JSON.parse(readFileSync(path.join(directory, 'manifest.json'), 'utf8'));
    const initial = assertLazyFeatures(manifest, file => JSON.parse(readFileSync(path.join(directory, `${file}.map`), 'utf8')).sources);
    const stylesheet = manifest['resources/css/app.css'] ? initialAssets(manifest, 'resources/css/app.css') : [];
    const assets = [...new Set([...initial, ...stylesheet])].map(file => {
        const content = readFileSync(path.join(directory, file));
        return { file, bytes: content.length, gzipBytes: gzipSync(content).length };
    });
    console.log(JSON.stringify({ entry: 'resources/js/app.js', assets }, null, 2));
}
