import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
  decodeEntities,
  rewriteHtml,
  splitLines,
  extractHighlights,
  extractGallery,
  loadSite,
} from '../../src/lib/content.mjs';

test('decodeEntities turns WordPress title entities into text', () => {
  assert.equal(decodeEntities('Sakkara, Memphis &#038; Dahshur'), 'Sakkara, Memphis & Dahshur');
  assert.equal(decodeEntities('Culture, Colors &amp; Hospitality'), 'Culture, Colors & Hospitality');
  assert.equal(decodeEntities('Jeep 4&#215;4 &#8211; trip'), 'Jeep 4×4 – trip');
});

test('rewriteHtml makes site links relative and points uploads at the asset origin', () => {
  const html =
    '<a href="https://booknilecruises.net/trip/x/">x</a><img src="https://booknilecruises.net/wp-content/uploads/a.jpg">';
  assert.equal(
    rewriteHtml(html, ''),
    '<a href="/trip/x/">x</a><img src="/wp-content/uploads/a.jpg">',
  );
  assert.equal(
    rewriteHtml(html, 'https://booknilecruises.net'),
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
    { src: '/wp-content/uploads/a.jpg', width: 900, height: 600 },
    { src: '/wp-content/uploads/b.jpg', width: 700, height: 500 },
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
    ogImage: '/wp-content/uploads/a.jpg',
  });
});

test('page SEO comes from the live page, not the broken REST value', () => {
  const site = loadSite();
  const deluxe = site.pages.find((p) => p.slug === 'deluxe-nile-cruises');
  assert.equal(deluxe.seo.title, 'Deluxe Nile Cruises - Book Nile cruises');
});
