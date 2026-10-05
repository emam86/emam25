import { defineConfig } from 'astro/config';
import { writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { htaccess } from './src/lib/redirects.mjs';

// Writes the Apache rules next to the built pages (Hostinger runs LiteSpeed,
// which reads .htaccess).
const apacheRules = {
  name: 'apache-rules',
  hooks: {
    'astro:build:done': async ({ dir }) => {
      await writeFile(new URL('.htaccess', dir), htaccess());
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
