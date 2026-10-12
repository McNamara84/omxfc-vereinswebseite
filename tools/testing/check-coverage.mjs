import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { checkoutSourceExists, compareCoverage } from './coverage.mjs';

// PRs compare against the baseline committed on their base branch as well as
// the current floor. Editing the floor in a PR must not hide a regression.
const [baselinePath, candidatePath] = process.argv.slice(2);
const comparison = { sourceExists: checkoutSourceExists };
try {
    if (!baselinePath || !candidatePath || !baselinePath.startsWith('tests/coverage-baselines/')) throw new Error('Usage: check-coverage.mjs tests/coverage-baselines/<baseline.json> <candidate.json>');
    const baseline = JSON.parse(readFileSync(baselinePath, 'utf8'));
    const candidate = JSON.parse(readFileSync(candidatePath, 'utf8'));
    const regressions = compareCoverage(baseline, candidate, comparison);
    if (process.env.GITHUB_EVENT_NAME === 'pull_request') {
        const event = JSON.parse(readFileSync(process.env.GITHUB_EVENT_PATH, 'utf8'));
        const base = event.pull_request?.base?.sha;
        if (!/^[a-f0-9]{40}$/.test(base ?? '')) throw new Error('Missing PR base SHA');
        // checkout must fetch this commit. A missing commit is an infrastructure
        // failure; only the first introduction of this file permits bootstrap.
        execFileSync('git', ['cat-file', '-e', `${base}^{commit}`]);
        const present = execFileSync('git', ['ls-tree', '--name-only', base, '--', baselinePath], { encoding: 'utf8' }).trim();
        if (present) {
            const protectedBaseline = JSON.parse(execFileSync('git', ['show', `${base}:${baselinePath}`], { encoding: 'utf8', maxBuffer: 16 * 1024 * 1024 }));
            regressions.push(...compareCoverage(protectedBaseline, baseline, comparison).map(value => `Baseline lowered: ${value}`));
            regressions.push(...compareCoverage(protectedBaseline, candidate, comparison));
        } else {
            console.log('Initial baseline introduction; the measured pre-refactoring baseline is used.');
        }
    }
    if (regressions.length) throw new Error(`Coverage regression:\n${[...new Set(regressions)].slice(0, 40).join('\n')}`);
    console.log('Coverage floor preserved globally, per file, and at unchanged source locations.');
} catch (error) { console.error(error.message); process.exitCode = 1; }
