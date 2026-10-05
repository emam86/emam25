#!/usr/bin/env node
// Read-only export of every public piece of content on booknilecruises.net
// through the WordPress REST API. It never writes to the live site.
//
// Usage:  node scripts/export-wp.mjs [outDir]
// Behind a proxy (Node 22.21+): NODE_USE_ENV_PROXY=1 node scripts/export-wp.mjs

import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';

const SITE = process.env.WP_SITE || 'https://booknilecruises.net';
const OUT = process.argv[2] || path.join(import.meta.dirname, '..', 'data', 'wp-export');
const UA = 'Mozilla/5.0 (booknilecruises-migration-export)';

// REST collections to export in full. We drop the rendered AIOSEO head
// HTML but keep the structured SEO JSON (aioseo_head_json) for parity.
const COLLECTIONS = {
  pages: 'wp/v2/pages',
  posts: 'wp/v2/posts',
  trips: 'wp/v2/trip',
  media: 'wp/v2/media',
  categories: 'wp/v2/categories',
  tags: 'wp/v2/tags',
  destination: 'wp/v2/destination',
  activities: 'wp/v2/activities',
  trip_types: 'wp/v2/trip_types',
  difficulty: 'wp/v2/difficulty',
  trip_tag: 'wp/v2/trip_tag',
  products: 'wp/v2/product',
};
const DROP_FIELDS = ['aioseo_head', '_links'];

async function get(url, attempt = 1) {
  const res = await fetch(url, { headers: { 'User-Agent': UA } });
  if (res.status >= 500 && attempt < 4) {
    await new Promise((r) => setTimeout(r, 1000 * 2 ** attempt));
    return get(url, attempt + 1);
  }
  return res;
}

async function fetchAll(route) {
  const items = [];
  for (let page = 1; ; page++) {
    const res = await get(`${SITE}/wp-json/${route}?per_page=100&page=${page}&context=view`);
    if (res.status === 400 && page > 1) break; // past the last page
    if (!res.ok) throw new Error(`${route} page ${page}: HTTP ${res.status}`);
    const batch = await res.json();
    for (const item of batch) for (const f of DROP_FIELDS) delete item[f];
    items.push(...batch);
    const totalPages = Number(res.headers.get('x-wp-totalpages') || 1);
    if (page >= totalPages) break;
  }
  return items;
}

async function sitemapUrls() {
  const index = await (await get(`${SITE}/sitemap.xml`)).text();
  const maps = [...index.matchAll(/<loc><!\[CDATA\[([^\]]+)\]\]><\/loc>/g)].map((m) => m[1]);
  const urls = [];
  for (const map of maps) {
    const xml = await (await get(map)).text();
    for (const m of xml.matchAll(/<loc><!\[CDATA\[([^\]]+)\]\]><\/loc>/g)) {
      urls.push({ sitemap: path.basename(map), url: m[1] });
    }
  }
  return urls;
}

async function main() {
  await mkdir(OUT, { recursive: true });
  const summary = { site: SITE, exportedAt: new Date().toISOString(), counts: {} };

  for (const [name, route] of Object.entries(COLLECTIONS)) {
    const items = await fetchAll(route);
    await writeFile(path.join(OUT, `${name}.json`), JSON.stringify(items, null, 2));
    summary.counts[name] = items.length;
    console.log(`${name.padEnd(12)} ${items.length}`);
  }

  // Trip packages (pricing tiers) live in WP Travel Engine's own API.
  const trips = JSON.parse(await readFile(path.join(OUT, 'trips.json'), 'utf8'));
  const packages = {};
  for (const trip of trips) {
    const res = await get(`${SITE}/wp-json/wptravelengine/v2/trips/${trip.id}/packages`);
    packages[trip.id] = res.ok ? await res.json() : { error: res.status };
  }
  await writeFile(path.join(OUT, 'trip-packages.json'), JSON.stringify(packages, null, 2));
  summary.counts.tripPackages = Object.keys(packages).length;
  console.log(`tripPackages ${summary.counts.tripPackages}`);

  const urls = await sitemapUrls();
  await writeFile(path.join(OUT, 'sitemap-urls.json'), JSON.stringify(urls, null, 2));
  summary.counts.sitemapUrls = urls.length;
  console.log(`sitemapUrls  ${urls.length}`);

  await writeFile(path.join(OUT, 'summary.json'), JSON.stringify(summary, null, 2));
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
