import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
  decodeEntities,
  rewriteHtml,
  splitLines,
  extractHighlights,
  extractGallery,
  loadSite,
  normalizeTrip,
} from '../../src/lib/content.mjs';

test('decodeEntities turns WordPress title entities into text', () => {
  assert.equal(decodeEntities('Sakkara, Memphis &#038; Dahshur'), 'Sakkara, Memphis & Dahshur');
  assert.equal(decodeEntities('Culture, Colors &amp; Hospitality'), 'Culture, Colors & Hospitality');
  assert.equal(decodeEntities('Jeep 4&#215;4 &#8211; trip'), 'Jeep 4×4 – trip');
});

test('rewriteHtml makes site links relative and points uploads at the images base', () => {
  const html =
    '<a href="https://booknilecruises.net/trip/x/">x</a><img src="https://booknilecruises.net/wp-content/uploads/a.jpg">';
  assert.equal(
    rewriteHtml(html),
    '<a href="/trip/x/">x</a><img src="/images/a.jpg">',
  );
  assert.equal(
    rewriteHtml(html, 'https://booknilecruises.net/wp-content/uploads'),
    '<a href="/trip/x/">x</a><img src="https://booknilecruises.net/wp-content/uploads/a.jpg">',
  );
});

test('rewriteHtml strips Gutenberg block comments', () => {
  assert.equal(rewriteHtml('<!-- wp:paragraph -->\n<p>Hi</p>\n<!-- /wp:paragraph -->', ''), '<p>Hi</p>');
});

test('splitLines turns the includes/excludes text into a clean list', () => {
  assert.deepEqual(splitLines('Meet and assist.\n\nFull board.\n  \nTransfers.'), [
    'Meet and assist.',
    'Full board.',
    'Transfers.',
  ]);
  assert.deepEqual(splitLines(''), []);
});

test('extractHighlights reads the Trip Highlights list from a rendered page', () => {
  const html =
    "<ul class='wpte-trip-highlights' ><li class='trip-highlight'>Edfu: Temple of Horus.</li><li class='trip-highlight'>Luxor &amp; Karnak</li></ul>";
  assert.deepEqual(extractHighlights(html), ['Edfu: Temple of Horus.', 'Luxor & Karnak']);
  assert.deepEqual(extractHighlights('<p>none</p>'), []);
});

test('extractGallery reads the main carousel images once each, in order', () => {
  const slide = (f, w, h) =>
    `<div class="splide__slide wte-gallery-image-clickable" data-full-image="https://booknilecruises.net/wp-content/uploads/${f}"><img src="https://booknilecruises.net/wp-content/uploads/${f}" width="${w}" height="${h}"></div>`;
  const html = `<div class="splide single-trip-main-carousel">${slide('a.jpg', 900, 600)}${slide('b.jpg', 700, 500)}${slide('a.jpg', 900, 600)}</div><div class="splide__slide wte-gallery-image-clickable" data-full-image="https://booknilecruises.net/wp-content/uploads/thumb.jpg"></div>`;
  assert.deepEqual(extractGallery(html), [
    { src: '/images/a.jpg', width: 900, height: 600 },
    { src: '/images/b.jpg', width: 700, height: 500 },
  ]);
});

test('loadSite keeps every exported trip with its URL, price, itinerary and terms', () => {
  const site = loadSite();
  assert.equal(site.trips.length, 102);
  const blue = site.trips.find((t) => t.slug === 'blue-shadow-nile-cruise');
  assert.equal(blue.url, '/trip/blue-shadow-nile-cruise/');
  assert.equal(blue.title, 'Blue Shadow Nile Cruise');
  assert.equal(blue.price, 490);
  assert.deepEqual(blue.duration, { days: 4, nights: 3 });
  assert.ok(blue.itinerary.length >= 4);
  assert.ok(blue.highlights.length === 5, 'highlights come from the HTML snapshot');
  assert.ok(blue.gallery.length >= 5, 'gallery comes from the HTML snapshot');
  assert.ok(blue.includes.includes('Meet and assist service upon arrival and departure.'));
  assert.deepEqual(blue.destinations.map((d) => d.slug).sort(), ['aswan', 'luxor']);
  assert.equal(blue.activities[0].slug, 'deluxe-nile-cruises');
  assert.equal(blue.seo.title, 'Blue Shadow Nile Cruise - Book Nile cruises');
});

