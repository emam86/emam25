// Content layer: turns the raw WordPress export (data/wp-export) and the
// rendered-page snapshots (data/html-snapshot) into the objects the site
// templates render. The raw export is never modified; everything here is a
// pure read so the build can be rerun after a fresh export.

import { readFileSync, existsSync } from 'node:fs';
import { gunzipSync } from 'node:zlib';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const DATA = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../../data');
const SITE_ORIGIN = 'https://booknilecruises.net';

const NAMED = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: ' ', hellip: '…', ndash: '–', mdash: '—', rsquo: '’', lsquo: '‘', rdquo: '”', ldquo: '“', times: '×' };

export function decodeEntities(str = '') {
  return str
    .replace(/&#x([0-9a-f]+);/gi, (_, h) => String.fromCodePoint(parseInt(h, 16)))
    .replace(/&#(\d+);/g, (_, d) => String.fromCodePoint(Number(d)))
    .replace(/&([a-z]+);/gi, (m, n) => NAMED[n.toLowerCase()] ?? m);
}

export function stripTags(html = '') {
  return decodeEntities(html.replace(/<[^>]+>/g, ' ')).replace(/\s+/g, ' ').trim();
}

// Site links become root-relative; uploads point at `assetOrigin`, which is ''
// in production (the files stay on the server) and the live site in previews.
export function rewriteHtml(html = '', assetOrigin = '') {
  return html
    .replace(/<!--\s*\/?wp:[\s\S]*?-->\n?/g, '')
    .replace(/https?:\/\/booknilecruises\.net(?=["']|\/(?!wp-content\/))/g, '')
    .replace(/https?:\/\/booknilecruises\.net\/wp-content\//g, `${assetOrigin}/wp-content/`)
    .replace(/<sup><\/sup>/g, '')
    .trim();
}

export function splitLines(text = '') {
  return text
    .split(/\n+/)
    .map((l) => decodeEntities(l).trim())
    .filter(Boolean);
}

export function extractHighlights(html = '') {
  const list = html.match(/<ul class=['"]wpte-trip-highlights['"][^>]*>([\s\S]*?)<\/ul>/);
  if (!list) return [];
  return [...list[1].matchAll(/<li[^>]*>([\s\S]*?)<\/li>/g)].map((m) => stripTags(m[1])).filter(Boolean);
}

export function extractGallery(html = '') {
  const start = html.indexOf('single-trip-main-carousel');
  if (start === -1) return [];
  // The main carousel ends where the thumbnail carousel or banner area ends.
  const end = html.indexOf('</div></div></div>', start);
  const block = html.slice(start, end === -1 ? undefined : end);
  const seen = new Set();
  const out = [];
  const re = /data-full-image="([^"]+)"[^>]*>\s*<img[^>]*?width="(\d+)" height="(\d+)"/g;
  for (const m of block.matchAll(re)) {
    const src = new URL(m[1]).pathname;
    if (seen.has(src)) continue;
    seen.add(src);
    out.push({ src, width: Number(m[2]), height: Number(m[3]) });
  }
  return out;
}

function readJson(name) {
  return JSON.parse(readFileSync(path.join(DATA, 'wp-export', name), 'utf8'));
}

function readSnapshot(url) {
  const file = path.join(DATA, 'html-snapshot', new URL(url, SITE_ORIGIN).pathname, 'index.html.gz');
  return existsSync(file) ? gunzipSync(readFileSync(file)).toString('utf8') : '';
}

function pathOf(link) {
  return new URL(link, SITE_ORIGIN).pathname;
}

// What search engines see today. The rendered page is the source of truth:
// for Elementor pages the REST API reports the footer template's SEO data.
export function seoFromHtml(html = '') {
  const meta = (attr, name) =>
    html.match(new RegExp(`<meta ${attr}="${name}" content="([^"]*)"`))?.[1];
  const title = html.match(/<title>([^<]*)<\/title>/)?.[1];
  const canonical = html.match(/<link rel="canonical" href="([^"]+)"/)?.[1];
  const og = meta('property', 'og:image');
  return {
    title: title ? decodeEntities(title) : null,
    description: meta('name', 'description') ? decodeEntities(meta('name', 'description')) : null,
    canonical: canonical ? pathOf(canonical) : null,
    ogImage: og ? pathOf(og) : null,
  };
}

function seoOf(item, snapshot = '') {
  const h = item.aioseo_head_json || {};
  const live = seoFromHtml(snapshot);
  return {
    title: live.title ?? decodeEntities(h.title || ''),
    description: live.description ?? decodeEntities(h.description || ''),
    canonical: live.canonical ?? (h.canonical_url ? pathOf(h.canonical_url) : null),
    ogImage: live.ogImage ?? (h.og?.['og:image'] ? pathOf(h.og['og:image']) : null),
  };
}

function imageFromMedia(media) {
  if (!media) return null;
  return {
    src: pathOf(media.source_url),
    width: media.media_details?.width ?? null,
    height: media.media_details?.height ?? null,
    alt: decodeEntities(media.alt_text || ''),
  };
}

function imageFromTripField(fi) {
  if (!fi?.file) return null;
  return { src: `/wp-content/uploads/${fi.file}`, width: fi.width ?? null, height: fi.height ?? null, alt: '' };
}

function priceOf(value) {
  return value === '' || value == null || Number.isNaN(Number(value)) ? null : Number(value);
}

function buildTerms(raw, taxonomy) {
  return raw.map((t) => ({
    id: t.id,
    taxonomy,
    slug: t.slug,
    name: decodeEntities(t.name),
    parent: t.parent,
    url: pathOf(t.link),
    description: t.description || '',
    seo: seoOf(t, readSnapshot(t.link)),
    trips: [],
  }));
}

export function normalizeTrip(raw, ctx) {
  const snapshot = ctx.snapshot ?? '';
  const pick = (ids, list) => ids.map((id) => list.find((t) => t.id === id)).filter(Boolean);
  const overview = raw.content?.rendered?.trim() ? raw.content.rendered : raw.description || '';
  const gallery = extractGallery(snapshot);
  const image = imageFromMedia(ctx.media.get(raw.featured_media)) || imageFromTripField(raw.featured_image) || gallery[0] || null;
  return {
    id: Number(raw.id),
    slug: raw.slug,
    url: pathOf(raw.link),
    title: decodeEntities(raw.title.rendered),
    excerpt: stripTags(raw.excerpt?.rendered || ''),
    code: raw.code,
    price: priceOf(raw.price),
    salePrice: raw.has_sale ? priceOf(raw.sale_price) : null,
    currency: raw.currency?.code || 'USD',
    duration: { days: Number(raw.duration?.days) || null, nights: Number(raw.duration?.nights) || null },
    minPax: priceOf(raw.min_pax),
    maxPax: priceOf(raw.max_pax),
    overviewHtml: rewriteHtml(overview, ctx.assetOrigin),
    highlights: extractHighlights(snapshot),
    itinerary: (raw.itineraries || []).map((d) => ({
      title: decodeEntities(d.title || ''),
      html: rewriteHtml(d.content || '', ctx.assetOrigin),
    })),
    includes: splitLines(raw.cost_includes),
    excludes: splitLines(raw.cost_excludes),
    faqs: (raw.faqs || []).map((f) => ({
      q: decodeEntities(f.question ?? f.title ?? ''),
      a: rewriteHtml(f.answer ?? f.content ?? '', ctx.assetOrigin),
    })),
    image,
    gallery: gallery.length ? gallery : image ? [image] : [],
    destinations: pick(raw.destination || [], ctx.terms.destination),
    activities: pick(raw.activities || [], ctx.terms.activities),
    tripTypes: pick(raw.trip_types || [], ctx.terms.trip_types),
    featured: Boolean(raw.is_featured),
    modified: raw.modified,
    seo: seoOf(raw, snapshot),
  };
}

function normalizePage(raw, assetOrigin) {
  return {
    id: raw.id,
    slug: raw.slug,
    url: pathOf(raw.link),
    title: decodeEntities(raw.title.rendered),
    html: rewriteHtml(raw.content.rendered, assetOrigin),
    text: stripTags(raw.content.rendered),
    featuredMedia: raw.featured_media || null,
    date: raw.date,
    modified: raw.modified,
    seo: seoOf(raw, readSnapshot(raw.link)),
  };
}

let cache;

export function loadSite({ assetOrigin = process.env.PUBLIC_ASSET_ORIGIN ?? '' } = {}) {
  if (cache && cache.assetOrigin === assetOrigin) return cache;
  const mediaList = readJson('media.json');
  const media = new Map(mediaList.map((m) => [m.id, m]));
  const terms = {
    destination: buildTerms(readJson('destination.json'), 'destination'),
    activities: buildTerms(readJson('activities.json'), 'activities'),
    trip_types: buildTerms(readJson('trip_types.json'), 'trip_types'),
  };
  const trips = readJson('trips.json')
    .map((raw) => normalizeTrip(raw, { media, terms, assetOrigin, snapshot: readSnapshot(raw.link) }))
    .sort((a, b) => a.title.localeCompare(b.title));

  // Attach trips to their terms, and roll child-term trips up into parents.
  for (const list of Object.values(terms)) {
    const byId = new Map(list.map((t) => [t.id, t]));
    for (const trip of trips) {
      const own = trip.destinations.concat(trip.activities, trip.tripTypes).filter((t) => byId.get(t.id) === t);
      for (const term of own) {
        for (let t = term; t; t = byId.get(t.parent)) if (!t.trips.includes(trip)) t.trips.push(trip);
      }
    }
  }

  cache = {
    assetOrigin,
    trips,
    terms,
    media,
    pages: readJson('pages.json').map((p) => normalizePage(p, assetOrigin)),
    posts: readJson('posts.json').map((p) => ({ ...normalizePage(p, assetOrigin), image: imageFromMedia(media.get(p.featured_media)) })),
  };
  return cache;
}

export function asset(src, origin = process.env.PUBLIC_ASSET_ORIGIN ?? '') {
  if (!src) return src;
  return src.startsWith('/wp-content/') ? `${origin}${src}` : src;
}
