// Reads the admin panel's content export when the build is pointed at one
// (BNC_EXPORT=/path/to/export.json). Without it the site builds from data/.

import { readFileSync } from 'node:fs';

let cached;

export function readExport() {
  if (cached !== undefined) return cached;
  const file = process.env.BNC_EXPORT;
  cached = file ? JSON.parse(readFileSync(file, 'utf8')) : null;
  return cached;
}

/** Panel settings (contact details, verification codes, analytics), or {} without an export. */
export function exportSettings() {
  return readExport()?.settings ?? {};
}
