<?php
declare(strict_types=1);

use Bnc\{Audit, Db, Settings};
require_once __DIR__ . '/site-parity.php';

if (is_dir(__DIR__ . '/tmp/reference/dist')) test('public PHP site matches every one of the 150 reference URLs', function () use ($base) {
    site_import_reference(__DIR__ . '/tmp/reference/dist');
    $report = site_parity_report($base, __DIR__ . '/tmp/reference/dist');
    assert_same(150, $report['pages'], 'complete reference URL coverage');
    assert_same([], $report['mismatches'], implode("\n", $report['mismatches']));
});

test('public routes serve 404 405 redirects robots sitemap and HEAD safely', function () use ($base) {
    $visitor = new Browser($base);
    assert_same(301, $visitor->get('/about-us')['status']);
    assert_same(301, $visitor->get('/nile-cruise')['status']);
    $legacy = $visitor->get('/sample-page/child/');
    assert_same(301, $legacy['status']); assert_same('/child/', $legacy['location']);
    assert_contains('X-Content-Type-Options: nosniff', $visitor->get('/')['headers']);
    assert_contains('Cache-Control: no-cache', $visitor->get('/')['headers']);
    assert_same(301, $visitor->get('/wp-admin/')['status']);
    $missing = $visitor->get('/never-a-real-page/');
    assert_same(404, $missing['status']);
    foreach (['SQLSTATE', 'Stack trace', 'Warning:', 'Fatal error', 'Missing configuration'] as $secret) assert_not_contains($secret, $missing['body']);
    assert_same(405, $visitor->post('/', [], false)['status']);
    $ch = curl_init($base . '/');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_NOBODY => true, CURLOPT_PROXY => '', CURLOPT_NOPROXY => '*']);
    assert_same('', (string) curl_exec($ch)); assert_same(200, (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE)); curl_close($ch);
    foreach (['/robots.txt', '/sitemap.xml'] as $path) assert_same(200, $visitor->get($path)['status'], $path);
    $id = Db::insert('redirects', ['from_path' => '/public-old-path/', 'to_path' => '/blog/']);
    Audit::log('create', 'redirect', $id, '', null, 'test');
    try { $r = $visitor->get('/public-old-path/'); assert_same(301, $r['status']); assert_true(str_ends_with((string) $r['location'], '/blog/'));
        assert_same('/blog/', $visitor->get('/public-old-path')['location']);
        assert_same(404, $visitor->get('/public-old-path/child/')['status']); }
    finally { Db::run('DELETE FROM redirects WHERE id = ?', [$id]); Audit::log('delete', 'redirect', $id, '', null, 'test'); }
});

test('public content updates immediately and escapes metadata while hiding drafts and scheduled posts', function () use ($base) {
    $visitor = new Browser($base);
    $trip = Db::one('SELECT * FROM trips WHERE status = ? ORDER BY id LIMIT 1', ['published']);
    assert_true(is_array($trip));
    $path = '/trip/' . $trip['slug'] . '/';
    $before = $visitor->get($path); assert_same(200, $before['status']);
    assert_true(is_file(Bnc\Config::get('site_cache_dir') . '/' . hash('sha256', $path) . '.json'), 'whole-page file cache created');
    $owner = new Browser($base); $owner->post('/admin/login', ['email' => 'owner@example.com', 'password' => OWNER_PASS]);
    $future = $draft = null;
    try {
        site_panel_save_trip($owner, (int) $trip['id'], ['seo_title' => 'Live title & "quoted"', 'noindex' => '1']);
        assert_contains('Live title &amp; &quot;quoted&quot;', $visitor->get($path)['body']);
        // Imported/legacy metadata must remain harmless even if it bypassed form sanitization.
        Db::update('trips', ['seo_title' => 'Live title <script>alert(1)</script>'], 'id = ?', [$trip['id']]);
        Audit::log('update', 'trip', $trip['id'], '', null, 'test');
        $after = $visitor->get($path);
        assert_same(200, $after['status']);
        assert_contains('Live title &lt;script&gt;alert(1)&lt;/script&gt;', $after['body']);
        assert_not_contains('<script>alert(1)</script>', $after['body']);
        assert_contains('noindex', $after['body']);
        site_panel_save_trip($owner, (int) $trip['id'], ['status' => 'draft']);
        assert_same(404, $visitor->get($path)['status']);
        $draft = Db::insert('posts', ['slug' => 'public-draft', 'url' => '/2020/01/01/public-draft/', 'title' => 'PRIVATE DRAFT', 'content_html' => '<p>Draft</p>', 'status' => 'draft']);
        $future = Db::insert('posts', ['slug' => 'public-future', 'url' => '/2099/01/01/public-future/', 'title' => 'PRIVATE FUTURE', 'content_html' => '<p>Future</p>', 'status' => 'published', 'published_at' => '2099-01-01 00:00:00']);
        Audit::log('create', 'post', $future, '', null, 'test');
        foreach (['/2020/01/01/public-draft/', '/2099/01/01/public-future/'] as $url) assert_same(404, $visitor->get($url)['status']);
        $blog = $visitor->get('/blog/')['body']; assert_not_contains('PRIVATE DRAFT', $blog); assert_not_contains('PRIVATE FUTURE', $blog);
        $sitemap = $visitor->get('/sitemap.xml')['body']; assert_not_contains($path, $sitemap); assert_not_contains('public-future', $sitemap);
    } finally {
        site_panel_save_trip($owner, (int) $trip['id'], ['seo_title' => $trip['seo_title'] ?? '', 'noindex' => (string) $trip['noindex'], 'status' => $trip['status']]);
        foreach ([$draft, $future] as $id) if ($id) Db::run('DELETE FROM posts WHERE id = ?', [$id]);
        Audit::log('update', 'trip', $trip['id'], '', null, 'test');
    }
    $restored = site_semantics($visitor->get($path)['body']); $original = site_semantics($before['body']);
    foreach (['title', 'meta', 'headings', 'text', 'hrefs', 'images'] as $field) assert_same($original[$field], $restored[$field], "restored $field reaches next request");
});

