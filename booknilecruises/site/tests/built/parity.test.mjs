// Run after `npm run build`. Checks the built site against the old one.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import path from 'node:path';
import { REDIRECTS } from '../../src/lib/redirects.mjs';
import { seoFromHtml } from '../../src/lib/content.mjs';
import { gunzipSync } from 'node:zlib';

const DIST = path.resolve('dist');
const DATA = path.resolve('..', 'data');
const urls = JSON.parse(readFileSync(path.join(DATA, 'wp-export/sitemap-urls.json'), 'utf8'))
  .map((u) => new URL(u.url))
  .filter((u) => !u.search) // ?tourm_* builder posts resolve to the home page
  .map((u) => u.pathname);

const built = (p) => existsSync(path.join(DIST, p, 'index.html'));
const redirected = (p) => Boolean(REDIRECTS[p]);

function htmlFiles(dir) {
  return readdirSync(dir).flatMap((f) => {
    const full = path.join(dir, f);
    return statSync(full).isDirectory() ? htmlFiles(full) : f.endsWith('.html') ? [full] : [];
  });
}

test('every old sitemap URL is either rebuilt or redirected', () => {
  const missing = urls.filter((p) => !built(p) && !redirected(p));
  assert.deepEqual(missing, []);
});

test('no URL is both rebuilt and redirected', () => {
  const both = Object.keys(REDIRECTS).filter(built);
  assert.deepEqual(both, []);
});

test('redirect targets exist', () => {
  const broken = Object.entries(REDIRECTS).filter(([, to]) => !built(to));
  assert.deepEqual(broken, []);
});

test('the .htaccess carries every redirect', () => {
  const ht = readFileSync(path.join(DIST, '.htaccess'), 'utf8');
  for (const [from, to] of Object.entries(REDIRECTS)) assert.ok(ht.includes(`Redirect 301 ${from} ${to}`), from);
});

test('trip and term pages keep their live <title> and canonical', () => {
  const diffs = [];
  for (const p of urls.filter((u) => /^\/(trip|destinations|activities|trip-types)\/./.test(u) && built(u))) {
    const snap = path.join(DATA, 'html-snapshot', p, 'index.html.gz');
    if (!existsSync(snap)) continue;
    const old = seoFromHtml(gunzipSync(readFileSync(snap)).toString());
    const now = seoFromHtml(readFileSync(path.join(DIST, p, 'index.html'), 'utf8'));
    if (old.title !== now.title) diffs.push(`${p}: title "${old.title}" -> "${now.title}"`);
    if (old.canonical && old.canonical !== now.canonical) diffs.push(`${p}: canonical ${old.canonical} -> ${now.canonical}`);
  }
  assert.deepEqual(diffs, []);
});

test('every page has a title, a description and one h1', () => {
  const bad = [];
  for (const f of htmlFiles(DIST)) {
    const html = readFileSync(f, 'utf8');
    const rel = path.relative(DIST, f);
    if (rel === '404.html') continue;
    const h1 = (html.match(/<h1[\s>]/g) || []).length;
    if (!/<title>[^<]+<\/title>/.test(html)) bad.push(`${rel}: no title`);
    if (!/<meta name="description" content="[^"]{30,}"/.test(html)) bad.push(`${rel}: short or missing description`);
    if (h1 !== 1) bad.push(`${rel}: ${h1} h1`);
  }
  assert.deepEqual(bad, []);
});

test('internal links point at built pages, redirects or uploads', () => {
  const broken = new Set();
  for (const f of htmlFiles(DIST)) {
    const html = readFileSync(f, 'utf8');
    for (const [, href] of html.matchAll(/href="(\/[^"#?]*)/g)) {
      if (href.startsWith('/wp-content/') || href.startsWith('/_astro/') || href.startsWith('/img/')) continue;
      if (/\.(xml|txt|webp|jpg|png)$/.test(href)) continue;
      if (!built(href) && !redirected(href)) broken.add(`${path.relative(DIST, f)} -> ${href}`);
    }
  }
  assert.deepEqual([...broken], []);
});

test('every trip page carries its highlights, itinerary days and inclusions', async () => {
  const { loadSite, decodeEntities } = await import('../../src/lib/content.mjs');
  const missing = [];
  const norm = (s) => decodeEntities(s).replace(/\s+/g, ' ').trim();
  let checked = 0;
  for (const t of loadSite().trips) {
    const html = norm(readFileSync(path.join(DIST, t.url, 'index.html'), 'utf8').replace(/<[^>]+>/g, ' '));
    const expected = [t.title, ...t.highlights, ...t.itinerary.map((d) => d.title), ...t.includes, ...t.excludes].filter(Boolean);
    for (const e of expected) {
      checked++;
      if (!html.includes(norm(e))) missing.push(`${t.url}: ${e.slice(0, 60)}`);
    }
  }
  assert.ok(checked > 1500, `only ${checked} strings checked`);
  assert.deepEqual(missing, []);
});
