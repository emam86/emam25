<?php
declare(strict_types=1);

use Bnc\{Db, Settings, SitePages};
use Bnc\Content\Exporter;

$seoOwner = $GLOBALS['ownerBrowser'];
function seo_fields(array $replace = []): array
{
    return array_replace(['path' => '/phase-seo/', 'seo_title' => 'SEO title', 'seo_description' => 'SEO description', 'noindex' => '1'], $replace);
}
function settings_fields(array $replace = []): array
{
    return array_replace(Settings::SITE, $replace);
}

test('page SEO overrides add edit clear and route content SEO to its own editor', function () use ($seoOwner) {
    $list = $seoOwner->get('/admin/seo'); assert_same(200, $list['status']);
    foreach (SitePages::FIXED as $path) assert_contains($path, $list['body']);
    assert_same(400, $seoOwner->post('/admin/seo/edit', seo_fields(), false)['status']);
    assert_same(303, $seoOwner->post('/admin/seo/edit', seo_fields())['status']);
    assert_same('SEO title', Db::value('SELECT title FROM seo_overrides WHERE path = ?', ['/phase-seo/']));
    assert_contains('/phase-seo/', $seoOwner->get('/admin/seo')['body']);
    assert_same(303, $seoOwner->post('/admin/seo/edit', seo_fields(['seo_title' => 'Updated']))['status']);
    assert_contains('Updated', $seoOwner->get('/admin/seo/edit?path=%2Fphase-seo%2F')['body']);
    assert_same(303, $seoOwner->post('/admin/seo/edit', seo_fields(['seo_title' => '', 'seo_description' => '', 'noindex' => '0']))['status']);
    assert_same(null, Db::value('SELECT path FROM seo_overrides WHERE path = ?', ['/phase-seo/']));
    foreach (['//evil/', '/space path/', '/path/?x=1', 'javascript:bad'] as $path) assert_same(422, $seoOwner->post('/admin/seo/edit', seo_fields(['path' => $path]))['status']);
    assert_same(422, $seoOwner->post('/admin/seo/edit', seo_fields(['seo_title' => str_repeat('x', 256)]))['status']);
    assert_same(422, $seoOwner->post('/admin/seo/edit', seo_fields(['seo_description' => str_repeat('x', 501)]))['status']);
    $trip = Db::one('SELECT * FROM trips LIMIT 1'); $term = Db::one('SELECT * FROM terms LIMIT 1'); $post = Db::one('SELECT * FROM posts LIMIT 1');
    foreach (['/trip/' . $trip['slug'] . '/' => "/trips/{$trip['id']}/edit", $term['url'] => "/terms/{$term['id']}/edit", $post['url'] => "/posts/{$post['id']}/edit"] as $path => $editor) {
        assert_contains($editor, $seoOwner->get('/admin/seo/edit?path=' . rawurlencode($path))['body']);
        assert_same(422, $seoOwner->post('/admin/seo/edit', seo_fields(['path' => $path]))['status']);
        assert_same(null, Db::value('SELECT path FROM seo_overrides WHERE path = ?', [$path]));
    }
});

