/**
 * Bundle budget check: fails CI when the initial JS payload (gzip) exceeds
 * the budget. Docs: Optimization.md §2.
 */
import { readFileSync, statSync } from 'node:fs';
import { gzipSync } from 'node:zlib';
import path from 'node:path';

const BUDGET_KB = 250;
const manifestPath = path.resolve('public/build/manifest.json');

const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));

const entry = manifest['resources/js/app.tsx'];
if (!entry) {
    console.error('FAIL: app entry missing from manifest');
    process.exit(1);
}

const files = Array.isArray(entry.imports) ? [entry.file, ...entry.imports] : [entry.file];

let total = 0;
for (const file of files) {
    const full = path.resolve('public/build', file);
    const raw = readFileSync(full);
    total += gzipSync(raw).length;
}

const totalKb = total / 1024;
const budgetKb = BUDGET_KB * 1024;

if (totalKb > budgetKb) {
    console.error(`FAIL: initial JS ${totalKb.toFixed(1)} kB gzip exceeds budget of ${BUDGET_KB} kB`);
    process.exit(1);
}

console.log(`OK: initial JS ${totalKb.toFixed(1)} kB gzip (budget ${BUDGET_KB} kB)`);
