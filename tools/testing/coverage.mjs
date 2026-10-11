import { createHash } from 'node:crypto';
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const metricNames = ['lines', 'statements', 'functions', 'branches'];
const digest = value => createHash('sha256').update(value).digest('hex');
const location = value => `${value.start.line}:${value.start.column}-${value.end.line}:${value.end.column}`;

function relativeFile(filename, root) {
    const normalized = filename.replaceAll('\\', '/');
    const normalizedRoot = root.replaceAll('\\', '/').replace(/\/$/, '');
    if (!normalized.startsWith(`${normalizedRoot}/`)) throw new Error(`Coverage file outside source root: ${filename}`);
    const relative = normalized.slice(normalizedRoot.length + 1);
    if (!relative || relative.split('/').includes('..')) throw new Error(`Invalid coverage path: ${filename}`);
    return relative;
}

function metric(units) {
    return { total: Object.keys(units).length, covered: Object.values(units).filter(Boolean).length };
}

export function snapshotJavaScript(report, { root = process.cwd() } = {}) {
    const files = {};
    for (const [filename, coverage] of Object.entries(report)) {
        if (!coverage || !coverage.statementMap || !coverage.fnMap || !coverage.branchMap) throw new Error(`Invalid JavaScript coverage: ${filename}`);
        const relative = relativeFile(filename, root);
        const source = readFileSync(path.join(root, relative));
        const units = { lines: {}, statements: {}, functions: {}, branches: {} };
        for (const [id, hits] of Object.entries(coverage.s)) {
            const position = coverage.statementMap[id];
            if (!position || !Number.isFinite(hits) || hits < 0) throw new Error(`Invalid statement coverage: ${relative}`);
            units.statements[`${id}:${location(position)}`] = hits > 0;
            units.lines[position.start.line] = (units.lines[position.start.line] ?? false) || hits > 0;
        }
        for (const [id, hits] of Object.entries(coverage.f)) {
            const position = coverage.fnMap[id];
            if (!position || !Number.isFinite(hits) || hits < 0) throw new Error(`Invalid function coverage: ${relative}`);
            units.functions[`${id}:${position.name}:${location(position.loc)}`] = hits > 0;
        }
        for (const [id, hits] of Object.entries(coverage.b)) {
            const branch = coverage.branchMap[id];
            if (!branch || !Array.isArray(hits) || hits.length !== branch.locations.length) throw new Error(`Invalid branch coverage: ${relative}`);
            hits.forEach((count, index) => {
                if (!Number.isFinite(count) || count < 0) throw new Error(`Invalid branch hit count: ${relative}`);
                units.branches[`${id}:${branch.type}:${index}:${location(branch.locations[index])}`] = count > 0;
            });
        }
        const hashSource = /\.(?:[cm]?js|json)$/.test(relative) ? source.toString('utf8').replaceAll('\r\n', '\n') : source;
        files[relative] = { hash: digest(hashSource), metrics: Object.fromEntries(metricNames.map(name => [name, metric(units[name])])), units };
    }
    return createSnapshot('javascript', 'vitest-v8', files);
}

export function createSnapshot(language, profile, files, metadata = {}) {
    const names = language === 'php' ? ['lines', 'functions'] : metricNames;
    const metrics = Object.fromEntries(names.map(name => [name, { total: 0, covered: 0 }]));
    for (const file of Object.values(files)) {
        for (const name of names) {
            metrics[name].total += file.metrics[name].total;
            metrics[name].covered += file.metrics[name].covered;
        }
    }
    const snapshot = { schema: 1, language, profile, metadata, metrics, files: structuredClone(Object.fromEntries(Object.entries(files).sort(([a], [b]) => a.localeCompare(b)))) };
    validateSnapshot(snapshot);
    return snapshot;
}

export function validateSnapshot(snapshot) {
    if (snapshot?.schema !== 1 || !['javascript', 'php'].includes(snapshot.language) || typeof snapshot.profile !== 'string' || !snapshot.files || !snapshot.metrics) throw new Error('Invalid or missing coverage snapshot');
    if (Object.keys(snapshot.files).length === 0) throw new Error('Coverage snapshot contains no source files');
    const names = snapshot.language === 'php' ? ['lines', 'functions'] : metricNames;
    const totals = Object.fromEntries(names.map(name => [name, { total: 0, covered: 0 }]));
    for (const [filename, file] of Object.entries(snapshot.files)) {
        if (path.posix.isAbsolute(filename) || /^[a-z]:/i.test(filename) || filename.includes('\\') || filename.split('/').includes('..') || !/^[a-f0-9]{64}$/.test(file.hash)) throw new Error(`Invalid coverage source: ${filename}`);
        for (const name of names) {
            const value = file.metrics?.[name];
            const units = file.units?.[name];
            if (!value || !Number.isSafeInteger(value.total) || !Number.isSafeInteger(value.covered) || value.total < 0 || value.covered < 0 || value.covered > value.total || !units || Object.values(units).some(hit => typeof hit !== 'boolean')) throw new Error(`Invalid ${name} metric: ${filename}`);
            const actual = metric(units);
            if (value.total !== actual.total || value.covered !== actual.covered) throw new Error(`Inconsistent ${name} coverage: ${filename}`);
            totals[name].total += value.total;
            totals[name].covered += value.covered;
        }
    }
    for (const name of names) {
        if (snapshot.metrics[name]?.total !== totals[name].total || snapshot.metrics[name]?.covered !== totals[name].covered) throw new Error(`Inconsistent total ${name} coverage`);
    }
    return snapshot;
}

