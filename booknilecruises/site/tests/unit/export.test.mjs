import { test } from 'node:test';
import assert from 'node:assert/strict';
import { loadSite } from '../../src/lib/content.mjs';
import { exportFromSite, siteFromExport, validateExport, EXPORT_VERSION } from '../../src/lib/export.mjs';

// Comparable view of the site: cycles (term.trips <-> trip.destinations) replaced by ids,
// gallery photos reduced to path and shape (WordPress recorded the displayed size,
// the media library has the original; width/height attributes only set the aspect ratio).
function plain(site) {
  const json = (v) => JSON.parse(JSON.stringify(v));
  return {
    trips: site.trips.map((t) => json({
      ...t,
      destinations: t.destinations.map((x) => x.id),
      activities: t.activities.map((x) => x.id),
      tripTypes: t.tripTypes.map((x) => x.id),
      gallery: t.gallery.map(({ src, width, height }) => ({ src, ratio: Math.round((width / height) * 50) / 50 })),
    })),
    terms: Object.fromEntries(Object.entries(site.terms).map(([k, list]) => [k, list.map((t) => json({ ...t, trips: t.trips.map((x) => x.slug) }))])),
    posts: json(site.posts),
  };
}

const site = loadSite({ imagesBase: '/images' });
const exported = exportFromSite(site);

test('export has the documented top-level shape', () => {
  assert.equal(exported.version, EXPORT_VERSION);
  for (const key of ['settings', 'media', 'terms', 'trips', 'posts', 'seo_overrides', 'redirects']) assert.ok(key in exported, key);
  assert.equal(exported.trips.length, site.trips.length);
  assert.ok(exported.media.length > 0);
  const trip = exported.trips.find((t) => t.slug === 'blue-shadow-nile-cruise');
  assert.equal(typeof trip.image_id, 'number');
  assert.ok(trip.gallery.every((id) => typeof id === 'number'));
  assert.ok(trip.term_ids.length > 0);
});

test('every image a trip or post uses is in the media list', () => {
  const ids = new Set(exported.media.map((m) => m.id));
  for (const t of exported.trips) {
    if (t.image_id !== null) assert.ok(ids.has(t.image_id), t.slug);
    for (const id of t.gallery) assert.ok(ids.has(id), `${t.slug} gallery ${id}`);
  }
  for (const p of exported.posts) if (p.image_id !== null) assert.ok(ids.has(p.image_id), p.slug);
});

test('the site rebuilt from the export matches the site built from WordPress data', () => {
  const rebuilt = siteFromExport(JSON.parse(JSON.stringify(exported)), { imagesBase: '/images', pages: site.pages });
  const a = plain(site);
  const b = plain(rebuilt);
  // og:image: WordPress used the site logo for every trip; the export lets trips use their own photo.
  for (const t of a.trips) t.seo.ogImage = null;
  for (const p of a.posts) p.seo.ogImage = null; // posts already use their own photo
  // A trip without a cover photo used its first gallery photo without responsive sizes; now it gets them.
  b.trips.forEach((t, i) => {
    if (a.trips[i].image && !a.trips[i].image.srcset) {
      assert.equal(t.image.src, a.trips[i].image.src);
      t.image = a.trips[i].image;
    }
  });
  assert.deepEqual(b.trips, a.trips);
  assert.deepEqual(b.terms, a.terms);
  assert.deepEqual(b.posts, a.posts);
});

test('drafts and noindex items are handled', () => {
  const copy = structuredClone(exported);
  copy.trips[0].status = 'draft';
  copy.trips[1].noindex = true;
  const rebuilt = siteFromExport(copy, { imagesBase: '/images', pages: [] });
  assert.equal(rebuilt.trips.length, exported.trips.length - 1);
  assert.ok(!rebuilt.trips.some((t) => t.slug === copy.trips[0].slug));
  assert.equal(rebuilt.trips.find((t) => t.slug === copy.trips[1].slug).seo.noindex, true);
  assert.ok(rebuilt.noindexPaths.has(`/trip/${copy.trips[1].slug}/`));
});

test('settings, SEO overrides and redirects come through', () => {
  const copy = structuredClone(exported);
  copy.settings = { google_site_verification: 'abc-123_X', ga4_id: 'G-ABC123', email: 'sales@example.com' };
  copy.seo_overrides = [{ path: '/about-us/', title: 'About', description: 'Desc', noindex: true }];
  copy.redirects = [{ from: '/old-trip/', to: '/trip/new-trip/' }];
  const rebuilt = siteFromExport(copy, { imagesBase: '/images', pages: [] });
  assert.equal(rebuilt.settings.google_site_verification, 'abc-123_X');
  assert.deepEqual(rebuilt.seoOverrides.get('/about-us/'), { title: 'About', description: 'Desc', noindex: true });
  assert.ok(rebuilt.noindexPaths.has('/about-us/'));
  assert.deepEqual(rebuilt.redirects, { '/old-trip/': '/trip/new-trip/' });
});

test('validateExport rejects values that could break the build or the server rules', () => {
  const bad = (mutate) => {
    const copy = structuredClone(exported);
    mutate(copy);
    return () => validateExport(copy);
  };
  assert.doesNotThrow(() => validateExport(exported));
  assert.throws(bad((c) => { c.version = 99; }), /version/);
  assert.throws(bad((c) => { c.redirects = [{ from: '/a/\nRewriteRule .* evil', to: '/' }]; }), /redirect/);
  assert.throws(bad((c) => { c.redirects = [{ from: '/a/', to: 'javascript:alert(1)' }]; }), /redirect/);
  assert.throws(bad((c) => { c.redirects = [{ from: 'no-slash', to: '/' }]; }), /redirect/);
  for (const from of ['/', '/admin/', '/api', '/images/2025/']) {
    assert.throws(bad((c) => { c.redirects = [{ from, to: '/x/' }]; }), /redirect/, from);
  }
  assert.throws(bad((c) => { c.redirects = [{ from: '/a/', to: '/a/' }]; }), /redirect/);
  assert.throws(bad((c) => { c.trips[0].slug = '../etc'; }), /slug/);
  assert.throws(bad((c) => { c.settings = { ga4_id: 'G-1"><script>' }; }), /ga4_id/);
  assert.throws(bad((c) => { c.settings = { google_site_verification: 'a" onload="x' }; }), /verification/);
  assert.throws(bad((c) => { c.trips[0].image_id = 999999999; }), /media/);
});