test('public settings and page SEO overrides reach the next request', function () use ($base) {
    $visitor = new Browser($base); $old = []; $override = null;
    foreach (['email', 'google_site_verification', 'bing_site_verification', 'ga4_id'] as $key) $old[$key] = Settings::get($key);
    try {
        Settings::set('email', 'live-site@example.com');
        Settings::set('google_site_verification', 'public-google'); Settings::set('bing_site_verification', 'public-bing'); Settings::set('ga4_id', 'G-TEST1234');
        Audit::log('update', 'settings', null, '', null, 'test');
        $home = $visitor->get('/')['body'];
        assert_contains('public-google', $home); assert_contains('public-bing', $home); assert_contains('G-TEST1234', $home);
        assert_contains('live-site@example.com', $visitor->get('/contact-us/')['body']);
        $override = '/about-us/'; Db::insert('seo_overrides', ['path' => '/about-us/', 'title' => 'Live override & title', 'description' => 'Changed description', 'noindex' => 1]);
        Audit::log('create', 'seo_override', $override, '', null, 'test');
        $html = $visitor->get('/about-us/')['body'];
        assert_contains('<title>Live override &amp; title</title>', $html); assert_contains('Changed description', $html); assert_contains('noindex', $html);
        assert_not_contains('https://booknilecruises.net/about-us/', $visitor->get('/sitemap.xml')['body']);
        Db::update('seo_overrides', ['title' => 'Second override'], 'path = ?', [$override]);
        Audit::log('update', 'seo_override', $override, '', null, 'test');
        assert_contains('<title>Second override</title>', $visitor->get('/about-us/')['body']);
    } finally {
        foreach ($old as $key => $value) Settings::set($key, $value);
        if ($override) Db::run('DELETE FROM seo_overrides WHERE path = ?', [$override]);
        Audit::log('update', 'settings', null, '', null, 'test');
    }
});

// A deadline must invalidate a cached 404/listing without an edit or publish job.
test('scheduled publication becomes visible when its deadline passes', function () use ($base) {
    $visitor = new Browser($base);
    $url = '/2026/10/06/public-deadline/';
    $id = Db::insert('posts', ['slug' => 'public-deadline', 'url' => $url, 'title' => 'Public deadline marker', 'content_html' => '<p>Published deadline content.</p>', 'status' => 'published', 'published_at' => date('Y-m-d H:i:s', time() + 3)]);
    Audit::log('create', 'post', $id, '', null, 'test');
    try {
        assert_same(404, $visitor->get($url)['status']);
        assert_not_contains('Public deadline marker', $visitor->get('/blog/')['body']);
        $versionBefore = (int) Db::value("SELECT `value` FROM settings WHERE `key` = 'content_version'");
        sleep(4);
        assert_same(200, $visitor->get($url)['status']);
        assert_contains('Public deadline marker', $visitor->get('/blog/')['body']);
        assert_contains($url, $visitor->get('/sitemap.xml')['body']);
        assert_true((int) Db::value("SELECT `value` FROM settings WHERE `key` = 'content_version'") > $versionBefore, 'deadline bumps the content version');
    } finally { Db::run('DELETE FROM posts WHERE id = ?', [$id]); Audit::log('delete', 'post', $id, '', null, 'test'); }
});