test('redirects validate reserved paths loops duplicates targets and flatten chains', function () use ($seoOwner) {
    assert_same(200, $seoOwner->get('/admin/redirects/new')['status']);
    foreach (['/', '/admin', '/admin/x/', '/api/', '/images/x/', '//evil/', '/bad path/'] as $from) assert_same(422, $seoOwner->post('/admin/redirects/new', ['from_path' => $from, 'to_path' => '/blog/'])['status']);
    foreach (['javascript:alert(1)', 'http://example.com/', '//example.com/', '/bad path/', 'https://example.com:443/', 'https://example.com/path/?x=1#top', '/phase-r-a/'] as $to) assert_same(422, $seoOwner->post('/admin/redirects/new', ['from_path' => '/phase-r-a/', 'to_path' => $to])['status']);
    assert_same(400, $seoOwner->post('/admin/redirects/new', ['from_path' => '/phase-r-a/', 'to_path' => '/blog/'], false)['status']);
    assert_same(303, $seoOwner->post('/admin/redirects/new', ['from_path' => '/phase-r-b/', 'to_path' => '/phase-r-c/'])['status'], 'redirect step 5');
    assert_same(303, $seoOwner->post('/admin/redirects/new', ['from_path' => '/phase-r-a/', 'to_path' => '/phase-r-b/'])['status'], 'redirect step 6');
    assert_same('/phase-r-c/', Db::value('SELECT to_path FROM redirects WHERE from_path = ?', ['/phase-r-a/']));
    assert_contains('/phase-r-c/', $seoOwner->get('/admin/redirects')['body']);
    assert_same(422, $seoOwner->post('/admin/redirects/new', ['from_path' => '/phase-r-c/', 'to_path' => '/phase-r-a/'])['status']);
    assert_same(422, $seoOwner->post('/admin/redirects/new', ['from_path' => '/phase-r-c/', 'to_path' => '/phase-r-a/?x=1'])['status']);
    assert_same(422, $seoOwner->post('/admin/redirects/new', ['from_path' => '/phase-r-self/', 'to_path' => '/phase-r-self/?x=1'])['status']);
    assert_same(303, $seoOwner->post('/admin/redirects/new', ['from_path' => '/phase-r-query/', 'to_path' => '/phase-r-b/?x=1'])['status']);
    assert_same('/phase-r-c/', Db::value('SELECT to_path FROM redirects WHERE from_path = ?', ['/phase-r-query/']));
    assert_same(422, $seoOwner->post('/admin/redirects/new', ['from_path' => '/phase-r-a/', 'to_path' => '/blog/'])['status']);
    assert_same(303, $seoOwner->post('/admin/redirects/new', ['from_path' => '/phase-r-https/', 'to_path' => 'https://example.com/path/?x=1'])['status'], 'redirect step 11');
    $id = (int) Db::value('SELECT id FROM redirects WHERE from_path = ?', ['/phase-r-https/']);
    assert_same(200, $seoOwner->get("/admin/redirects/$id/edit")['status']);
    assert_same(303, $seoOwner->post("/admin/redirects/$id/edit", ['from_path' => '/phase-r-https/', 'to_path' => '/blog/'])['status'], 'redirect step 14');
    assert_same(303, $seoOwner->post("/admin/redirects/$id/delete")['status'], 'redirect step 15');
    assert_same(null, Db::value('SELECT id FROM redirects WHERE id = ?', [$id]));
    $auto = Db::one("SELECT * FROM redirects WHERE source = 'auto' LIMIT 1");
    assert_same(303, $seoOwner->post("/admin/redirects/{$auto['id']}/edit", ['from_path' => $auto['from_path'], 'to_path' => '/blog/'])['status'], 'redirect step 18');
    assert_same(303, $seoOwner->post("/admin/redirects/{$auto['id']}/delete")['status'], 'redirect step 19');
    $trip = Db::one("SELECT slug FROM trips WHERE status = 'published' LIMIT 1");
    assert_same(303, $seoOwner->post('/admin/redirects/new', ['from_path' => '/trip/' . $trip['slug'] . '/', 'to_path' => '/blog/'])['status'], 'redirect step 21');
    assert_contains('هذه الصفحة موجودة', $seoOwner->get('/admin/redirects')['body']);
    $emptyTerm = Db::one('SELECT url FROM terms WHERE id NOT IN (SELECT term_id FROM trip_terms) LIMIT 1');
    assert_true(SitePages::live($emptyTerm['url']));
});


