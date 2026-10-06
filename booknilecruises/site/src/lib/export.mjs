// The contract between the admin panel and the site build.
//
// The panel (PHP + MySQL) publishes its content as one JSON document, the
// "export". siteFromExport() turns it into the same objects loadSite() builds
// from the WordPress data, so every template works unchanged. exportFromSite()
// goes the other way and produces the panel's first import from today's data.
//
// Shape (version 1), all keys required:
//   settings       { google_site_verification, bing_site_verification, ga4_id, indexnow_key,
//                    email, whatsapp, phone_display, phone_alt, address } (each optional)
//   media          [{ id, path: "/images/…", width, height, alt, mime, sizes: { key: { file, width, height, mime_type } } }]
//   terms          [{ id, taxonomy: destination|activities|trip_types, slug, name, parent_id, url,
//                     description, seo_title, seo_description }]
//   trips          [{ id, slug, title, status: draft|published, excerpt, code, price, sale_price, currency,
//                     duration_days, duration_nights, min_pax, max_pax, overview_html, highlights[],
//                     itinerary[{ title, html }], includes[], excludes[], faqs[{ q, a }], image_id,
//                     gallery[media id], featured, seo_title, seo_description, noindex, term_ids[], updated_at }]
//   posts          [{ id, slug, url, title, status, excerpt, content_html, image_id, seo_title,
//                     seo_description, noindex, published_at, updated_at }]
//   seo_overrides  [{ path, title, description, noindex }]
//   redirects      [{ from, to }]
// HTML fields hold image paths as /images/…; the panel sanitises HTML when it is saved.

import { attachTripsToTerms, imageFromMedia, stripTags, toImagePath, decodeEntities, asset } from './content.mjs';

export const EXPORT_VERSION = 1;
const TAXONOMIES = ['destination', 'activities', 'trip_types'];

