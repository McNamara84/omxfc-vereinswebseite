import { readdirSync, readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

function walk(directory) {
    return readdirSync(directory, { withFileTypes: true }).flatMap(entry => {
        const filename = path.join(directory, entry.name);
        return entry.isDirectory() ? walk(filename) : [filename];
    });
}

function phpTestMethods(source) {
    const methods = [];
    for (const match of source.matchAll(/public\s+function\s+(\w+)\s*\(/g)) {
        let isTest = /^test/i.test(match[1]);
        let end = match.index;
        // Read adjacent attributes without nested regex repetitions that backtrack.
        while (!isTest) {
            while (end > 0 && /\s/.test(source[end - 1])) end--;
            if (source[end - 1] !== ']') break;
            const start = source.lastIndexOf('#[', end - 1);
            if (start < 0) break;
            const attribute = source.slice(start, end).replace(/\s/g, '');
            isTest = attribute === '#[Test]' || attribute.endsWith('\\Test]');
            end = start;
        }
        if (isTest) methods.push(match[1]);
    }
    return methods;
}

export function inventory(root = process.cwd()) {
    return walk(path.join(root, 'tests')).flatMap(filename => {
        const relative = path.relative(root, filename).split(path.sep).join('/');
        if (!/(Test\.php|\.test\.(js|mjs)|\.(spec|benchmark)\.js)$/.test(relative) && relative !== 'tests/Arch.php') return [];
        const source = readFileSync(filename, 'utf8');
        const classMethods = phpTestMethods(source);
        const declaredCases = [...source.matchAll(/\b(?:it|test)\(\s*['"]([^'"\n]+)/g)].map(match => match[1]);
        const directory = relative.split('/')[1];
        let runner = { Unit: 'php-unit', Feature: 'php-feature', Integration: 'php-integration', Vitest: 'vitest', Node: 'node', e2e: 'playwright', Browser: 'pest-browser' }[directory] ?? 'architecture';
        if (/MariaDb/.test(path.basename(filename))) runner = 'mariadb';
        if (relative === 'tests/Integration/TypesenseInternalSnapshotTest.php') runner = 'typesense-manual';
        if (directory === 'Browser' && !relative.endsWith('/ModalBackdropPreviewTest.php')) runner = 'pest-browser-manual';
        if (relative.endsWith('/homepage-performance.benchmark.js')) runner = 'benchmark';
        const inheritsPestApplication = declaredCases.length > 0 && ['Feature', 'Integration', 'Browser'].includes(directory);
        return [{ file: relative, runner, laravel: /use Tests\\TestCase;/.test(source) || inheritsPestApplication, database: /use (?:RefreshDatabase|DatabaseTransactions|DatabaseMigrations)/.test(source) || directory === 'Browser', methods: [...new Set([...classMethods, ...declaredCases])], seeders: [...new Set([...source.matchAll(/\b(\w+Seeder)\b/g)].map(match => match[1]))] }];
    }).sort((a, b) => a.file.localeCompare(b.file));
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
    const result = inventory();
    if (process.argv[2]) writeFileSync(process.argv[2], `${JSON.stringify(result, null, 2)}\n`);
    else console.log(JSON.stringify(result, null, 2));
}
