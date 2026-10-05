#!/usr/bin/env node
// Saves the rendered HTML of every URL in the exported sitemap, gzipped, so we
// keep a copy of what visitors see today (trip highlights and galleries are
// only present in the rendered pages, not in the REST API). Read-only.
//
// Usage:  NODE_USE_ENV_PROXY=1 node scripts/snapshot-html.mjs

import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { gzipSync } from 'node:zlib';

const ROOT = path.join(import.meta.dirname, '..');
const OUT = path.join(ROOT, 'data', 'html-snapshot');
const UA = 'Mozilla/5.0 (booknilecruises-migration-snapshot)';

export function snapshotPath(url) {
  const { pathname } = new URL(url);
  return path.join(OUT, pathname, 'index.html.gz');
}

async function main() {
  const urls = JSON.parse(await readFile(path.join(ROOT, 'data/wp-export/sitemap-urls.json'), 'utf8'))
    .map((u) => u.url)
    .filter((u) => !new URL(u).search); // ?tourm_* builder posts render the home page
  let failed = 0;
  for (const [i, url] of urls.entries()) {
    const res = await fetch(url, { headers: { 'User-Agent': UA } });
    if (!res.ok) {
      failed++;
      console.warn(`HTTP ${res.status} ${url}`);
      continue;
    }
    const file = snapshotPath(url);
    await mkdir(path.dirname(file), { recursive: true });
    await writeFile(file, gzipSync(await res.text()));
    if (i % 25 === 0) console.log(`${i + 1}/${urls.length}`);
  }
  console.log(`saved ${urls.length - failed}/${urls.length}`);
  if (failed) process.exitCode = 1;
}

if (import.meta.url === `file://${process.argv[1]}`) main();