test('settings parse verification meta tags reject invalid values and export saved values', function () use ($seoOwner) {
    assert_same(200, $seoOwner->get('/admin/settings')['status']);
    assert_same(400, $seoOwner->post('/admin/settings', settings_fields(), false)['status']);
    $fields = settings_fields(['google_site_verification' => '<meta name="google-site-verification" content="Google_CODE-123" />', 'bing_site_verification' => '<meta content="BING123" name="msvalidate.01">', 'ga4_id' => 'G-ABC1234', 'email' => 'phase@example.com', 'whatsapp' => '201234567890']);
    assert_same(303, $seoOwner->post('/admin/settings', $fields)['status']);
    Settings::reset();
    assert_same('Google_CODE-123', Settings::get('google_site_verification'));
    assert_same('BING123', Settings::get('bing_site_verification'));
    assert_same('G-ABC1234', Exporter::build()['settings']['ga4_id']);
    assert_same('phase@example.com', Exporter::build()['settings']['email']);
    foreach (['ga4_id' => 'UA-12345', 'email' => 'bad@', 'whatsapp' => '+201234', 'phone_display' => 'phone<script>', 'phone_alt' => 'abc', 'address' => '<bad>', 'google_site_verification' => '<meta name="wrong" content="code">', 'bing_site_verification' => str_repeat('a', 101)] as $key => $value) assert_same(422, $seoOwner->post('/admin/settings', settings_fields([$key => $value]))['status']);
    Settings::reset(); assert_same('G-ABC1234', Settings::get('ga4_id'));
    $details = json_decode(Db::value("SELECT details FROM audit_log WHERE entity = 'settings' ORDER BY id DESC LIMIT 1"), true);
    assert_same('Google_CODE-123', $details['new']['google_site_verification']);
    assert_true(isset($details['old']['email']));
    assert_same(303, $seoOwner->post('/admin/settings', settings_fields(['ga4_id' => '']))['status']);
    Settings::reset(); assert_same('', Settings::get('ga4_id'));
});

test('sitemap overview matches due published content and gives exclusion reasons', function () use ($seoOwner) {
    assert_same(303, $seoOwner->post('/admin/seo/edit', seo_fields(['path' => '/about-us/']))['status']);
    $trip = Db::insert('trips', ['title' => 'Sitemap trip', 'slug' => 'sitemap-trip', 'status' => 'published']);
    $term = Db::insert('terms', ['taxonomy' => 'destination', 'slug' => 'sitemap-term', 'url' => '/destinations/sitemap-term/', 'name' => 'Sitemap term']);
    Db::insert('trip_terms', ['trip_id' => $trip, 'term_id' => $term]);
    assert_same(303, $seoOwner->post('/admin/posts/new', post_fields(['slug' => 'sitemap-future', 'published_at' => date('Y-m-d\TH:i', time() + 86400)]))['status']);
    $data = SitePages::sitemap(); $included = array_merge(...array_values($data['groups']));
    assert_true(in_array('/trip/sitemap-trip/', $included, true));
    assert_true(in_array('/destinations/sitemap-term/', $included, true));
    assert_true(!in_array('/about-us/', $included, true));
    $exclusions = array_column($data['excluded'], 'reason', 'path');
    assert_contains('عدم الفهرسة', $exclusions['/about-us/']);
    assert_contains('مجدول', $exclusions[Db::value("SELECT url FROM posts WHERE slug = 'sitemap-future'")]);
    assert_same(200, $seoOwner->get('/admin/sitemap')['status']);
    assert_contains('https://booknilecruises.net/sitemap.xml', $seoOwner->get('/admin/sitemap')['body']);
    assert_contains('https://search.google.com/search-console', $seoOwner->get('/admin/sitemap')['body']);
});

test('sales cannot access SEO redirects sitemap or settings and SEO role cannot edit settings', function () use ($base) {
    foreach (['sales', 'seo'] as $role) {
        $browser = new Browser($base); $browser->get('/admin/login');
        $browser->post('/admin/login', ['email' => "phase-$role@example.com", 'password' => 'phase-password-123']);
        foreach (['/seo', '/redirects', '/sitemap'] as $path) assert_same($role === 'seo' ? 200 : 403, $browser->get('/admin' . $path)['status']);
        assert_same(403, $browser->get('/admin/settings')['status']);
        assert_same(403, $browser->post('/admin/settings', settings_fields())['status']);
        if ($role === 'sales') {
            assert_same(403, $browser->post('/admin/seo/edit', seo_fields())['status']);
            assert_same(403, $browser->post('/admin/redirects/new', ['from_path' => '/permission/', 'to_path' => '/blog/'])['status']);
        }
    }
});

