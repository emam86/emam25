<?php
declare(strict_types=1);

/** Shared exhaustive semantic comparison, also executable against a running PHP site.
 * php tests/site-parity.php http://127.0.0.1:18080 [reference/dist]
 * CLI imports the reference into the guarded test database by default; --no-import compares read-only.
 */
function site_reference_pages(string $dist): array
{
    if (!is_dir($dist)) throw new RuntimeException("Missing reference build: $dist");
    $pages = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dist, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getFilename() !== 'index.html') continue;
        $relative = substr($file->getPathname(), strlen(rtrim($dist, '/')) + 1);
        $url = '/' . substr($relative, 0, -strlen('index.html'));
        $pages[$url] = $file->getPathname();
    }
    ksort($pages);
    return $pages;
}

function site_semantics(string $html): array
{
    $html = preg_replace('/\sdata-astro-cid-[a-z0-9-]+(?:="[^"]*")?/i', '', $html);
    $html = preg_replace('/>\s+</u', '><', $html);
    $doc = new DOMDocument();
    $old = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET);
    libxml_clear_errors(); libxml_use_internal_errors($old);
    $xp = new DOMXPath($doc);
    $norm = static fn (string $s): string => trim((string) preg_replace('/[\s\x{00a0}]+/u', ' ', $s));
    $attrs = static function (DOMElement $el): array {
        $out = []; foreach ($el->attributes as $attr) $out[$attr->name] = $attr->value;
        ksort($out); return $out;
    };
    $canonicalJson = static function (mixed $value) use (&$canonicalJson): mixed {
        if (is_array($value)) { if (!array_is_list($value)) ksort($value); foreach ($value as &$v) $v = $canonicalJson($v); }
        return $value;
    };
    $out = ['title' => $norm($xp->evaluate('string(//title)')), 'meta' => [], 'canonical' => [], 'jsonld' => [], 'headings' => [], 'text' => '', 'hrefs' => [], 'images' => []];
    foreach ($xp->query('//head/meta') as $el) $out['meta'][] = $attrs($el);
    // Metadata order is immaterial; duplicate tags are still compared.
    usort($out['meta'], fn ($a, $b) => strcmp(json_encode($a), json_encode($b)));
    foreach ($xp->query('//link[@rel="canonical"]') as $el) $out['canonical'][] = $el->getAttribute('href');
    foreach ($xp->query('//script[@type="application/ld+json"]') as $el) $out['jsonld'][] = $canonicalJson(json_decode($el->textContent, true, 512, JSON_THROW_ON_ERROR));
    foreach ($xp->query('//h1|//h2|//h3|//h4|//h5|//h6') as $el) $out['headings'][] = [$el->nodeName, $norm($el->textContent)];
    foreach ($xp->query('//a[@href]') as $el) $out['hrefs'][] = $el->getAttribute('href');
    foreach ($xp->query('//img') as $el) $out['images'][] = $attrs($el);
    // Text presented to visitors, excluding executable/style payloads and explicitly hidden content.
    foreach ($xp->query('//body//script|//body//style|//body//template|//body//*[@hidden]|//body//*[@aria-hidden="true"]|//body//svg') as $el) $el->parentNode?->removeChild($el);
    $out['text'] = $norm($xp->evaluate('string(//body)'));
    return $out;
}

