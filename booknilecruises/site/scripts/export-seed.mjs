// Writes today's site data (WordPress export + snapshots + overrides) in the
// admin panel's export format. The panel imports this file once (phase 8),
// and the parity check builds the site from it.
//   node scripts/export-seed.mjs ../data/seed/export.json
import { mkdirSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { loadSite } from '../src/lib/content.mjs';
import { exportFromSite } from '../src/lib/export.mjs';

const out = process.argv[2];
if (!out) {
  console.error('Usage: node scripts/export-seed.mjs <output.json>');
  process.exit(2);
}
const exp = exportFromSite(loadSite({ imagesBase: '/images' }));
mkdirSync(path.dirname(path.resolve(out)), { recursive: true });
writeFileSync(out, JSON.stringify(exp, null, 1));
console.log(`${out}: ${exp.trips.length} trips, ${exp.terms.length} terms, ${exp.media.length} photos, ${exp.posts.length} posts`);