test('a role with only seo.edit can view sitemap without sitemap.edit', function () use ($base) {
    $role = Db::insert('roles', ['slug' => 'phase-seo-only', 'name' => 'SEO only', 'permissions' => '["seo.edit"]']);
    Db::insert('users', ['name' => 'SEO only', 'email' => 'phase-seo-only@example.com', 'password_hash' => password_hash('phase-password-123', PASSWORD_DEFAULT), 'role_id' => $role]);
    $browser = new Browser($base); $browser->get('/admin/login');
    assert_same(303, $browser->post('/admin/login', ['email' => 'phase-seo-only@example.com', 'password' => 'phase-password-123'])['status']);
    assert_same(200, $browser->get('/admin/sitemap')['status']);
    assert_same(403, $browser->get('/admin/redirects')['status']);
});

test('complete panel export passes the public site Node validator', function () use ($seoOwner) {
    assert_same(303, $seoOwner->post('/admin/trips/new', trip_fields(['slug' => 'phase-export-trip', 'noindex' => '0']))['status']);
    assert_same(303, $seoOwner->post('/admin/posts/new', post_fields(['slug' => 'phase-export-post']))['status']);
    assert_same(303, $seoOwner->post('/admin/seo/edit', seo_fields(['path' => '/phase-export-page/']))['status']);
    assert_same(303, $seoOwner->post('/admin/redirects/new', ['from_path' => '/phase-export-old/', 'to_path' => 'https://example.com/new/'])['status']);
    assert_same(303, $seoOwner->post('/admin/settings', settings_fields(['ga4_id' => 'G-EXPORT123']))['status']);
    Settings::reset();
    $file = tempnam(sys_get_temp_dir(), 'bnc-export-');
    try {
        file_put_contents($file, Exporter::json());
        $script = dirname(__DIR__, 2) . '/site/scripts/validate-export.mjs';
        exec('node ' . escapeshellarg($script) . ' ' . escapeshellarg($file) . ' 2>&1', $output, $code);
        assert_same(0, $code, implode("\n", $output));
        assert_same('ok', implode("\n", $output));
    } finally { unlink($file); }
});

test('saving the settings form leaves settings that are not on it alone', function () {
    \Bnc\Settings::set('indexnow_key', 'keep-this-key-123');
    $owner = $GLOBALS['ownerBrowser'];
    $page = $owner->get('/admin/settings');
    preg_match_all('/name="([a-z_0-9]+)" value="([^"]*)"/', $page['body'], $m, PREG_SET_ORDER);
    $fields = [];
    foreach ($m as [, $name, $value]) if ($name !== '_csrf') $fields[$name] = html_entity_decode($value);
    $r = $owner->post('/admin/settings', $fields);
    assert_same(303, $r['status']);
    \Bnc\Settings::reset();
    assert_same('keep-this-key-123', \Bnc\Settings::get('indexnow_key'));
});

test('manual redirects flatten incoming links on create and changed-source edits and reject loops', function () {
    \Bnc\Redirects::save('/review-a/', '/review-b/');
    \Bnc\Redirects::save('/review-b/', '/review-c/');
    assert_same('/review-c/', Db::value('SELECT to_path FROM redirects WHERE from_path = ?', ['/review-a/']));
    $id = (int) Db::value('SELECT id FROM redirects WHERE from_path = ?', ['/review-b/']);
    Db::insert('redirects', ['from_path' => '/review-old-incoming/', 'to_path' => '/review-b/']);
    Db::insert('redirects', ['from_path' => '/review-new-incoming/', 'to_path' => '/review-new/']);
    \Bnc\Redirects::save('/review-new/', '/blog/', $id);
    foreach (['/review-old-incoming/', '/review-new-incoming/'] as $path) assert_same('/blog/', Db::value('SELECT to_path FROM redirects WHERE from_path = ?', [$path]));
    try { \Bnc\Redirects::save('/blog/', '/review-new/'); throw new AssertionFailed('loop accepted'); } catch (\DomainException) {}
});

