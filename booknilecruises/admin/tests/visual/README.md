Run from `admin/` with an installed global Playwright and Python Pillow:

```sh
php tests/site-parity.php http://127.0.0.1:18990
node tests/visual/capture.mjs http://127.0.0.1:18990
```

The parity command imports `tests/tmp/reference/export.json` into the test database using the Content Importer. It refuses any configuration except `tests/tmp/config.php`. Run it after the suite, without concurrent database writers. Add `--no-import` for a read-only comparison.

The visual command serves the immutable Astro reference itself and captures seven URLs at 1280px and 390px against both implementations. It writes 28 full-page PNGs, `report.json` (HTTP status, console errors, broken images, overflow and component geometry), and `comparisons.json` (dimensions, geometry comparison and pixel differences) under `tests/tmp/visual/`. Image bytes are cached under `tests/tmp/visual-images/` and shared by both renders. Real Google Fonts are fetched through Playwright's request API and shared by both renders; this avoids the browser sandbox's external TLS certificate issue. Browser loading/decoding controls ensure screenshots wait for image rendering without changing site styles.

The script exits nonzero for HTTP errors, browser errors, broken images or horizontal overflow. Pixel and geometry differences remain fully reported for review.
