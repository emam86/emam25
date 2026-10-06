// node tests/visual/capture.mjs PHP_BASE [REFERENCE_DIST] [OUTPUT_DIR]
// Captures seven page families at desktop and mobile, with browser error and overflow reports.
import { execFileSync, execSync } from 'node:child_process';
import { createRequire } from 'node:module';
import { createServer } from 'node:http';
import { readFile, stat, mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
const require = createRequire(import.meta.url);
let playwright;
const globalRoot = execSync('npm root -g', { encoding: 'utf8' }).trim();
try { playwright = require(require.resolve('playwright', { paths: [globalRoot] })); }
catch { playwright = require(require.resolve('playwright', { paths: ['/opt/node-tools/node_modules'] })); }
const here = path.dirname(fileURLToPath(import.meta.url));
const base = process.argv[2];
if (!base) throw new Error('Usage: node tests/visual/capture.mjs PHP_BASE [REFERENCE_DIST] [OUTPUT_DIR]');
const dist = path.resolve(process.argv[3] || path.join(here, '../tmp/reference/dist'));
const output = path.resolve(process.argv[4] || path.join(here, '../tmp/visual'));
await mkdir(output, { recursive: true });
const mime = { '.html': 'text/html', '.css': 'text/css', '.js': 'text/javascript', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.webp': 'image/webp', '.png': 'image/png', '.svg': 'image/svg+xml', '.woff2': 'font/woff2' };
const server = createServer(async (req, res) => {
  try {
    const url = new URL(req.url, 'http://localhost');
    let file = path.resolve(dist, '.' + decodeURIComponent(url.pathname));
    if (!file.startsWith(dist + path.sep) && file !== dist) { res.writeHead(403); res.end(); return; }
    if ((await stat(file)).isDirectory()) file = path.join(file, 'index.html');
    const bytes = await readFile(file);
    res.writeHead(200, { 'Content-Type': mime[path.extname(file)] || 'application/octet-stream' }); res.end(bytes);
  } catch { res.writeHead(404); res.end('Not found'); }
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const reference = `http://127.0.0.1:${server.address().port}`;
const urls = ['/', '/trip/', '/trip/blue-shadow-nile-cruise/', '/nile-cruise/', '/destinations/luxor/', '/contact-us/', '/transfers/'];
const browsers = await playwright.chromium.launch({ headless: true, args: ['--no-sandbox'] });
const report = [];
const assets = new Map();
const imageCache = path.join(here, '../tmp/visual-images');
async function sourceImage(pathname, context) {
  const local = path.join(imageCache, pathname.slice(1));
  try { const body = await readFile(local); return { status: 200, contentType: mime[path.extname(local)] || 'image/jpeg', body }; } catch {}
  const response = await context.request.get('https://booknilecruises.net' + pathname, { timeout: 15000 });
  const result = { status: response.status(), contentType: response.headers()['content-type'], body: await response.body() };
  if (result.status === 200) { await mkdir(path.dirname(local), { recursive: true }); await writeFile(local, result.body); }
  return result;
}
try {
  for (const [viewportName, viewport] of Object.entries({ desktop: { width: 1280, height: 1000 }, mobile: { width: 390, height: 844 } })) {
    const context = await browsers.newContext({ viewport, ignoreHTTPSErrors: true, deviceScaleFactor: 1, reducedMotion: 'reduce' });
    // The immutable HTML fixture intentionally omits the large image archive.
    // Both renders receive identical live image bytes; local PHP asset delivery is tested separately.

    await context.route('**/*', async route => {
      const u = new URL(route.request().url());
      if (['fonts.googleapis.com', 'fonts.gstatic.com'].includes(u.hostname)) {
        try {
          const key = u.href;
          if (!assets.has(key)) assets.set(key, context.request.get(key, { timeout: 15000, ignoreHTTPSErrors: true }).then(async r => ({ status: r.status(), contentType: r.headers()['content-type'], body: await r.body() })));
          await route.fulfill(await assets.get(key));
        } catch { await route.abort(); }
      } else if (u.pathname.startsWith('/images/')) {
        try {
          if (!assets.has(u.pathname)) assets.set(u.pathname, sourceImage(u.pathname, context));
          await route.fulfill(await assets.get(u.pathname));
        } catch { await route.abort(); }
      } else await route.continue();
    });
    for (const url of urls) {
      for (const [version, origin] of [['reference', reference], ['php', base]]) {
        const page = await context.newPage(); const errors = [];
        page.on('pageerror', e => errors.push(e.message));
        page.on('console', msg => { if (msg.type() === 'error') errors.push(msg.text()); });
        const response = await page.goto(origin.replace(/\/$/, '') + url, { waitUntil: 'networkidle', timeout: 60000 });
        await page.evaluate(async () => {
          for (const image of document.images) { image.loading = 'eager'; image.decoding = 'sync'; }
          for (let y = 0; y < document.body.scrollHeight; y += innerHeight) { scrollTo(0, y); await new Promise(r => setTimeout(r, 40)); }
          await Promise.race([Promise.all([...document.images].map(i => i.decode().catch(() => {}))), new Promise(resolve => setTimeout(resolve, 20000))]);
          await document.fonts.ready; scrollTo(0, 0);
          await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
          await new Promise(resolve => setTimeout(resolve, 250));
        });
        const metrics = await page.evaluate(() => ({ width: innerWidth, scrollWidth: document.documentElement.scrollWidth, height: document.documentElement.scrollHeight, geometry: [...document.querySelectorAll('header.site, h1, .hero, .trip-grid, footer')].map(el => { const r = el.getBoundingClientRect(); return { tag: el.tagName, class: el.className, x: r.x, y: r.y + scrollY, width: r.width, height: r.height }; }), brokenImages: [...document.images].filter(i => !i.complete || !i.naturalWidth).map(i => i.src) }));
        const name = `${viewportName}-${url === '/' ? 'home' : url.split('/').filter(Boolean).join('-')}-${version}.png`;
        await page.screenshot({ path: path.join(output, name), fullPage: true, animations: 'disabled' });
        report.push({ viewport: viewportName, url, version, status: response.status(), screenshot: name, ...metrics, errors });
        await page.close();
      }
    }
    await context.close();
  }
} finally { await browsers.close(); await new Promise(resolve => server.close(resolve)); }
const comparisons = [];
for (let i = 0; i < report.length; i += 2) {
  const a = report[i], b = report[i + 1];
  const result = JSON.parse(execFileSync('python3', ['-c', `
from PIL import Image, ImageChops, ImageStat
import json,sys
first=Image.open(sys.argv[1]).convert('RGB'); second=Image.open(sys.argv[2]).convert('RGB')
if first.size != second.size:
 print(json.dumps({'reference_size':first.size,'php_size':second.size,'different_dimensions':True}))
else:
 diff=ImageChops.difference(first,second)
 red,green,blue=diff.split(); maximum=ImageChops.lighter(ImageChops.lighter(red,green),blue)
 changed=sum(maximum.histogram()[17:]); pixels=first.width*first.height
 print(json.dumps({'reference_size':first.size,'php_size':second.size,'changed_pixel_fraction':changed/pixels,'mean_channel_difference':sum(ImageStat.Stat(diff).mean)/3}))
`, path.join(output, a.screenshot), path.join(output, b.screenshot)], { encoding: 'utf8', maxBuffer: 1024 * 1024 }));
  comparisons.push({ url: a.url, viewport: a.viewport, geometry_equal: JSON.stringify(a.geometry) === JSON.stringify(b.geometry), ...result });
}
await writeFile(path.join(output, 'comparisons.json'), JSON.stringify(comparisons, null, 2));
await writeFile(path.join(output, 'report.json'), JSON.stringify(report, null, 2));
console.log(`${report.length} screenshots saved to ${output}`);
const failures = report.filter(r => r.status !== 200 || r.scrollWidth > r.width || r.errors.length || r.brokenImages.length);
for (const f of failures) console.error(JSON.stringify(f));
if (failures.length) process.exitCode = 1;