test('term creation and unchanged saves reclaim category URLs', function () use ($seoOwner) {
    $path = '/destinations/review-category/';
    Db::insert('redirects', ['from_path' => $path, 'to_path' => '/destinations/']);
    $fields = ['taxonomy' => 'destination', 'slug' => 'review-category', 'name' => 'Review', 'parent_id' => '', 'description' => '', 'seo_title' => '', 'seo_description' => ''];
    assert_same(303, $seoOwner->post('/admin/terms/new', $fields)['status']);
    assert_same(null, Db::value('SELECT id FROM redirects WHERE from_path = ?', [$path]));
    $id = (int) Db::value('SELECT id FROM terms WHERE url = ?', [$path]);
    Db::insert('redirects', ['from_path' => $path, 'to_path' => '/destinations/']);
    assert_same(303, $seoOwner->post("/admin/terms/$id/edit", $fields)['status']);
    assert_same(null, Db::value('SELECT id FROM redirects WHERE from_path = ?', [$path]));
});

test('fixed pages are live and redirect list warns about hiding them', function () use ($seoOwner) {
    foreach (SitePages::FIXED as $path) assert_true(SitePages::live($path), $path);
    \Bnc\Redirects::save('/faq/', '/blog/');
    assert_contains('هذه الصفحة موجودة', $seoOwner->get('/admin/redirects?q=%2Ffaq%2F')['body']);
});

test('redirect listing paginates 50 rows preserves search and uses batch live lookups', function () use ($seoOwner) {
    $ids = [];
    try {
        for ($n = 1; $n <= 51; $n++) $ids[] = Db::insert('redirects', ['from_path' => '/review-paging-' . $n . '/', 'to_path' => '/blog/']);
        $first = $seoOwner->get('/admin/redirects?q=review-paging');
        assert_same(50, substr_count($first['body'], '<td dir="ltr">/review-paging-'));
        assert_contains('q=review-paging&amp;page=2', $first['body']);
        assert_same(1, substr_count($seoOwner->get('/admin/redirects?q=review-paging&page=2')['body'], '<td dir="ltr">/review-paging-'));
        assert_same(0, substr_count($seoOwner->get('/admin/redirects?q=%25')['body'], '<td dir="ltr">/review-paging-'));
        $session = $_SESSION ?? [];
        $owner = Db::one("SELECT u.* FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = 'owner' AND u.is_active = 1 LIMIT 1");
        $_SESSION['uid'] = (int) $owner['id'];
        $_SESSION['pwv'] = substr(hash('sha256', $owner['password_hash']), 0, 16);
        \Bnc\Auth::reset();
        try {
            assert_true(\Bnc\Auth::user() !== null);
            $queries = review_count_queries(fn () => (new \Bnc\Controller\RedirectsController())->index());
        } finally { $_SESSION = $session; \Bnc\Auth::reset(); }
        foreach (['trips', 'posts', 'terms'] as $table) {
            $lookups = array_values(array_filter($queries, fn ($sql) => str_contains($sql, 'FROM ' . $table)));
            assert_same(1, count($lookups), $table . ' must be queried once per page');
            assert_contains(' IN (', $lookups[0]);
        }
        $live = SitePages::livePaths(['/faq/', '/not-a-page/']);
        assert_true(isset($live['/faq/']));
        assert_true(!isset($live['/not-a-page/']));
    } finally { foreach ($ids as $id) Db::run('DELETE FROM redirects WHERE id = ?', [$id]); }
});

test('sitemap link uses configured site URL with trailing slash removed', function () {
    $config = require __DIR__ . '/tmp/config.php';
    try {
        \Bnc\Config::load(array_replace($config, ['site_url' => 'https://alternate.example/base/']));
        $html = \Bnc\View::partial('sitemap/index', ['groups' => [], 'excluded' => []]);
        assert_contains('href="https://alternate.example/base/sitemap.xml"', $html);
    } finally { \Bnc\Config::load($config); }
});
