import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
import { validateSnapshot } from './coverage.mjs';

export function phpCoverageBadge(snapshot) {
    validateSnapshot(snapshot);
    if (snapshot.language !== 'php') throw new Error('A PHP coverage snapshot is required');
    const { covered, total } = snapshot.metrics.lines;
    const percent = total === 0 ? null : 100 * covered / total;
    const value = percent === null ? 'n/a' : `${percent.toFixed(1)}%`;
    const color = percent === null ? '#9f9f9f' : percent >= 90 ? '#4c1' : percent >= 80 ? '#97ca00' : percent >= 70 ? '#a4a61d' : percent >= 60 ? '#dfb317' : percent >= 50 ? '#fe7d37' : '#e05d44';
    return `<svg xmlns="http://www.w3.org/2000/svg" width="153" height="20" role="img" aria-label="PHP Coverage: ${value}">
<title>PHP Coverage: ${value}</title>
<linearGradient id="shade" x2="0" y2="100%"><stop offset="0" stop-color="#bbb" stop-opacity=".1"/><stop offset="1" stop-opacity=".1"/></linearGradient>
<clipPath id="round"><rect width="153" height="20" rx="3"/></clipPath>
<g clip-path="url(#round)"><path fill="#555" d="M0 0h101v20H0z"/><path fill="${color}" d="M101 0h52v20h-52z"/><path fill="url(#shade)" d="M0 0h153v20H0z"/></g>
<g fill="#fff" text-anchor="middle" font-family="Verdana,Geneva,DejaVu Sans,sans-serif" font-size="11"><text x="50.5" y="15" fill="#010101" fill-opacity=".3">PHP Coverage</text><text x="50.5" y="14">PHP Coverage</text><text x="127" y="15" fill="#010101" fill-opacity=".3">${value}</text><text x="127" y="14">${value}</text></g>
</svg>\n`;
}

if (process.argv[1] && import.meta.url === pathToFileURL(path.resolve(process.argv[1])).href) {
    const [input, output] = process.argv.slice(2);
    if (!input || !output) throw new Error('Usage: php-coverage-badge.mjs <php snapshot.json> <badge.svg>');
    const badge = phpCoverageBadge(JSON.parse(readFileSync(input, 'utf8')));
    mkdirSync(path.dirname(output), { recursive: true });
    writeFileSync(output, badge);
}
