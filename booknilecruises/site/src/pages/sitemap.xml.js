import { loadSite } from '../lib/content.mjs';
import { CATEGORY_PAGES, SITE } from '../lib/site.mjs';

// One sitemap with every indexable URL the site builds.
export function GET() {
  const site = loadSite();
  const urls = [
    '/', '/trip/', '/destinations/', '/activities/', '/trip-types/', '/about-us/', '/contact-us/', '/faq/',
    '/transfers/', '/terms-and-conditions/', '/blog/',
    ...Object.keys(CATEGORY_PAGES).map((s) => `/${s}/`),
    ...site.trips.map((t) => t.url),
    ...site.posts.map((p) => p.url),
    ...Object.values(site.terms).flat().filter((t) => t.trips.length).map((t) => t.url),
  ];
  const lastmod = new Map(site.trips.map((t) => [t.url, t.modified]));
  const body = [...new Set(urls)]
    .filter((u) => !site.noindexPaths.has(u))
    .map((u) => `  <url><loc>${SITE.origin}${u}</loc>${lastmod.has(u) ? `<lastmod>${lastmod.get(u).slice(0, 10)}</lastmod>` : ''}</url>`)
    .join('\n');
  return new Response(`<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${body}\n</urlset>\n`, {
    headers: { 'Content-Type': 'application/xml' },
  });
}