function site_panel_save_trip(Browser $owner, int $id, array $changes): void
{
    $path = "/admin/trips/$id/edit";
    $page = $owner->get($path); assert_same(200, $page['status']);
    $pairs = [];
    foreach (form_fields($page['body'], "/trips/$id/edit") as $pair) {
        $name = rawurldecode(explode('=', $pair, 2)[0]);
        if (!array_key_exists($name, $changes)) $pairs[] = $pair;
    }
    foreach ($changes as $name => $value) if ($name !== 'noindex' || $value === '1') $pairs[] = rawurlencode($name) . '=' . rawurlencode((string) $value);
    $saved = $owner->postRaw($path, implode('&', $pairs));
    assert_same(303, $saved['status'], 'owner trip edit saves');
}

test('public noindex configuration invalidates cached HTML and robots without a content edit', function () use ($base) {
    $visitor = new Browser($base);
    assert_contains('max-image-preview:large', $visitor->get('/')['body']);
    assert_contains('Allow: /', $visitor->get('/robots.txt')['body']);
    $old = phase5_config(['site_noindex' => true]);
    try {
        assert_contains('content="noindex, nofollow"', $visitor->get('/')['body']);
        assert_same("User-agent: *\nDisallow: /\n", $visitor->get('/robots.txt')['body']);
    } finally { phase5_restore($old); }
    assert_contains('max-image-preview:large', $visitor->get('/')['body']);
    assert_contains('Allow: /', $visitor->get('/robots.txt')['body']);
});

test('IndexNow ownership file exists only while its setting is enabled', function () use ($base) {
    $visitor = new Browser($base); $old = Settings::get('indexnow_key', '');
    $key = 'test-public-indexnow-key';
    assert_same(404, $visitor->get('/'.$key.'.txt')['status']);
    try {
        Settings::set('indexnow_key', $key); Audit::log('update', 'settings', null, '', null, 'test');
        $file = $visitor->get('/'.$key.'.txt'); assert_same(200, $file['status']); assert_same($key, $file['body']);
    } finally { Settings::set('indexnow_key', $old); Audit::log('update', 'settings', null, '', null, 'test'); }
    assert_same(404, $visitor->get('/'.$key.'.txt')['status']);
});

test('site cache: lock-free reads, newly scheduled post goes live on time, 404s are not cached', function () use ($base) {
    $dir = (string) (\Bnc\Config::get('site_cache_dir') ?: BNC_APP . '/cache/site');
    $count = fn () => count(glob($dir . '/*.json') ?: []);
    $b = new Browser($base);
    $b->get('/');
    $before = $count();
    for ($i = 0; $i < 5; $i++) assert_same(404, $b->get('/no-such-page-' . bin2hex(random_bytes(4)) . '/')['status']);
    assert_same($before, $count(), '404 pages are not cached');

    // Schedule a post a few seconds ahead through the database and a content write, as the panel does.
    $slug = 'cache-timing-' . bin2hex(random_bytes(3));
    $when = date('Y-m-d H:i:s', time() + 3);
    $url = '/' . date('Y/m/d', time() + 3) . '/' . $slug . '/';
    \Bnc\Db::insert('posts', ['slug' => $slug, 'url' => $url, 'title' => 'Cache timing', 'status' => 'published', 'content_html' => '<p>x</p>', 'published_at' => $when]);
    \Bnc\Audit::log('create', 'post', null, 'test');
    assert_not_contains($slug, $b->get('/blog/')['body'], 'not visible before its time');
    sleep(4);
    assert_contains($slug, $b->get('/blog/')['body'], 'visible once due, without any further edit');
});

test('public pages revalidate every time and answer 304 when unchanged', function () use ($base) {
    $b = new Browser($base);
    $r = $b->get('/trip/');
    assert_contains('Cache-Control: no-cache', $r['headers']);
    preg_match('/^ETag:\s*(\S+)/mi', $r['headers'], $m);
    assert_true(isset($m[1]), 'ETag sent');
    $ch = curl_init($base . '/trip/');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_PROXY => '', CURLOPT_NOPROXY => '*', CURLOPT_HTTPHEADER => ['If-None-Match: ' . $m[1]]]);
    curl_exec($ch);
    assert_same(304, curl_getinfo($ch, CURLINFO_RESPONSE_CODE));
});
