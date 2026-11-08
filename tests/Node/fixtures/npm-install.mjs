#!/usr/bin/env node
import { appendFileSync } from 'node:fs';

const args = process.argv.slice(2);
appendFileSync(process.env.NPM_FIXTURE_CALLS, `${args.join(' ')}\n`);

if (args[0] === '--version') {
    console.log(process.env.NPM_FIXTURE_VERSION);
} else {
    const phase = args[0] === 'install' && args.includes('--global')
        ? 'global'
        : args[0] === 'ci' ? (args.includes('--prefix') ? 'security' : 'project') : null;
    if (!phase) throw new Error(`Unexpected npm fixture command: ${args.join(' ')}`);
    console.log(`fixture ${phase} installation`);
    if (process.env.NPM_FIXTURE_WARNING_PHASE === phase) {
        console.warn(`npm warn deprecated fixture-${phase}@1.0.0: use a maintained package`);
    } else if (process.env.NPM_FIXTURE_WARNING_PHASE === 'ordinary') {
        console.warn(`npm warn optional ordinary ${phase} warning`);
    }
    if (process.env.NPM_FIXTURE_FAIL_PHASE === phase) {
        console.error(`npm error fixture ${phase} installation failed`);
        process.exitCode = 7;
    }
}
