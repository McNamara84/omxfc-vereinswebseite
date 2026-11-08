import { spawn } from 'node:child_process';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
import { stripVTControlCharacters } from 'node:util';

// Match warning formats, not the word "deprecated" in test titles or payloads.
const warningFormats = [
    /^\s*npm\s+(?:warn|WARN)\s+deprecated\b/,
    /^\s*(?:\(node:\d+\)\s*)?(?:\[DEP[\w]+\]\s*)?DeprecationWarning:/,
    /^\s*(?:\[(?:vite|vitest)[^\]]*\]\s*)?(?:DEPRECATED\b|\[DEPRECATION(?: WARNING)?\]|Deprecation(?: Warning|Warning)?\s*:)/i,
    /^\s*(?:\[vite[^\]]*\]\s*)?The CJS build of Vite's Node API is deprecated\b/,
];

export function findDeprecations(output) {
    return [...new Set(stripVTControlCharacters(output).split(/\r?\n/)
        .filter(line => warningFormats.some(pattern => pattern.test(line)))
        .map(line => line.trim()))];
}

function printWarnings(warnings, stderr) {
    if (warnings.length) stderr.write(`Deprecation check failed:\n${warnings.join('\n')}\n`);
}

export async function runChecked(args, { env = process.env, cwd = process.cwd(), stdout = process.stdout, stderr = process.stderr } = {}) {
    if (!args.length) throw new Error('Missing Node script to check');
    const child = spawn(process.execPath, args, {
        cwd, windowsHide: true, shell: false, stdio: ['inherit', 'pipe', 'pipe'],
        env: { ...env, NODE_OPTIONS: `${env.NODE_OPTIONS ?? ''} --throw-deprecation --trace-deprecation`.trim() },
    });
    const warnings = new Set();
    for (const [stream, destination] of [[child.stdout, stdout], [child.stderr, stderr]]) {
        let pending = '';
        stream.setEncoding('utf8');
        stream.on('data', chunk => {
            destination.write(chunk);
            pending += chunk;
            const end = pending.lastIndexOf('\n');
            if (end >= 0) {
                for (const warning of findDeprecations(pending.slice(0, end))) warnings.add(warning);
                pending = pending.slice(end + 1);
            }
        });
        stream.on('end', () => {
            for (const warning of findDeprecations(pending)) warnings.add(warning);
        });
    }
    const code = await new Promise((resolve, reject) => {
        child.once('error', reject);
        child.once('close', (status, signal) => resolve(signal ? 1 : status ?? 1));
    });
    printWarnings([...warnings], stderr);
    return code || (warnings.size ? 1 : 0);
}

if (process.argv[1] && import.meta.url === pathToFileURL(path.resolve(process.argv[1])).href) {
    try {
        const args = process.argv.slice(2);
        if (args[0] === '--run') process.exitCode = await runChecked(args.slice(1));
        else {
            if (!args.length) throw new Error('Usage: check-deprecations.mjs <log...> | --run <node-script> [args...]');
            const warnings = args.flatMap(filename => findDeprecations(readFileSync(filename, 'utf8')));
            printWarnings([...new Set(warnings)], process.stderr);
            process.exitCode = warnings.length ? 1 : 0;
        }
    } catch (error) { console.error(error.message); process.exitCode = 1; }
}
