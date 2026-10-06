import { readFileSync } from 'node:fs';
import { validateExport } from '../src/lib/export.mjs';

try {
  validateExport(JSON.parse(readFileSync(process.argv[2], 'utf8')));
  console.log('ok');
} catch (error) {
  console.error(error.message);
  process.exitCode = 1;
}
