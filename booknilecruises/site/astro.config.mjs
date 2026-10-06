import { defineConfig } from 'astro/config';
import { writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { htaccess, REDIRECTS } from './src/lib/redirects.mjs';
import { readExport } from './src/lib/export-file.mjs';
import { validateExport } from './src/lib/export.mjs';

// Writes the Apache rules next to the built pages (Hostinger runs LiteSpeed,
// which reads .htaccess).
const apacheRules = {
  name: 'apache-rules',
  hooks: {
    'astro:build:done': async ({ dir }) => {
      // Redirects added in the admin panel (validated, so they can't inject server rules) are exact-path
      // rules placed first, so they override the built-in ones.
      const exp = readExport();
      const panel = exp ? Object.fromEntries(validateExport(exp).redirects.map((r) => [r.from, r.to])) : {};
      await writeFile(new URL('.htaccess', dir), htaccess(REDIRECTS, { noindex: process.env.PUBLIC_NOINDEX === '1', exact: panel }));
    },
  },
};

export default defineConfig({
  site: 'https://booknilecruises.net',
  trailingSlash: 'always',
  build: { format: 'directory', inlineStylesheets: 'auto' },
  integrations: [apacheRules],
  vite: { resolve: { alias: { '@lib': fileURLToPath(new URL('./src/lib', import.meta.url)) } } },
});