const SLUG = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;
const SITE_PATH = /^\/(?:[A-Za-z0-9._~%-]+\/)*[A-Za-z0-9._~%-]*$/;
const REDIRECT_TARGET = /^(?:\/(?:[A-Za-z0-9._~%-]+\/)*[A-Za-z0-9._~%-]*|https:\/\/[A-Za-z0-9.-]+(?:\/[A-Za-z0-9._~%\/-]*)?)(?:[?#][A-Za-z0-9._~%=&\/-]*)?$/;
const IMAGE_PATH = /^\/images\/(?:[A-Za-z0-9_][A-Za-z0-9._-]*\/)*[A-Za-z0-9_][A-Za-z0-9._-]*$/;
const SETTING_RULES = {
  google_site_verification: [/^[A-Za-z0-9_-]{0,100}$/, 'verification'],
  bing_site_verification: [/^[A-Za-z0-9_-]{0,100}$/, 'verification'],
  ga4_id: [/^(?:G-[A-Z0-9]{4,20})?$/, 'ga4_id'],
  indexnow_key: [/^(?:[A-Za-z0-9-]{8,128})?$/, 'indexnow_key'],
  email: [/^(?:[^\s@<>"]+@[^\s@<>"]+\.[A-Za-z]{2,})?$/, 'email'],
  whatsapp: [/^[0-9]{0,20}$/, 'whatsapp'],
  phone_display: [/^[+0-9 ()-]{0,30}$/, 'phone'],
  phone_alt: [/^[+0-9 ()-]{0,30}$/, 'phone'],
  address: [/^[^<>]{0,200}$/, 'address'],
};

/** Throws with a readable message when the export can't be built safely. */
export function validateExport(exp) {
  const fail = (msg) => { throw new Error(`Invalid export: ${msg}`); };
  if (exp?.version !== EXPORT_VERSION) fail(`version ${exp?.version} (expected ${EXPORT_VERSION})`);
  for (const key of ['settings', 'media', 'terms', 'trips', 'posts', 'seo_overrides', 'redirects']) {
    if (exp[key] == null) fail(`missing ${key}`);
  }
  for (const [key, value] of Object.entries(exp.settings)) {
    const rule = SETTING_RULES[key];
    if (rule && value != null && !rule[0].test(String(value))) fail(`setting ${key} has an invalid ${rule[1]} value`);
  }
  const media = new Set();
  for (const m of exp.media) {
    if (!IMAGE_PATH.test(m.path) || m.path.includes('..')) fail(`media ${m.id} path ${JSON.stringify(m.path)}`);
    for (const size of Object.values(m.sizes ?? {})) {
      if (!/^[A-Za-z0-9_][A-Za-z0-9._-]*$/.test(size.file)) fail(`media ${m.id} size file ${JSON.stringify(size.file)}`);
    }
    media.add(m.id);
  }
  const needMedia = (id, what) => { if (id != null && !media.has(id)) fail(`${what} uses missing media ${id}`); };
  const terms = new Set();
  for (const t of exp.terms) {
    if (!TAXONOMIES.includes(t.taxonomy)) fail(`term ${t.id} taxonomy ${t.taxonomy}`);
    if (!SLUG.test(t.slug)) fail(`term slug ${JSON.stringify(t.slug)}`);
    if (!SITE_PATH.test(t.url)) fail(`term url ${JSON.stringify(t.url)}`);
    terms.add(t.id);
  }
  for (const t of exp.trips) {
    if (!SLUG.test(t.slug)) fail(`trip slug ${JSON.stringify(t.slug)}`);
    needMedia(t.image_id, `trip ${t.slug}`);
    for (const id of t.gallery) needMedia(id, `trip ${t.slug} gallery`);
    for (const id of t.term_ids) if (!terms.has(id)) fail(`trip ${t.slug} uses missing term ${id}`);
  }
  for (const p of exp.posts) {
    if (!SLUG.test(p.slug)) fail(`post slug ${JSON.stringify(p.slug)}`);
    if (!SITE_PATH.test(p.url)) fail(`post url ${JSON.stringify(p.url)}`);
    needMedia(p.image_id, `post ${p.slug}`);
  }
  for (const o of exp.seo_overrides) if (!SITE_PATH.test(o.path)) fail(`SEO override path ${JSON.stringify(o.path)}`);
  for (const r of exp.redirects) {
    // Apache's Redirect matches prefixes: "/" or "/admin" would capture whole sections of the site.
    const reserved = r.from === '/' || /^\/(?:admin|api|images)(?:\/|$)/.test(r.from);
    if (!SITE_PATH.test(r.from) || !REDIRECT_TARGET.test(r.to) || reserved || r.from === r.to) fail(`redirect ${JSON.stringify(r)}`);
  }
  return exp;
}

/** Site objects (same shape as loadSite) from a panel export. */
export function siteFromExport(exp, { imagesBase = '/images', pages = [] } = {}) {
  validateExport(exp);
  const mediaRows = new Map(exp.media.map((m) => [m.id, m]));
  // Same image objects as for WordPress media, so srcset and card sizes are identical.
  const image = (id) => {
    const m = mediaRows.get(id);
    if (!m) return null;
    return imageFromMedia({ source_url: m.path, alt_text: m.alt ?? '', media_details: { width: m.width, height: m.height, sizes: m.sizes ?? {} } });
  };
  const html = (value) => rewriteImages(value ?? '', imagesBase);

  const terms = Object.fromEntries(TAXONOMIES.map((tax) => [tax, []]));
  for (const t of exp.terms) {
    terms[t.taxonomy].push({
      id: t.id,
      taxonomy: t.taxonomy,
      slug: t.slug,
      name: t.name,
      parent: t.parent_id ?? 0,
      url: t.url,
      description: t.description ?? '',
      seo: { title: t.seo_title ?? '', description: t.seo_description ?? '', canonical: t.url, ogImage: null, noindex: false },
      trips: [],
    });
  }
  const termById = new Map(Object.values(terms).flat().map((t) => [t.id, t]));
  const termsOf = (ids, tax) => ids.map((id) => termById.get(id)).filter((t) => t?.taxonomy === tax);

  const trips = exp.trips
    .filter((t) => t.status === 'published')
    .map((t) => {
      const url = `/trip/${t.slug}/`;
      const cover = image(t.image_id);
      const gallery = t.gallery.map(image).filter(Boolean);
      return {
        id: t.id,
        slug: t.slug,
        url,
        title: t.title,
        excerpt: t.excerpt ?? '',
        code: t.code ?? '',
        price: t.price ?? null,
        salePrice: t.sale_price ?? null,
        currency: t.currency || 'USD',
        duration: { days: t.duration_days ?? null, nights: t.duration_nights ?? null },
        minPax: t.min_pax ?? null,
        maxPax: t.max_pax ?? null,
        overviewHtml: html(t.overview_html),
        highlights: t.highlights ?? [],
        itinerary: (t.itinerary ?? []).map((d) => ({ title: d.title ?? '', html: html(d.html) })),
        includes: t.includes ?? [],
        excludes: t.excludes ?? [],
        faqs: (t.faqs ?? []).map((f) => ({ q: f.q, a: html(f.a) })),
        image: cover,
        gallery: gallery.length ? gallery : cover ? [cover] : [],
        destinations: termsOf(t.term_ids, 'destination'),
        activities: termsOf(t.term_ids, 'activities'),
        tripTypes: termsOf(t.term_ids, 'trip_types'),
        featured: Boolean(t.featured),
        modified: t.updated_at,
        seo: { title: t.seo_title ?? '', description: t.seo_description ?? '', canonical: url, ogImage: null, noindex: Boolean(t.noindex) },
      };
    })
    .sort((a, b) => a.title.localeCompare(b.title));
  attachTripsToTerms(trips, terms);

  const posts = exp.posts
    .filter((p) => p.status === 'published')
    .map((p) => {
      const body = html(p.content_html);
      return {
        id: p.id,
        slug: p.slug,
        url: p.url,
        title: p.title,
        html: body,
        text: stripTags(body),
        featuredMedia: p.image_id ?? null,
        date: p.published_at,
        modified: p.updated_at,
        seo: { title: p.seo_title ?? '', description: p.seo_description ?? '', canonical: p.url, ogImage: null, noindex: Boolean(p.noindex) },
        image: image(p.image_id),
      };
    });

  const seoOverrides = new Map(exp.seo_overrides.map((o) => [o.path, { title: o.title ?? null, description: o.description ?? null, noindex: Boolean(o.noindex) }]));
  const noindexPaths = new Set([
    ...trips.filter((t) => t.seo.noindex).map((t) => t.url),
    ...posts.filter((p) => p.seo.noindex).map((p) => p.url),
    ...[...seoOverrides].filter(([, o]) => o.noindex).map(([p]) => p),
  ]);

  return {
    trips,
    terms,
    media: new Map(exp.media.map((m) => [m.id, { id: m.id, source_url: m.path, alt_text: m.alt ?? '', media_details: { width: m.width, height: m.height, sizes: m.sizes ?? {} } }])),
    pages,
    posts,
    settings: { ...exp.settings },
    seoOverrides,
    redirects: Object.fromEntries(exp.redirects.map((r) => [r.from, r.to])),
    noindexPaths,
  };
}

// HTML in the export uses /images/… paths; point them at the configured base.
function rewriteImages(html, imagesBase) {
  return html.replace(/(?<=^|["'\s(,=;>])\/images\/[^\s"'<>)&]+/g, (src) => asset(src, imagesBase));
}

/** The panel's first import: today's site data in export form. */
export function exportFromSite(site) {
  const media = [];
  const idByPath = new Map();
  for (const m of site.media.values()) {
    const p = toImagePath(m.source_url);
    if (!p.startsWith('/images/')) continue; // hosted elsewhere
    const sizes = {};
    for (const [key, s] of Object.entries(m.media_details?.sizes ?? {})) {
      if (s.file) sizes[key] = { file: s.file, width: s.width, height: s.height, mime_type: s.mime_type ?? null };
    }
    media.push({
      id: m.id,
      path: p,
      width: m.media_details?.width ?? null,
      height: m.media_details?.height ?? null,
      alt: decodeEntities(m.alt_text || ''),
      mime: m.mime_type ?? null,
      sizes,
    });
    idByPath.set(p, m.id);
  }
  const mediaId = (img, what) => {
    if (!img) return null;
    const id = idByPath.get(img.src);
    if (id === undefined) throw new Error(`${what}: image ${img.src} is not in the media library`);
    return id;
  };

  const terms = Object.values(site.terms).flat().map((t) => ({
    id: t.id,
    taxonomy: t.taxonomy,
    slug: t.slug,
    name: t.name,
    parent_id: t.parent || null,
    url: t.url,
    description: t.description,
    seo_title: t.seo.title,
    seo_description: t.seo.description,
  }));

  const trips = site.trips.map((t) => ({
    id: t.id,
    slug: t.slug,
    title: t.title,
    status: 'published',
    excerpt: t.excerpt,
    code: t.code ?? '',
    price: t.price,
    sale_price: t.salePrice,
    currency: t.currency,
    duration_days: t.duration.days,
    duration_nights: t.duration.nights,
    min_pax: t.minPax,
    max_pax: t.maxPax,
    overview_html: t.overviewHtml,
    highlights: t.highlights,
    itinerary: t.itinerary,
    includes: t.includes,
    excludes: t.excludes,
    faqs: t.faqs,
    image_id: mediaId(t.image, t.slug),
    gallery: t.gallery.map((img) => mediaId(img, `${t.slug} gallery`)),
    featured: t.featured,
    seo_title: t.seo.title,
    seo_description: t.seo.description,
    noindex: false,
    term_ids: [...t.destinations, ...t.activities, ...t.tripTypes].map((x) => x.id),
    updated_at: t.modified,
  }));

  const posts = site.posts.map((p) => ({
    id: p.id,
    slug: p.slug,
    url: p.url,
    title: p.title,
    status: 'published',
    excerpt: '',
    content_html: p.html,
    image_id: mediaId(p.image, p.slug),
    seo_title: p.seo.title,
    seo_description: p.seo.description,
    noindex: false,
    published_at: p.date,
    updated_at: p.modified,
  }));

  return validateExport({
    version: EXPORT_VERSION,
    generated_at: new Date().toISOString(),
    settings: {},
    media,
    terms,
    trips,
    posts,
    seo_overrides: [],
    redirects: [],
  });
}
