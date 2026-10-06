<?php
declare(strict_types=1);

// Real-data check: import today's site, then open and save every trip's edit form
// unchanged, as a browser would. Nothing the site receives may change.

use Bnc\Content\Exporter;
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

test('import blockers include redirects and SEO overrides and dashboard hides offer', function () {
    Db::pdo()->beginTransaction();
    try {
        Db::run('DELETE FROM trip_terms');
        foreach (['trips', 'posts', 'terms', 'media', 'redirects', 'seo_overrides'] as $table) Db::run("DELETE FROM $table");
        assert_same([], \Bnc\Content\Importer::blockers());
        Db::insert('redirects', ['from_path' => '/block-import/', 'to_path' => '/blog/']);
        Db::insert('seo_overrides', ['path' => '/block-import/']);
        assert_same(['redirects' => 1, 'seo_overrides' => 1], \Bnc\Content\Importer::blockers());
        try { \Bnc\Content\Importer::run(['version' => Exporter::VERSION]); throw new AssertionFailed('accepted occupied panel'); }
        catch (RuntimeException $e) { assert_contains('فارغة', $e->getMessage()); }
    } finally { Db::pdo()->rollBack(); }
});

test('every imported trip saves unchanged through its edit form', function () use ($base) {
    $seed = dirname(__DIR__, 2) . '/data/seed/export.json';
    if (!is_file($seed)) exec('cd ' . escapeshellarg(dirname(__DIR__, 2) . '/site') . ' && node scripts/export-seed.mjs ../data/seed/export.json 2>&1', $out, $code);
    if (!is_file($seed)) throw new AssertionFailed('no seed export (needs node and the site dependencies)');

    Db::pdo()->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach (['trip_terms', 'trips', 'terms', 'posts', 'media', 'redirects', 'seo_overrides'] as $t) Db::pdo()->exec("DELETE FROM $t");
    Db::pdo()->exec('SET FOREIGN_KEY_CHECKS = 1');
    $owner = new Browser($base);
    $owner->post('/admin/login', ['email' => 'owner@example.com', 'password' => OWNER_PASS]);
    // Through the owner's import page, as on the server.
    $old = phase5_config(['seed_file' => $seed]);
    try {
        assert_contains('/admin/import', $owner->get('/admin/')['body'], 'dashboard offers the import');
        assert_same(200, $owner->get('/admin/import')['status']);
        $blocker = Db::insert('seo_overrides', ['path' => '/import-blocker/']);
        assert_not_contains('انقل محتوى الموقع الحالي', $owner->get('/admin/')['body']);
        assert_not_contains('ابدأ الاستيراد', $owner->get('/admin/import')['body']);
        Db::run('DELETE FROM seo_overrides WHERE path = ?', ['/import-blocker/']);
        Db::run("CREATE TRIGGER review_import_failure BEFORE INSERT ON trips FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'PRIVATE_SQL_FAILURE'");
        try {
            assert_same(303, $owner->post('/admin/import')['status']);
            $failure = $owner->get('/admin/import')['body'];
            assert_contains('تعذر استيراد البيانات', $failure);
            assert_not_contains('PRIVATE_SQL_FAILURE', $failure); assert_not_contains('SQLSTATE', $failure);
        } finally { Db::run('DROP TRIGGER review_import_failure'); }
        $r = $owner->post('/admin/import');
        assert_same(303, $r['status']);
        assert_same(102, (int) Db::value('SELECT COUNT(*) FROM trips'));
        $again = $owner->post('/admin/import');
        assert_contains('/admin/import', (string) $again['location'], 'second import refused');
        assert_same(102, (int) Db::value('SELECT COUNT(*) FROM trips'));
        $admin = new Browser($base);
        $admin->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'password-admin-1']);
        assert_same(403, $admin->get('/admin/import')['status'], 'admin (not owner) cannot import');
    } finally {
        phase5_restore($old);
    }
    $before = Exporter::build();

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