test('loadSite gives trips without a price a null price, never 0', () => {
  const site = loadSite();
  const noPrice = site.trips.find((t) => t.slug === 'three-pyramids-dahabiya-nile-cruise');
  assert.equal(noPrice.price, null);
});

test('loadSite exposes terms with their original URLs and trip lists', () => {
  const site = loadSite();
  const luxor = site.terms.destination.find((t) => t.slug === 'luxor');
  assert.equal(luxor.url, '/destinations/luxor/');
  assert.equal(luxor.trips.length, 60);
  const nile = site.terms.activities.find((t) => t.slug === 'nile-cruise');
  assert.equal(nile.url, '/activities/nile-cruise/');
  // The parent term collects the trips of all its children.
  assert.equal(nile.trips.length, 9 + 12 + 9 + 3);
});

test('seoFromHtml reads the live title, description and canonical', async () => {
  const { seoFromHtml } = await import('../../src/lib/content.mjs');
  const html =
    '<title>Luxor &amp; Aswan - Book Nile cruises</title><meta name="description" content="Five days &amp; four nights" /><link rel="canonical" href="https://booknilecruises.net/trip/x/" /><meta property="og:image" content="https://booknilecruises.net/wp-content/uploads/a.jpg" />';
  assert.deepEqual(seoFromHtml(html), {
    title: 'Luxor & Aswan - Book Nile cruises',
    description: 'Five days & four nights',
    canonical: '/trip/x/',
    ogImage: '/images/a.jpg',
  });
});

test('page SEO comes from the live page, not the broken REST value', () => {
  const site = loadSite();
  const deluxe = site.pages.find((p) => p.slug === 'deluxe-nile-cruises');
  assert.equal(deluxe.seo.title, 'Deluxe Nile Cruises - Book Nile cruises');
});