function hasRegressed(before, after) {
    if (before.total === 0) return false;
    if (after.total === 0) return before.covered > 0;
    return BigInt(after.covered) * BigInt(before.total) < BigInt(before.covered) * BigInt(after.total);
}

export function compareCoverage(baseline, candidate) {
    validateSnapshot(baseline);
    validateSnapshot(candidate);
    if (baseline.language !== candidate.language || baseline.profile !== candidate.profile) throw new Error('Coverage profiles are not comparable');
    const regressions = [];
    for (const name of Object.keys(baseline.metrics)) {
        if (hasRegressed(baseline.metrics[name], candidate.metrics[name])) regressions.push(`Total ${name} coverage decreased`);
    }
    for (const [filename, before] of Object.entries(baseline.files)) {
        const after = candidate.files[filename];
        if (!after) {
            regressions.push(`Source file missing from coverage: ${filename}`);
            continue;
        }
        for (const name of Object.keys(before.metrics)) {
            if (hasRegressed(before.metrics[name], after.metrics[name])) regressions.push(`${filename}: ${name} coverage decreased`);
            if (before.hash === after.hash) {
                for (const [id, covered] of Object.entries(before.units[name])) {
                    if (!(id in after.units[name])) regressions.push(`${filename}: ${name} ${id} missing from coverage`);
                    else if (covered && !after.units[name][id]) regressions.push(`${filename}: previously covered ${name} ${id} is uncovered`);
                }
            }
        }
    }
    return regressions;
}

export function mergeSnapshots(snapshots) {
    if (snapshots.length === 0) throw new Error('No coverage shards supplied');
    snapshots.forEach(validateSnapshot);
    const first = snapshots[0];
    const files = structuredClone(first.files);
    for (const snapshot of snapshots.slice(1)) {
        if (snapshot.language !== first.language || snapshot.profile !== first.profile) throw new Error('Coverage shard profiles differ');
        for (const [filename, file] of Object.entries(snapshot.files)) {
            if (!files[filename]) {
                files[filename] = structuredClone(file);
                continue;
            }
            const target = files[filename];
            if (file.hash !== target.hash) throw new Error(`Coverage shards contain different source: ${filename}`);
            for (const [name, units] of Object.entries(file.units)) {
                if (Object.keys(units).length !== Object.keys(target.units[name]).length || Object.keys(units).some(id => !(id in target.units[name]))) throw new Error(`Coverage shard locations differ: ${filename}`);
                for (const [id, hit] of Object.entries(units)) target.units[name][id] ||= hit;
                target.metrics[name] = metric(target.units[name]);
            }
        }
    }
    return createSnapshot(first.language, first.profile, files, first.metadata);
}

const read = filename => JSON.parse(readFileSync(filename, 'utf8'));
const write = (filename, report) => {
    mkdirSync(path.dirname(filename), { recursive: true });
    writeFileSync(filename, `${JSON.stringify(report)}\n`);
};

export function runCoverageCommand(args) {
    const [command, ...files] = args;
    if (command === 'js' && files.length === 2) {
        write(files[1], snapshotJavaScript(read(files[0])));
    } else if (command === 'merge' && files.length >= 3) {
        write(files[0], mergeSnapshots(files.slice(1).map(read)));
    } else if (command === 'compare' && files.length === 2) {
        const regressions = compareCoverage(read(files[0]), read(files[1]));
        if (regressions.length) throw new Error(`Coverage regression:\n${regressions.slice(0, 40).join('\n')}${regressions.length > 40 ? `\n... ${regressions.length} regressions` : ''}`);
        console.log('Coverage preserved globally, per file, and at unchanged source locations.');
    } else {
        throw new Error('Usage: coverage.mjs js <coverage-final.json> <snapshot.json> | compare <baseline.json> <candidate.json> | merge <output.json> <shard.json> <shard.json> [...]');
    }
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
    try { runCoverageCommand(process.argv.slice(2)); }
    catch (error) { console.error(error.message); process.exitCode = 1; }
}