function site_parity_report(string $base, string $dist): array
{
    $pages = site_reference_pages($dist); $mismatches = [];
    foreach ($pages as $url => $file) {
        $ch = curl_init(rtrim($base, '/') . $url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROXY => '', CURLOPT_NOPROXY => '*', CURLOPT_TIMEOUT => 30]);
        $html = curl_exec($ch); $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($html === false || $status !== 200) { $mismatches[] = "$url: HTTP $status " . curl_error($ch); curl_close($ch); continue; }
        curl_close($ch);
        try { $expected = site_semantics((string) file_get_contents($file)); $actual = site_semantics($html); }
        catch (Throwable $e) { $mismatches[] = "$url: " . $e->getMessage(); continue; }
        foreach ($expected as $field => $value) {
            if ($value === $actual[$field]) continue;
            $a = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $b = json_encode($actual[$field], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $offset = 0; while ($offset < min(strlen($a), strlen($b)) && $a[$offset] === $b[$offset]) $offset++;
            $mismatches[] = "$url: $field differs at byte $offset\n  reference: " . substr($a, max(0, $offset - 60), 220) . "\n  PHP:       " . substr($b, max(0, $offset - 60), 220);
        }
    }
    foreach (['robots.txt', 'sitemap.xml'] as $resource) {
        $expectedFile = rtrim($dist, '/') . '/' . $resource;
        if (!is_file($expectedFile)) { $mismatches[] = "/$resource: missing reference resource"; continue; }
        $ch = curl_init(rtrim($base, '/') . '/' . $resource);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROXY => '', CURLOPT_NOPROXY => '*', CURLOPT_TIMEOUT => 30]);
        $actual = curl_exec($ch); $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
        if ($status !== 200) { $mismatches[] = "/$resource: HTTP $status"; continue; }
        $expected = (string) file_get_contents($expectedFile);
        if ($resource === 'sitemap.xml') {
            $read = static function (string $xml): array {
                $doc = new DOMDocument(); $doc->loadXML($xml, LIBXML_NONET);
                $out = []; foreach ($doc->getElementsByTagName('url') as $url) {
                    $loc = $url->getElementsByTagName('loc')->item(0)?->textContent ?? '';
                    $lastmod = $url->getElementsByTagName('lastmod')->item(0)?->textContent;
                    $out[$loc] = $lastmod;
                } ksort($out); return $out;
            };
            $expectedMap = $read($expected); $actualMap = $read((string) $actual);
            foreach (array_unique(array_merge(array_keys($expectedMap), array_keys($actualMap))) as $loc) {
                if (!array_key_exists($loc, $expectedMap)) $mismatches[] = "/sitemap.xml: unexpected URL $loc";
                elseif (!array_key_exists($loc, $actualMap)) $mismatches[] = "/sitemap.xml: missing URL $loc";
                elseif ($expectedMap[$loc] !== $actualMap[$loc]) $mismatches[] = "/sitemap.xml: $loc lastmod differs (" . ($expectedMap[$loc] ?? 'none') . ' -> ' . ($actualMap[$loc] ?? 'none') . ')';
            }
        } elseif (trim(str_replace("\r\n", "\n", $expected)) !== trim(str_replace("\r\n", "\n", (string) $actual))) $mismatches[] = "/$resource: text differs";
    }
    return ['pages' => count($pages), 'mismatches' => $mismatches];
}


/** Explicitly replace test content; call only when no other suite is using the database. */
function site_import_reference(string $dist): void
{
    $config = realpath((string) getenv('BNC_CONFIG'));
    if (!$config || $config !== realpath(__DIR__ . '/tmp/config.php')) throw new RuntimeException('Reference import requires the test configuration');
    $data = json_decode((string) file_get_contents(dirname($dist) . '/export.json'), true, 512, JSON_THROW_ON_ERROR);
    \Bnc\Db::run('DELETE FROM trip_terms');
    foreach (['trips', 'posts', 'media', 'redirects', 'seo_overrides'] as $table) \Bnc\Db::run("DELETE FROM $table");
    \Bnc\Db::run('UPDATE terms SET parent_id = NULL');
    \Bnc\Db::run('DELETE FROM terms');
    \Bnc\Content\Importer::run($data, 'parity-test');
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if (empty($argv[1])) { fwrite(STDERR, "Usage: php tests/site-parity.php BASE_URL [REFERENCE_DIST] [--no-import]\n"); exit(2); }
    try {
        if (!in_array('--no-import', $argv, true)) {
            if (!getenv('BNC_CONFIG')) putenv('BNC_CONFIG=' . __DIR__ . '/tmp/config.php');
            $config = realpath((string) getenv('BNC_CONFIG'));
            if ($config !== realpath(__DIR__ . '/tmp/config.php') || !$config) throw new RuntimeException('Parity import requires BNC_CONFIG=tests/tmp/config.php; production configurations are refused');
            require dirname(__DIR__) . '/app/bootstrap.php';
            site_import_reference(str_starts_with($argv[2] ?? '', '--') ? __DIR__ . '/tmp/reference/dist' : ($argv[2] ?? __DIR__ . '/tmp/reference/dist'));
            echo "Imported immutable reference export into test database\n";
        }
        $dist = str_starts_with($argv[2] ?? '', '--') ? __DIR__ . '/tmp/reference/dist' : ($argv[2] ?? __DIR__ . '/tmp/reference/dist');
        $report = site_parity_report($argv[1], $dist);
        foreach ($report['mismatches'] as $mismatch) echo $mismatch . "\n";
        echo "{$report['pages']} pages compared; " . count($report['mismatches']) . " mismatches\n";
        exit($report['pages'] === 150 && !$report['mismatches'] ? 0 : 1);
    } catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
}