test('trip images carry a card-size rendition in the same uploads folder', () => {
  const site = loadSite();
  const t = site.trips.find((x) => x.slug === 'semiramis-ii-nile-cruise');
  assert.match(t.image.src, /^\/images\/2025\/12\//);
  assert.ok(t.image.card.src.startsWith('/images/2025/12/'));
  assert.ok(t.image.card.width <= 1024);
});

test('content overrides correct a trip FAQ answer without touching the raw export', () => {
  const site = loadSite();
  const t = site.trips.find((x) => x.slug === 'esmeralda-nile-cruise');
  const doctor = t.faqs.find((f) => f.q === 'Is there a doctor available?');
  assert.equal(doctor.a, '<p>Yes, a doctor is available on call 24 hours a day.</p>');
  assert.ok(!JSON.stringify(t.faqs).includes('facilities list mentions'));
});


test('trip srcsets retain the original and only large matching-aspect renditions in width order', () => {
  const image = loadSite().trips.find((t) => t.slug === 'semiramis-ii-nile-cruise').image;
  assert.deepEqual(image.srcset, [
    { src: '/images/2025/12/MS-Semramis-II-nile-cruice2-600x400.jpg', width: 600 },
    { src: '/images/2025/12/MS-Semramis-II-nile-cruice2-768x512.jpg', width: 768 },
    { src: '/images/2025/12/MS-Semramis-II-nile-cruice2-1024x682.jpg', width: 1024 },
    { src: image.src, width: 1280 },
  ]);
});

test('srcsetAttr applies the images base and comma-separated width descriptors', async () => {
  const { srcsetAttr } = await import('../../src/lib/content.mjs');
  assert.equal(typeof srcsetAttr, 'function');
  const image = { srcset: [{ src: '/images/a-600.jpg', width: 600 }, { src: '/images/a.jpg', width: 1200 }] };
  assert.equal(srcsetAttr(image, 'https://booknilecruises.net/wp-content/uploads'), 'https://booknilecruises.net/wp-content/uploads/a-600.jpg 600w, https://booknilecruises.net/wp-content/uploads/a.jpg 1200w');
  assert.equal(srcsetAttr(image), '/images/a-600.jpg 600w, /images/a.jpg 1200w');
  assert.equal(srcsetAttr({}), undefined);
  assert.equal(srcsetAttr({ srcset: [] }), undefined);
});


test('both image sources filter near-aspect sizes and deduplicate widths with the original preferred', () => {
  const sizes = {
    cropped: { file: 'crop.jpg', width: 800, height: 800 },
    small: { file: 'small.jpg', width: 599, height: 300 },
    matching: { file: 'near.jpg', width: 600, height: 305 },
    duplicate: { file: 'duplicate.jpg', width: 600, height: 305 },
    outsideTolerance: { file: 'wide.jpg', width: 900, height: 440 },
    full: { file: 'full-copy.jpg', width: 1200, height: 600 },
  };
  const raw = { id: 1, slug: 'example', link: '/trip/example/', title: { rendered: 'Example' }, featured_media: 1,
    featured_image: { file: '2025/12/original.jpg', width: 1200, height: 600, sizes } };
  const media = { source_url: 'https://booknilecruises.net/wp-content/uploads/2025/12/original.jpg',
    media_details: { width: 1200, height: 600, sizes } };
  for (const records of [new Map(), new Map([[1, media]])]) {
    const trip = normalizeTrip(raw, { media: records, terms: { destination: [], activities: [], trip_types: [] } });
    assert.deepEqual(trip.image.srcset, [
      { src: '/images/2025/12/duplicate.jpg', width: 600 },
      { src: '/images/2025/12/original.jpg', width: 1200 },
    ]);
  }
});

test('asset maps only image paths using the default or explicit base', async () => {
  const { asset, toImagePath } = await import('../../src/lib/content.mjs');
  assert.equal(asset('/images/a.jpg'), '/images/a.jpg');
  assert.equal(asset('/images/a.jpg', 'https://booknilecruises.net/wp-content/uploads/'), 'https://booknilecruises.net/wp-content/uploads/a.jpg');
  assert.equal(asset('/trip/x/'), '/trip/x/');
  assert.equal(asset(undefined), undefined);
  assert.equal(toImagePath('/wp-content/uploads/2025/12/a.jpg'), '/images/2025/12/a.jpg');
  assert.equal(toImagePath('/images/a.jpg'), '/images/a.jpg');
});

test('rewriteHtml maps http and relative uploads including srcsets and CSS', () => {
  const html = `<img src="http://booknilecruises.net/wp-content/uploads/a.jpg" srcset="/wp-content/uploads/b.jpg 600w, /wp-content/uploads/c.jpg 1200w"><div style="background:url('/wp-content/uploads/a.jpg')"></div>`;
  const expected = `<img src="/images/a.jpg" srcset="/images/b.jpg 600w, /images/c.jpg 1200w"><div style="background:url('/images/a.jpg')"></div>`;
  assert.equal(rewriteHtml(html), expected);
});

test('the content layer returns images paths for all public images', () => {
  const site = loadSite();
  const check = (image) => {
    if (!image) return;
    assert.ok(image.src.startsWith('/images/'), image.src);
    if (image.card) check(image.card);
    for (const entry of image.srcset ?? []) check(entry);
  };
  for (const item of [...site.trips, ...site.pages, ...site.posts, ...Object.values(site.terms).flat()]) {
    check(item.image);
    for (const image of item.gallery ?? []) check(image);
    if (item.seo.ogImage) assert.ok(item.seo.ogImage.startsWith('/images/'), item.seo.ogImage);
  }
});

test('the Apache rules redirect old photo URLs, WordPress sitemaps and www/http', async () => {
  const { htaccess } = await import('../../src/lib/redirects.mjs');
  const ht = htaccess();
  assert.match(ht, /RedirectMatch 301 \^\/wp-content\/uploads\/\(\.\*\)\$ \/images\/\$1/);
  assert.match(ht, /sitemap_index\|wp-sitemap/);
  assert.match(ht, /RewriteCond %\{HTTP_HOST\} \^www\\\.\(\.\+\)\$ \[NC\]/);
  assert.match(ht, /X-Forwarded-Proto/);
  assert.doesNotMatch(htaccess(undefined, { noindex: false }), /noindex/);
  assert.match(htaccess(undefined, { noindex: true }), /X-Robots-Tag "noindex, nofollow"/);
});

test('rewriteHtml leaves images hosted on other domains untouched', () => {
  const html = '<img src="https://luxoraswancruises.com/wp-content/uploads/2023/12/a.jpg"><img src="/wp-content/uploads/2025/12/b.jpg">';
  assert.equal(
    rewriteHtml(html, '/images'),
    '<img src="https://luxoraswancruises.com/wp-content/uploads/2023/12/a.jpg"><img src="/images/2025/12/b.jpg">',
  );
});

test('toImagePath maps only our own uploads and leaves other hosts alone', async () => {
  const { toImagePath } = await import('../../src/lib/content.mjs');
  assert.equal(toImagePath('https://booknilecruises.net/wp-content/uploads/2025/12/a.jpg'), '/images/2025/12/a.jpg');
  assert.equal(toImagePath('/wp-content/uploads/2025/12/a.jpg'), '/images/2025/12/a.jpg');
  assert.equal(
    toImagePath('https://luxoraswancruises.com/wp-content/uploads/2023/12/a.jpg'),
    'https://luxoraswancruises.com/wp-content/uploads/2023/12/a.jpg',
  );
});

test('rewriteHtml converts uploads inside CSS url(&quot;...) and link text too', () => {
  const html = '<div style="background:url(&quot;https://booknilecruises.net/wp-content/uploads/2025/12/a.jpg&quot;)"></div><a>https://booknilecruises.net/wp-content/uploads/2025/12/b.jpg</a>';
  const out = rewriteHtml(html, '/images');
  assert.ok(!out.includes('wp-content'), out);
});

test('asset treats an empty images base as the default', async () => {
  const { asset } = await import('../../src/lib/content.mjs');
  assert.equal(asset('/images/2025/12/a.jpg', ''), '/images/2025/12/a.jpg');
});

test('every old WordPress sitemap name redirects, the new sitemap does not', async () => {
  const { htaccess } = await import('../../src/lib/redirects.mjs');
  const rule = htaccess().match(/RedirectMatch 301 (\S+) \/sitemap\.xml/)[1];
  const re = new RegExp(rule);
  const { readFileSync } = await import('node:fs');
  const names = [...new Set(JSON.parse(readFileSync('../data/wp-export/sitemap-urls.json', 'utf8')).map((u) => `/${u.sitemap}`))];
  for (const n of [...names, '/sitemap_index.xml', '/wp-sitemap.xml', '/wp-sitemap-posts-page-1.xml', '/post-sitemap2.xml']) {
    assert.ok(re.test(n), `${n} not redirected`);
  }
  assert.ok(!re.test('/sitemap.xml'));
});

test('the Apache rules block PHP, WordPress leftovers and archives', async () => {
  const { htaccess } = await import('../../src/lib/redirects.mjs');
  const ht = htaccess();
  assert.match(ht, /<FilesMatch "\\\.\(php\[0-9\]\?\|phtml\|phar\)\$">\s*Require all denied/);
  const rule = new RegExp(ht.match(/RedirectMatch 404 (\S+)/)[1]);
  for (const p of ['/old/', '/old/wp-login.php', '/wordpress-old-20261006/x', '/wp-includes/version.php', '/wp-content/plugins/x.php']) assert.ok(rule.test(p), p);
  for (const p of ['/', '/images/2025/12/a.jpg', '/wp-content/uploads/2025/12/a.jpg', '/trip/old-cairo/', '/older/']) assert.ok(!rule.test(p), p);
  assert.match(ht, /readme\\\.html\|license\\\.txt\|wp-config/);
});

test('panel redirects are exact-path rules that come first and override built-ins', async () => {
  const { htaccess: ht, exactRule } = await import('../../src/lib/redirects.mjs');
  assert.equal(exactRule('/old.trip/', '/trip/new/'), 'RedirectMatch 301 ^/old\\.trip/?$ /trip/new/');
  assert.equal(exactRule('/a', '/a/b/'), 'RedirectMatch 301 ^/a$ /a/b/');
  const out = ht({ '/cart/': '/contact-us/' }, { exact: { '/cart/': '/trip/', '/x/': '/y/' } });
  assert.ok(out.indexOf('^/cart/?$ /trip/') < out.indexOf('^/x/?$'));
  assert.doesNotMatch(out, /Redirect 301 \/cart\/ /, 'built-in rule for the same path is replaced');
  // An exact rule only matches its own path, so /a/ -> /a/b/ cannot loop.
  const re = new RegExp(exactRule('/a/', '/a/b/').split(' ')[2]);
  assert.ok(re.test('/a/') && re.test('/a') && !re.test('/a/b/'));
});
