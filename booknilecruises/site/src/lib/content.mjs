// Content layer: turns the raw WordPress export (data/wp-export) and the
// rendered-page snapshots (data/html-snapshot) into the objects the site
// templates render. The raw export is never modified; everything here is a
// pure read so the build can be rerun after a fresh export.

import { readFileSync, existsSync } from 'node:fs';
import { gunzipSync } from 'node:zlib';
import path from 'node:path';
import { readExport } from './export-file.mjs';
import { siteFromExport } from './export.mjs';

// Resolved from the working directory (the site/ folder for both `astro build`
// and `npm test`), because Astro bundles this module into dist/ at build time.
const DATA = path.resolve(process.env.BNC_DATA_DIR ?? path.join(process.cwd(), '..', 'data'));
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

// Keep image paths independent of the preview or production image base.
// Only our own uploads move; an image hosted on another site keeps its URL.
export function toImagePath(pathOrUrl) {
  const url = new URL(pathOrUrl, SITE_ORIGIN);
  if (url.host !== new URL(SITE_ORIGIN).host) return url.href;
  return url.pathname.replace(/^\/wp-content\/uploads\//, '/images/');
}

// Site links become root-relative; image URLs use the configured base.
export function rewriteHtml(html = '', imagesBase = envImagesBase()) {
  return html
    .replace(/<!--\s*\/?wp:[\s\S]*?-->\n?/g, '')
    .replace(/https?:\/\/booknilecruises\.net(?=["']|\/)/g, '')
    // Our own uploads are root-relative by now; a path right after another
    // host name (e.g. https://other-site.com/wp-content/...) is left alone.
    .replace(/(?<=^|["'\s(,=;>])\/wp-content\/uploads\/[^\s"'<>)&]+/g,
      (url) => asset(toImagePath(url), imagesBase))
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
    const src = toImagePath(m[1]);
    if (seen.has(src)) continue;
    seen.add(src);
    out.push({ src, width: Number(m[2]), height: Number(m[3]) });
  }
  return out;
}

function readJson(name) {
  return JSON.parse(readFileSync(path.join(DATA, 'wp-export', name), 'utf8'));
}

function readOverrides() {
  const file = path.join(DATA, 'overrides.json');
  return existsSync(file) ? JSON.parse(readFileSync(file, 'utf8')) : {};
}

// FAQ answers corrected by the business, keyed by trip slug and question text.
function applyTripOverrides(trip, overrides) {
  const faqFixes = overrides?.faqs ?? {};
  for (const faq of trip.faqs) if (faqFixes[faq.q]) faq.a = faqFixes[faq.q];
  return trip;
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
    ogImage: og ? toImagePath(og) : null,
  };
}

function seoOf(item, snapshot = '') {
  const h = item.aioseo_head_json || {};
  const live = seoFromHtml(snapshot);
  return {
    title: live.title ?? decodeEntities(h.title || ''),
    description: live.description ?? decodeEntities(h.description || ''),
    canonical: live.canonical ?? (h.canonical_url ? pathOf(h.canonical_url) : null),
    ogImage: live.ogImage ?? (h.og?.['og:image'] ? toImagePath(h.og['og:image']) : null),
    noindex: false,
  };
}

// A ~768px rendition for cards, falling back to the original file.
const CARD_SIZES = ['medium_large', 'large', 'tourm_424X498', 'trip-thumb-size'];

function cardFrom(dir, sizes = {}) {
  const key = CARD_SIZES.find((k) => sizes[k]?.file);
  return key ? { src: toImagePath(`${dir}/${sizes[key].file}`), width: sizes[key].width, height: sizes[key].height } : null;
}

function srcsetFrom(src, width, height, sizes = {}) {
  const entries = new Map();
  const ratio = width / height;
  for (const size of Object.values(sizes)) {
    if (size.file && size.width >= 600 && size.height > 0 &&
        Math.abs((size.width / size.height) / ratio - 1) <= 0.02) {
      entries.set(size.width, { src: toImagePath(`${path.posix.dirname(src)}/${size.file}`), width: size.width });
    }
  }
  if (width > 0) entries.set(width, { src, width });
  return [...entries.values()].sort((a, b) => a.width - b.width);
}

export function imageFromMedia(media) {
  if (!media) return null;
  const src = toImagePath(media.source_url);
  return {
    src,
    width: media.media_details?.width ?? null,
    height: media.media_details?.height ?? null,
    alt: decodeEntities(media.alt_text || ''),
    srcset: srcsetFrom(src, media.media_details?.width, media.media_details?.height, media.media_details?.sizes),
    card: cardFrom(path.posix.dirname(src), media.media_details?.sizes),
  };
}

function imageFromTripField(fi) {
  if (!fi?.file) return null;
  const src = `/images/${fi.file}`;
  return { src, width: fi.width ?? null, height: fi.height ?? null, alt: '', srcset: srcsetFrom(src, fi.width, fi.height, fi.sizes), card: cardFrom(path.posix.dirname(src), fi.sizes) };
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
    overviewHtml: rewriteHtml(overview, ctx.imagesBase),
    highlights: extractHighlights(snapshot),
    itinerary: (raw.itineraries || []).map((d) => ({
      title: decodeEntities(d.title || ''),
      html: rewriteHtml(d.content || '', ctx.imagesBase),
    })),
    includes: splitLines(raw.cost_includes),
    excludes: splitLines(raw.cost_excludes),
    faqs: (raw.faqs || []).map((f) => ({
      q: decodeEntities(f.question ?? f.title ?? ''),
      a: rewriteHtml(f.answer ?? f.content ?? '', ctx.imagesBase),
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

function normalizePage(raw, imagesBase) {
  return {
    id: raw.id,
    slug: raw.slug,
    url: pathOf(raw.link),
    title: decodeEntities(raw.title.rendered),
    html: rewriteHtml(raw.content.rendered, imagesBase),
    text: stripTags(raw.content.rendered),
    featuredMedia: raw.featured_media || null,
    date: raw.date,
    modified: raw.modified,
    seo: seoOf(raw, readSnapshot(raw.link)),
  };
}

// Astro fills import.meta.env from .env files; plain Node (tests) only has process.env.
function envImagesBase() {
  if (import.meta.env?.PUBLIC_ASSET_ORIGIN || process.env.PUBLIC_ASSET_ORIGIN) {
    throw new Error('PUBLIC_ASSET_ORIGIN was replaced by PUBLIC_IMAGES_BASE (e.g. https://booknilecruises.net/wp-content/uploads)');
  }
  return import.meta.env?.PUBLIC_IMAGES_BASE || process.env.PUBLIC_IMAGES_BASE || '/images';
}

let cache;

export function loadSite({ imagesBase = envImagesBase() } = {}) {
  if (cache && cache.imagesBase === imagesBase) return cache;
  const exported = readExport();
  if (exported) {
    // Built from the admin panel's data; the static pages' text still comes from the WordPress export.
    const pages = readJson('pages.json').map((p) => normalizePage(p, imagesBase));
    cache = { imagesBase, ...siteFromExport(exported, { imagesBase, pages }) };
    return cache;
  }
  const mediaList = readJson('media.json');
  const media = new Map(mediaList.map((m) => [m.id, m]));
  const terms = {
    destination: buildTerms(readJson('destination.json'), 'destination'),
    activities: buildTerms(readJson('activities.json'), 'activities'),
    trip_types: buildTerms(readJson('trip_types.json'), 'trip_types'),
  };
  const overrides = readOverrides();
  const trips = readJson('trips.json')
    .map((raw) => normalizeTrip(raw, { media, terms, imagesBase, snapshot: readSnapshot(raw.link) }))
    .map((trip) => applyTripOverrides(trip, overrides.trips?.[trip.slug]))
    .sort((a, b) => a.title.localeCompare(b.title));

  attachTripsToTerms(trips, terms);

  cache = {
    imagesBase,
    trips,
    terms,
    media,
    pages: readJson('pages.json').map((p) => normalizePage(p, imagesBase)),
    posts: readJson('posts.json').map((p) => ({ ...normalizePage(p, imagesBase), image: imageFromMedia(media.get(p.featured_media)) })),
    settings: {},
    seoOverrides: new Map(),
    redirects: {},
    noindexPaths: new Set(),
  };
  return cache;
}

// Attach trips to their terms, and roll child-term trips up into parents.
export function attachTripsToTerms(trips, terms) {
  for (const list of Object.values(terms)) {
    const byId = new Map(list.map((t) => [t.id, t]));
    for (const trip of trips) {
      const own = trip.destinations.concat(trip.activities, trip.tripTypes).filter((t) => byId.get(t.id) === t);
      for (const term of own) {
        for (let t = term; t; t = byId.get(t.parent)) if (!t.trips.includes(trip)) t.trips.push(trip);
      }
    }
  }
}

export function asset(src, base = envImagesBase()) {
  base = base || '/images';
  if (!src) return src;
  return src.startsWith('/images/') ? `${base.replace(/\/+$/, '')}/${src.slice('/images/'.length)}` : src;
}

export function srcsetAttr(img, base = envImagesBase()) {
  return img?.srcset?.length
    ? img.srcset.map(({ src, width }) => `${asset(src, base)} ${width}w`).join(', ')
    : undefined;
}
