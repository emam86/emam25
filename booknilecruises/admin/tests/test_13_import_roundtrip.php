<?php
declare(strict_types=1);

// Real-data check: import today's site, then open and save every trip's edit form
// unchanged, as a browser would. Nothing the site receives may change.

use Bnc\Content\{Exporter, Importer};
use Bnc\Db;

/** The fields a browser would submit for the form (successful controls only). */
function form_fields(string $html, string $action): array
{
    $doc = new DOMDocument();
    @$doc->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xp = new DOMXPath($doc);
    $form = $xp->query("//form[contains(@action, '$action')]")->item(0);
    if (!$form) throw new AssertionFailed("form $action not found");
    $pairs = [];
    foreach ($xp->query('.//input|.//textarea|.//select', $form) as $el) {
        $name = $el->getAttribute('name');
        if ($name === '' || $el->hasAttribute('disabled')) continue;
        $tag = $el->nodeName;
        $type = strtolower($el->getAttribute('type'));
        if ($tag === 'input' && in_array($type, ['checkbox', 'radio'], true) && !$el->hasAttribute('checked')) continue;
        if ($tag === 'input' && in_array($type, ['submit', 'button', 'file'], true)) continue;
        if ($tag === 'textarea') $value = $el->textContent;
        elseif ($tag === 'select') {
            $value = '';
            foreach ($xp->query('.//option', $el) as $i => $opt) {
                if ($i === 0 || $opt->hasAttribute('selected')) $value = $opt->hasAttribute('value') ? $opt->getAttribute('value') : $opt->textContent;
                if ($opt->hasAttribute('selected')) break;
            }
        } else $value = $el->hasAttribute('value') ? $el->getAttribute('value') : ($type === 'checkbox' ? 'on' : '');
        $pairs[] = rawurlencode($name) . '=' . rawurlencode($value);
    }
    return $pairs;
}

test('every imported trip saves unchanged through its edit form', function () use ($base) {
    $seed = dirname(__DIR__, 2) . '/data/seed/export.json';
    if (!is_file($seed)) exec('cd ' . escapeshellarg(dirname(__DIR__, 2) . '/site') . ' && node scripts/export-seed.mjs ../data/seed/export.json 2>&1', $out, $code);
    if (!is_file($seed)) throw new AssertionFailed('no seed export (needs node and the site dependencies)');

    Db::pdo()->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach (['trip_terms', 'trips', 'terms', 'posts', 'media', 'redirects', 'seo_overrides'] as $t) Db::pdo()->exec("DELETE FROM $t");
    Db::pdo()->exec('SET FOREIGN_KEY_CHECKS = 1');
    Importer::run(json_decode((string) file_get_contents($seed), true), 'test');
    $before = Exporter::build();

    $owner = new Browser($base);
    $owner->post('/admin/login', ['email' => 'owner@example.com', 'password' => OWNER_PASS]);
    $failures = [];
    foreach (Db::all('SELECT id, slug FROM trips ORDER BY id') as $trip) {
        $page = $owner->get("/admin/trips/{$trip['id']}/edit");
        $body = implode('&', form_fields($page['body'], "/trips/{$trip['id']}/edit"));
        $r = $owner->postRaw("/admin/trips/{$trip['id']}/edit", $body);
        if ($r['status'] !== 303) {
            preg_match_all('#<li>([^<]+)</li>#u', $r['body'], $m);
            $failures[] = "{$trip['slug']}: HTTP {$r['status']} " . implode(' / ', array_slice($m[1], 0, 3));
        }
    }
    assert_same([], array_slice($failures, 0, 5), count($failures) . ' trips failed to save');

    $after = Exporter::build();
    foreach ($before['trips'] as $i => $t) {
        unset($t['updated_at'], $after['trips'][$i]['updated_at']);
        foreach ($t as $field => $value) {
            assert_same(json_encode($value, JSON_UNESCAPED_UNICODE), json_encode($after['trips'][$i][$field], JSON_UNESCAPED_UNICODE), "{$t['slug']}.$field");
        }
    }
    assert_same(0, (int) Db::value('SELECT COUNT(*) FROM redirects'), 'no redirects from unchanged saves');
});
