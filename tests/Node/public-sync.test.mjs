import test from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const script = fileURLToPath(new URL('../../docker/tests/sync-public.sh', import.meta.url));

function fixture(t) {
    const root = mkdtempSync(path.join(tmpdir(), 'omxfc-public-sync-'));
    t.after(() => {
        if (!root.startsWith(path.resolve(tmpdir()) + path.sep) || !path.basename(root).startsWith('omxfc-public-sync-')) throw new Error('Unexpected temporary fixture path');
        rmSync(root, { recursive: true, force: true });
    });
    const source = path.join(root, 'source public');
    const target = path.join(root, 'target public');
    const write = (directory, filename, content) => {
        const destination = path.join(directory, filename);
        mkdirSync(path.dirname(destination), { recursive: true });
        writeFileSync(destination, content);
    };
    const sync = () => spawnSync('sh', [script, source, target], { encoding: 'utf8' });
    return { source, target, write, sync };
}

test('public sources refresh edits, additions and deletions while preserving generated assets', t => {
    const { source, target, write, sync } = fixture(t);
    write(target, 'robots.txt', 'stale robots');
    write(target, 'images/brina-rating.webp', 'stale image');
    write(target, 'deleted.txt', 'removed in checkout');
    write(target, 'old-directory/deleted.txt', 'removed directory');
    write(target, '.old-hidden', 'removed hidden file');
    write(target, 'build/manifest.json', 'mounted build');
    write(target, 'vendor/livewire/livewire.js', 'generated vendor');
    write(source, 'robots.txt', 'current robots');
    write(source, 'images/brina-rating.webp', Buffer.from([0, 1, 2, 255]));
    write(source, 'new directory/added.txt', 'new asset');
    write(source, '.htaccess', 'current hidden file');
    write(source, 'build/manifest.json', 'host build must not replace mounted build');
    write(source, 'vendor/livewire/livewire.js', 'host vendor must not replace generated vendor');

    const first = sync();
    assert.equal(first.status, 0, first.stderr);
    assert.equal(readFileSync(path.join(target, 'robots.txt'), 'utf8'), 'current robots');
    assert.deepEqual(readFileSync(path.join(target, 'images/brina-rating.webp')), Buffer.from([0, 1, 2, 255]));
    assert.equal(readFileSync(path.join(target, 'new directory/added.txt'), 'utf8'), 'new asset');
    assert.equal(readFileSync(path.join(target, '.htaccess'), 'utf8'), 'current hidden file');
    for (const filename of ['deleted.txt', 'old-directory', '.old-hidden']) assert.equal(existsSync(path.join(target, filename)), false, filename);

    rmSync(path.join(source, 'images/brina-rating.webp'));
    write(source, 'robots.txt', 'next robots');
    const second = sync();
    assert.equal(second.status, 0, second.stderr);
    assert.equal(readFileSync(path.join(target, 'robots.txt'), 'utf8'), 'next robots');
    assert.equal(existsSync(path.join(target, 'images/brina-rating.webp')), false);
    assert.equal(readFileSync(path.join(target, 'build/manifest.json'), 'utf8'), 'mounted build');
    assert.equal(readFileSync(path.join(target, 'vendor/livewire/livewire.js'), 'utf8'), 'generated vendor');
});

test('a missing public source fails before deleting existing container files', t => {
    const { target, write, sync } = fixture(t);
    write(target, 'robots.txt', 'keep until a valid source exists');
    const result = sync();
    assert.notEqual(result.status, 0);
    assert.equal(readFileSync(path.join(target, 'robots.txt'), 'utf8'), 'keep until a valid source exists');
});
