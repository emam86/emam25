<?php
declare(strict_types=1);

use Bnc\{Db, Settings};
use Bnc\Seo\{Checker, IndexNow, IndexNowClient};
use Bnc\Publish\Publisher;

function seo_issue(array $issues, string $code, string $entity, int|string $id, string $severity): array
{
    foreach ($issues as $issue) if ($issue['code'] === $code && $issue['entity'] === $entity && (string) $issue['entity_id'] === (string) $id) {
        assert_same($severity, $issue['severity']);
        assert_same(['severity', 'code', 'entity', 'entity_id', 'title', 'message', 'edit_url'], array_keys($issue));
        return $issue;
    }
    throw new AssertionFailed("Missing $code for $entity #$id");
}

test('SEO checker covers published content metadata photos words categories settings links and redirect graphs', function () {
    $ids = []; $redirectIds = []; $termIds = []; $postIds = [];
    $settings = [];
    foreach (['google_site_verification', 'ga4_id', 'indexnow_key'] as $key) { $settings[$key] = Settings::get($key); Settings::set($key, ''); }
    $media = Db::insert('media', ['path' => '/images/seo-test.jpg', 'alt' => '']);
    try {
        $empty = $ids[] = Db::insert('trips', ['slug' => 'seo-empty', 'title' => 'Empty SEO', 'status' => 'published', 'noindex' => 1]);
        $rich = $ids[] = Db::insert('trips', ['slug' => 'seo-rich', 'title' => 'Rich SEO', 'status' => 'published', 'seo_title' => str_repeat('ع', 61), 'seo_description' => 'Duplicate', 'image_id' => $media, 'gallery' => json_encode([$media]), 'overview_html' => '<p><a href="/trip/seo-draft/">Draft</a> <a href="https://booknilecruises.net/trip/missing/?x=1">Missing</a> <a href="/seo-old/">Old</a> <a href="https://other.example/trip/missing/">External</a></p>', 'itinerary' => json_encode([['html' => '<a href="/activities/seo-missing/">Missing category</a>']]), 'faqs' => json_encode([['a' => '<a href="/2020/01/01/seo-missing/">Missing post</a>']])]);
        $draft = $ids[] = Db::insert('trips', ['slug' => 'seo-draft', 'title' => 'Draft SEO']);
        $term = $termIds[] = Db::insert('terms', ['taxonomy' => 'activities', 'slug' => 'seo-term', 'url' => '/activities/seo-term/', 'name' => 'SEO term', 'seo_title' => str_repeat('ع', 61), 'seo_description' => 'Duplicate']);
        $unused = $termIds[] = Db::insert('terms', ['taxonomy' => 'destination', 'slug' => 'seo-unused', 'url' => '/destinations/seo-unused/', 'name' => 'Unused SEO']);
        Db::insert('trip_terms', ['trip_id' => $rich, 'term_id' => $term]);
        $post = $postIds[] = Db::insert('posts', ['slug' => 'seo-post', 'url' => '/2020/01/01/seo-post/', 'title' => 'SEO post', 'status' => 'published', 'published_at' => '2020-01-01 00:00:00', 'content_html' => '<a href="/destinations/seo-missing/">Missing</a>', 'seo_title' => str_repeat('ع', 61), 'seo_description' => 'Duplicate', 'image_id' => $media, 'noindex' => 1]);
        $long = $postIds[] = Db::insert('posts', ['slug' => 'seo-long', 'url' => '/2020/01/01/seo-long/', 'title' => 'Long SEO', 'status' => 'published', 'content_html' => '', 'seo_description' => str_repeat('d', 161)]);
        $future = $postIds[] = Db::insert('posts', ['slug' => 'seo-future', 'url' => '/2099/01/01/seo-future/', 'title' => 'Future SEO', 'status' => 'published', 'content_html' => '', 'published_at' => '2099-01-01 00:00:00']);
        foreach (['/seo-old/' => '/seo-next/', '/seo-next/' => '/blog/', '/seo-loop-a/' => '/seo-loop-b/?x=1', '/seo-loop-b/' => '/seo-loop-a/', '/trip/seo-rich/' => '/blog/'] as $from => $to) $redirectIds[] = Db::insert('redirects', ['from_path' => $from, 'to_path' => $to]);
        $report = (new Checker())->run(); $issues = json_decode($report['issues'], true);
        foreach (['title_missing' => 'warning', 'description_missing' => 'error', 'cover_missing' => 'error', 'gallery_short' => 'warning', 'content_short' => 'warning', 'category_missing' => 'error', 'price_missing' => 'warning', 'noindex' => 'info'] as $code => $severity) seo_issue($issues, $code, 'trip', $empty, $severity);
        foreach (['title_long' => 'warning', 'description_length' => 'warning', 'duplicate_title' => 'error', 'duplicate_description' => 'error', 'alt_missing' => 'warning', 'broken_link' => 'error', 'link_redirected' => 'warning'] as $code => $severity) seo_issue($issues, $code, 'trip', $rich, $severity);
        assert_same('/media/' . $media, seo_issue($issues, 'alt_missing', 'trip', $rich, 'warning')['edit_url']);
        assert_contains('SEO post', seo_issue($issues, 'duplicate_title', 'trip', $rich, 'error')['message']);
        foreach (['duplicate_title' => 'error', 'duplicate_description' => 'error', 'alt_missing' => 'warning', 'content_short' => 'warning', 'broken_link' => 'error', 'noindex' => 'info'] as $code => $severity) seo_issue($issues, $code, 'post', $post, $severity);
        seo_issue($issues, 'description_length', 'post', $long, 'warning');
        seo_issue($issues, 'duplicate_title', 'term', $term, 'error');
        seo_issue($issues, 'category_empty', 'term', $unused, 'info'); seo_issue($issues, 'category_description_missing', 'term', $unused, 'warning');
        seo_issue($issues, 'redirect_chain', 'redirect', $redirectIds[0], 'error');
        seo_issue($issues, 'redirect_loop', 'redirect', $redirectIds[2], 'error'); seo_issue($issues, 'redirect_loop', 'redirect', $redirectIds[3], 'error');
        seo_issue($issues, 'redirect_live', 'redirect', $redirectIds[4], 'warning');
        foreach (['google_verification_missing' => ['google_site_verification', 'warning'], 'ga4_missing' => ['ga4_id', 'info'], 'indexnow_missing' => ['indexnow_key', 'info']] as $code => [$key, $severity]) seo_issue($issues, $code, 'settings', $key, $severity);
        assert_same(4, count(array_filter($issues, fn ($i) => $i['code'] === 'broken_link' && $i['entity'] === 'trip' && (int) $i['entity_id'] === $rich)));
        assert_same([], array_values(array_filter($issues, fn ($i) => ($i['entity'] === 'trip' && (int) $i['entity_id'] === $draft) || ($i['entity'] === 'post' && (int) $i['entity_id'] === $future))));
        $counts = array_count_values(array_column($issues, 'severity'));
        assert_same($counts['error'], (int) $report['error_count']); assert_same($counts['warning'], (int) $report['warning_count']);
        assert_same($report['issues'], Db::value('SELECT issues FROM seo_reports WHERE id = ?', [$report['id']]));
        // Boundary values and enough content/photos should remove their warnings.
        $photos = [$media];
        for ($n = 0; $n < 2; $n++) $photos[] = Db::insert('media', ['path' => '/images/seo-extra-' . $n . '.jpg', 'alt' => 'Description']);
        Db::update('media', ['alt' => 'Description'], 'id = ?', [$media]);
        Db::update('trips', ['seo_title' => str_repeat('t', 60), 'seo_description' => str_repeat('s', 70), 'overview_html' => str_repeat('كلمة ', 150), 'gallery' => json_encode($photos), 'price' => 100, 'itinerary' => '[]', 'faqs' => '[]'], 'id = ?', [$rich]);
        Db::update('posts', ['seo_title' => 'Unique post', 'seo_description' => str_repeat('p', 160), 'content_html' => str_repeat('word ', 300)], 'id = ?', [$post]);
        $clean = json_decode((new Checker())->run()['issues'], true);
        $unwanted = ['title_long', 'description_length', 'content_short', 'alt_missing', 'gallery_short', 'category_missing', 'price_missing', 'broken_link'];
        assert_same([], array_values(array_filter($clean, fn ($i) => in_array($i['code'], $unwanted, true) && (($i['entity'] === 'trip' && (int) $i['entity_id'] === $rich) || ($i['entity'] === 'post' && (int) $i['entity_id'] === $post)))));
        foreach (array_slice($photos, 1) as $photo) Db::run('DELETE FROM media WHERE id = ?', [$photo]);
    } finally {
        foreach ($ids as $id) Db::run('DELETE FROM trips WHERE id = ?', [$id]);
        foreach ($postIds as $id) Db::run('DELETE FROM posts WHERE id = ?', [$id]);
        foreach ($termIds as $id) Db::run('DELETE FROM terms WHERE id = ?', [$id]);
        foreach ($redirectIds as $id) Db::run('DELETE FROM redirects WHERE id = ?', [$id]);
        Db::run('DELETE FROM media WHERE id = ?', [$media]);
        foreach ($settings as $key => $value) Settings::set($key, $value);
    }
});

test('SEO report storage prunes to thirty and CLI uses the test DB with BNC_CONFIG', function () {
    Db::run('DELETE FROM seo_reports');
    for ($i = 0; $i < 32; $i++) Db::insert('seo_reports', ['issues' => '[]']);
    $report = (new Checker())->run();
    assert_same(30, (int) Db::value('SELECT COUNT(*) FROM seo_reports'));
    assert_same((int) $report['id'] - 29, (int) Db::value('SELECT MIN(id) FROM seo_reports'));
    exec('BNC_CONFIG=' . escapeshellarg((string) getenv('BNC_CONFIG')) . ' ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/app/bin/seo-check.php') . ' 2>&1', $output, $code);
    assert_same(0, $code, implode("\n", $output)); assert_contains('SEO report #', implode("\n", $output));
    assert_same((int) $report['id'] + 1, (int) Db::value('SELECT MAX(id) FROM seo_reports'));
});

test('SEO report permissions CSRF run filters dashboard email validation and key generation', function () use ($base) {
    $owner = $GLOBALS['ownerBrowser'];
    foreach (['seo', 'sales'] as $role) {
        $browser = new Browser($base); $browser->get('/admin/login');
        $browser->post('/admin/login', ['email' => "phase-$role@example.com", 'password' => 'phase-password-123']);
        assert_same($role === 'seo' ? 200 : 403, $browser->get('/admin/seo/report')['status']);
        assert_same($role === 'seo' ? 303 : 403, $browser->post('/admin/seo/report', ['action' => 'run'])['status']);
    }
    assert_same(400, $owner->post('/admin/seo/report', ['action' => 'run'], false)['status']);
    $before = (int) Db::value('SELECT MAX(id) FROM seo_reports');
    assert_same(303, $owner->post('/admin/seo/report', ['action' => 'run'])['status']);
    assert_same($before + 1, (int) Db::value('SELECT MAX(id) FROM seo_reports'));
    assert_contains('فحص SEO', $owner->get('/admin/')['body']);
    $page = $owner->get('/admin/seo/report?severity=info'); assert_same(200, $page['status']); assert_not_contains('<h2>أخطاء</h2>', $page['body']); assert_contains('آخر التقارير', $page['body']);
    assert_same(422, $owner->post('/admin/seo/report', ['action' => 'email', 'seo_report_email' => "bad\r\nmail"])['status']);
    assert_same(303, $owner->post('/admin/seo/report', ['action' => 'email', 'seo_report_email' => 'seo@example.com'])['status']);
    Settings::reset(); assert_same('seo@example.com', Settings::get('seo_report_email'));
    assert_same(303, $owner->post('/admin/seo/report', ['action' => 'generate'])['status']);
    Settings::reset(); assert_true((bool) preg_match('/^[a-f0-9]{32}$/D', Settings::get('indexnow_key')));
    assert_same(Settings::get('indexnow_key'), \Bnc\Content\Exporter::build()['settings']['indexnow_key']);
    Settings::set('indexnow_key', '');
});

test('SEO API requires export token validates email and returns stored counts', function () {
    $old = phase5_config(['export_token' => str_repeat('s', 40)]);
    try {
        assert_same(401, api_http('POST', '/api/seo/check', new stdClass())['status']);
        assert_same(401, api_http('POST', '/api/seo/check', new stdClass(), api_headers(api_test_key(['trips.read'])))['status']);
        assert_same(422, api_http('POST', '/api/seo/check', ['email' => 'yes'], api_headers(str_repeat('s', 40)))['status']);
        $response = api_http('POST', '/api/seo/check', ['email' => false], api_headers(str_repeat('s', 40)));
        assert_same(200, $response['status']);
        $report = Db::one('SELECT * FROM seo_reports WHERE id = ?', [$response['json']['report_id']]);
        assert_same((int) $report['error_count'], $response['json']['errors']); assert_same((int) $report['warning_count'], $response['json']['warnings']);
    } finally { phase5_restore($old); }
});

test('IndexNow selects only changes after previous success and callback submits exact body once through fake', function () {
    $key = Settings::get('indexnow_key');
    $before = '2090-01-01 00:00:00'; $after = '2090-01-02 00:00:00';
    $previous = Db::insert('publish_jobs', ['triggered_by' => 'seo-test', 'status' => 'succeeded', 'finished_at' => $before, 'created_at' => '2090-01-01 00:00:01']);
    $failed = Db::insert('publish_jobs', ['triggered_by' => 'seo-test', 'status' => 'failed', 'finished_at' => $after]);
    $job = Db::insert('publish_jobs', ['triggered_by' => 'seo-test']);
    $unchanged = Db::insert('trips', ['slug' => 'indexnow-old', 'title' => 'Old', 'updated_at' => $before]);
    $trip = Db::insert('trips', ['slug' => 'indexnow-new', 'title' => 'New', 'status' => 'published', 'updated_at' => $after]);
    $post = Db::insert('posts', ['slug' => 'indexnow-post', 'url' => '/2020/01/01/indexnow-post/', 'title' => 'New post', 'status' => 'published', 'published_at' => '2020-01-01 00:00:00', 'content_html' => '', 'updated_at' => $after]);
    $term = Db::insert('terms', ['slug' => 'indexnow-term', 'taxonomy' => 'activities', 'url' => '/activities/indexnow-term/', 'name' => 'Term', 'updated_at' => $after]);
    Db::insert('trip_terms', ['trip_id' => $trip, 'term_id' => $term]);
    $redirect = Db::insert('redirects', ['from_path' => '/indexnow-source/', 'to_path' => '/blog/', 'created_at' => $after]);
    $oldRedirect = Db::insert('redirects', ['from_path' => '/indexnow-old-source/', 'to_path' => '/blog/', 'created_at' => $before]);
    $fake = new class implements IndexNowClient {
        public array $bodies = [];
        public function submit(array $body): int { $this->bodies[] = $body; return 200; }
    };
    try {
        Settings::set('indexnow_key', str_repeat('a', 32));
        // Use a prior date so callback's current finished_at follows the previous success.
        Db::update('publish_jobs', ['finished_at' => '2000-01-01 00:00:00', 'created_at' => '2000-01-01 00:00:00'], 'id = ?', [$previous]);
        Publisher::status(['job_id' => $job, 'status' => 'succeeded', 'run_url' => 'https://github.com/example/nile/actions/runs/seo', 'message' => 'Done'], $fake);
        assert_same(1, count($fake->bodies));
        assert_contains('IndexNow:', Db::value('SELECT message FROM publish_jobs WHERE id = ?', [$job]));
        Publisher::status(['job_id' => $job, 'status' => 'succeeded', 'run_url' => 'https://github.com/example/nile/actions/runs/seo', 'message' => 'Done'], $fake);
        assert_same(1, count($fake->bodies));
        Db::update('publish_jobs', ['finished_at' => $before, 'created_at' => '2090-01-01 00:00:01'], 'id = ?', [$previous]);
        Db::update('publish_jobs', ['finished_at' => '2090-01-03 00:00:00'], 'id = ?', [$job]);
        $fake->bodies = []; IndexNow::submit($job, $fake);
        $expected = ['https://booknilecruises.net/trip/indexnow-new/', 'https://booknilecruises.net/2020/01/01/indexnow-post/', 'https://booknilecruises.net/activities/indexnow-term/', 'https://booknilecruises.net/indexnow-source/'];
        assert_same(['host' => 'booknilecruises.net', 'key' => str_repeat('a', 32), 'keyLocation' => 'https://booknilecruises.net/' . str_repeat('a', 32) . '.txt', 'urlList' => $expected], $fake->bodies[0]);
        assert_contains('IndexNow: 4 URLs, HTTP 200', Db::value('SELECT message FROM publish_jobs WHERE id = ?', [$job]));
        Settings::set('indexnow_key', ''); IndexNow::submit($job, $fake); assert_same(1, count($fake->bodies));
        Settings::set('indexnow_key', str_repeat('a', 32));
        Db::update('publish_jobs', ['finished_at' => '2099-01-01 00:00:00', 'created_at' => '2099-01-01 00:00:00'], 'id = ?', [$previous]);
        Db::update('publish_jobs', ['finished_at' => '2099-01-02 00:00:00'], 'id = ?', [$job]);
        IndexNow::submit($job, $fake); assert_same(1, count($fake->bodies));
    } finally {
        Settings::set('indexnow_key', $key);
        foreach ([$previous, $failed, $job] as $id) Db::run('DELETE FROM publish_jobs WHERE id = ?', [$id]);
        foreach ([$unchanged, $trip] as $id) Db::run('DELETE FROM trips WHERE id = ?', [$id]);
        Db::run('DELETE FROM posts WHERE id = ?', [$post]); Db::run('DELETE FROM terms WHERE id = ?', [$term]);
        foreach ([$redirect, $oldRedirect] as $id) Db::run('DELETE FROM redirects WHERE id = ?', [$id]);
    }
});

test('weekly email uses shared mail transport top twenty panel links recipient fallbacks API and CLI', function () {
    $file = __DIR__ . '/tmp/seo-mail.json';
    $old = phase5_config(['export_token' => str_repeat('m', 40), 'mail' => ['from' => 'panel@example.com', 'notify' => 'fallback@example.com', 'fake' => $file]]);
    $oldSeo = Settings::get('seo_report_email'); $oldEnquiry = Settings::get('enquiry_notify_email');
    try {
        $issues = [];
        for ($i = 0; $i < 25; $i++) $issues[] = ['severity' => $i === 24 ? 'error' : 'warning', 'code' => 'test', 'entity' => 'trip', 'entity_id' => $i, 'title' => 'Item ' . $i, 'message' => 'أصلح العنوان', 'edit_url' => '/trips/' . $i . '/edit'];
        $report = ['id' => 1, 'error_count' => 1, 'warning_count' => 24, 'issues' => json_encode($issues)];
        Settings::set('seo_report_email', 'seo@example.com');
        \Bnc\Seo\ReportMailer::send($report);
        $record = json_decode(file_get_contents($file), true);
        assert_same('seo@example.com', $record['to']); assert_same('panel@example.com', $record['from']);
        assert_contains('أخطاء: 1، تحذيرات: 24', $record['body']); assert_same(20, substr_count($record['body'], '/edit'));
        assert_contains('https://booknilecruises.net/admin/trips/24/edit', $record['body']); assert_not_contains('/trips/23/edit', $record['body']);
        Settings::set('seo_report_email', ''); Settings::set('enquiry_notify_email', 'enquiries@example.com');
        \Bnc\Seo\ReportMailer::send($report); assert_same('enquiries@example.com', json_decode(file_get_contents($file), true)['to']);
        Settings::set('enquiry_notify_email', '');
        assert_same(200, api_http('POST', '/api/seo/check', ['email' => true], api_headers(str_repeat('m', 40)))['status']);
        assert_same('fallback@example.com', json_decode(file_get_contents($file), true)['to']);
        unlink($file);
        exec('BNC_CONFIG=' . escapeshellarg((string) getenv('BNC_CONFIG')) . ' ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/app/bin/seo-check.php') . ' --email 2>&1', $out, $code);
        assert_same(0, $code, implode("\n", $out)); assert_true(is_file($file));
    } finally { Settings::set('seo_report_email', $oldSeo); Settings::set('enquiry_notify_email', $oldEnquiry); phase5_restore($old); }
});

test('IndexNow first publish selects content and batches ten thousand with failed HTTP recorded', function () {
    $oldKey = Settings::get('indexnow_key');
    $fake = new class implements IndexNowClient {
        public array $bodies = [];
        public function submit(array $body): int { $this->bodies[] = $body; return 503; }
    };
    Db::pdo()->beginTransaction();
    try {
        Db::run("UPDATE publish_jobs SET status = 'failed'");
        $job = Db::insert('publish_jobs', ['status' => 'succeeded', 'finished_at' => '2099-01-02 00:00:00', 'triggered_by' => 'first-publish']);
        $trip = Db::one('SELECT slug FROM trips LIMIT 1');
        assert_true(in_array('https://booknilecruises.net/trip/' . $trip['slug'] . '/', IndexNow::urls($job), true));
        Db::insert('publish_jobs', ['status' => 'succeeded', 'finished_at' => '2099-01-01 00:00:00', 'triggered_by' => 'previous-publish', 'created_at' => '2099-01-01 00:00:00']);
        for ($offset = 0; $offset < 10001; $offset += 1000) {
            $values = []; $params = [];
            for ($i = $offset; $i < min($offset + 1000, 10001); $i++) {
                $values[] = '(?, ?, ?)'; array_push($params, '/seo-batch-' . $i . '/', '/blog/', '2099-01-02 00:00:00');
            }
            Db::run('INSERT INTO redirects (from_path, to_path, created_at) VALUES ' . implode(',', $values), $params);
        }
        Settings::set('indexnow_key', str_repeat('b', 32));
        IndexNow::submit($job, $fake);
        assert_same(2, count($fake->bodies));
        assert_same(10000, count($fake->bodies[0]['urlList'])); assert_same(1, count($fake->bodies[1]['urlList']));
        assert_contains('IndexNow: 10000 URLs, HTTP 503', Db::value('SELECT message FROM publish_jobs WHERE id = ?', [$job]));
        assert_contains('IndexNow: 1 URLs, HTTP 503', Db::value('SELECT message FROM publish_jobs WHERE id = ?', [$job]));
        assert_same('succeeded', Db::value('SELECT status FROM publish_jobs WHERE id = ?', [$job]));
    } finally { Db::pdo()->rollBack(); Settings::reset(); assert_same($oldKey, Settings::get('indexnow_key')); }
});


test('SEO paths follow configured host and empty report recipient skips audit', function () {
    $old = phase5_config(['site_url' => 'https://cruises.example', 'mail' => ['notify' => '']]);
    $seo = Settings::get('seo_report_email'); $enquiry = Settings::get('enquiry_notify_email');
    try {
        assert_same('/blog/', Checker::path('https://cruises.example/blog/'));
        assert_same(null, Checker::path('https://booknilecruises.net/blog/'));
        Settings::set('seo_report_email', ''); Settings::set('enquiry_notify_email', '');
        $before = (int) Db::value("SELECT COUNT(*) FROM audit_log WHERE action = 'notification_failed'");
        \Bnc\Seo\ReportMailer::send(['id' => 1, 'error_count' => 0, 'warning_count' => 0, 'issues' => '[]']);
        assert_same($before, (int) Db::value("SELECT COUNT(*) FROM audit_log WHERE action = 'notification_failed'"));
    } finally { Settings::set('seo_report_email', $seo); Settings::set('enquiry_notify_email', $enquiry); phase5_restore($old); }
});

test('publish window includes edits during previous build scheduled posts public categories and bounded redirects', function () {
    $old = phase5_config(['site_url' => 'https://cruises.example']);
    $oldKey = Settings::get('indexnow_key');
    $ids = []; $posts = []; $terms = []; $redirects = [];
    try {
        $from = '2020-01-01 00:00:00'; $to = '2020-01-03 00:00:00';
        foreach (['published', 'draft'] as $status) $ids[] = Db::insert('trips', ['slug' => 'review-window-' . $status, 'title' => 'Window', 'status' => $status, 'updated_at' => '2020-01-01 12:00:00']);
        foreach (['published', 'draft'] as $status) $posts[] = Db::insert('posts', ['slug' => 'review-window-' . $status, 'url' => '/review-post-' . $status . '/', 'title' => 'Window', 'content_html' => '', 'status' => $status, 'updated_at' => '2019-01-01 00:00:00', 'published_at' => '2020-01-02 00:00:00']);
        $posts[] = Db::insert('posts', ['slug' => 'review-future', 'url' => '/review-future/', 'title' => 'Future', 'content_html' => '', 'status' => 'published', 'updated_at' => $from, 'published_at' => '2020-01-04 00:00:00']);
        foreach (['used', 'empty'] as $slug) $terms[] = Db::insert('terms', ['slug' => 'review-' . $slug, 'name' => $slug, 'taxonomy' => 'activities', 'url' => '/review-' . $slug . '/', 'updated_at' => $from]);
        Db::insert('trip_terms', ['trip_id' => $ids[0], 'term_id' => $terms[0]]);
        foreach (['2020-01-02 00:00:00', '2020-01-04 00:00:00'] as $i => $at) $redirects[] = Db::insert('redirects', ['from_path' => '/review-redirect-' . $i . '/', 'to_path' => '/blog/', 'created_at' => $at]);
        $result = \Bnc\Publish\Changes::since($from, $to);
        assert_same(['https://cruises.example/trip/review-window-published/', 'https://cruises.example/review-post-published/', 'https://cruises.example/review-used/', 'https://cruises.example/review-redirect-0/'], $result['urls']);
        $previous = Db::insert('publish_jobs', ['status' => 'succeeded', 'triggered_by' => 'review-window', 'created_at' => $from, 'finished_at' => '2020-01-02 00:00:00']);
        $job = Db::insert('publish_jobs', ['status' => 'succeeded', 'triggered_by' => 'review-window', 'created_at' => $to, 'finished_at' => $to]);
        assert_same([$from, $to], \Bnc\Publish\Changes::window($job));
        Settings::set('indexnow_key', str_repeat('d', 32));
        $fake = new class implements IndexNowClient { public array $bodies = []; public function submit(array $body): int { $this->bodies[] = $body; return 200; } };
        IndexNow::submit($job, $fake);
        assert_same('cruises.example', $fake->bodies[0]['host']);
        assert_same('https://cruises.example/' . str_repeat('d', 32) . '.txt', $fake->bodies[0]['keyLocation']);
    } finally {
        foreach ($ids as $id) Db::run('DELETE FROM trips WHERE id = ?', [$id]);
        foreach ($posts as $id) Db::run('DELETE FROM posts WHERE id = ?', [$id]);
        foreach ($terms as $id) Db::run('DELETE FROM terms WHERE id = ?', [$id]);
        foreach ($redirects as $id) Db::run('DELETE FROM redirects WHERE id = ?', [$id]);
        Db::run("DELETE FROM publish_jobs WHERE triggered_by = 'review-window'"); Settings::set('indexnow_key', $oldKey); phase5_restore($old);
    }
});

test('SEO checker loads post URL lookup once for many links', function () {
    $id = Db::insert('trips', ['slug' => 'review-link-batch', 'title' => 'Batch links', 'status' => 'published', 'overview_html' => str_repeat('<a href="/custom-post/">Link</a>', 5)]);
    try {
        $queries = review_count_queries(fn () => (new Checker())->run());
        assert_same(0, count(array_filter($queries, fn ($sql) => str_contains($sql, 'FROM posts WHERE url ='))));
        assert_same(1, count(array_filter($queries, fn ($sql) => $sql === 'SELECT url FROM posts')));
    } finally { Db::run('DELETE FROM trips WHERE id = ?', [$id]); }
});

test('content audit versions and deferred effects commit atomically and discard rollback callbacks', function () {
    $oldKey = Settings::get('indexnow_key');
    Settings::set('indexnow_key', '');
    $version = (int) Settings::get('content_version', '0');
    $calls = 0;
    try {
        Db::tx(function () use (&$calls): void {
            \Bnc\Audit::log('update', 'settings', null, 'اختبار إبطال الذاكرة');
            Db::afterCommit(function () use (&$calls): void { $calls++; });
            throw new RuntimeException('rollback probe');
        });
    } catch (RuntimeException $e) { assert_same('rollback probe', $e->getMessage()); }
    assert_same($version, (int) Settings::get('content_version', '0'));
    assert_same(0, $calls);
    Db::tx(function () use (&$calls): void {
        \Bnc\Audit::log('update', 'settings', null, 'اختبار إبطال الذاكرة');
        Db::afterCommit(function () use (&$calls): void { $calls++; });
        assert_same(0, $calls);
    });
    assert_same($version + 1, (int) Settings::get('content_version', '0'));
    assert_same(1, $calls);
    Settings::set('indexnow_key', $oldKey);
});

test('direct IndexNow submits deduplicated paths without publish jobs', function () {
    $old = Settings::get('indexnow_key');
    $fake = new class implements IndexNowClient {
        public array $bodies = [];
        public function submit(array $body): int { $this->bodies[] = $body; return 503; }
    };
    try {
        Settings::set('indexnow_key', str_repeat('d', 32));
        IndexNow::submitPaths(['/trip/direct/', '/trip/direct/'], $fake);
        assert_same(1, count($fake->bodies));
        assert_same(['https://booknilecruises.net/trip/direct/'], $fake->bodies[0]['urlList']);
        assert_same('https://booknilecruises.net/' . str_repeat('d', 32) . '.txt', $fake->bodies[0]['keyLocation']);
    } finally { Settings::set('indexnow_key', $old); }
});

test('IndexNow capture limits redirects to relevant old and new paths', function () {
    $unrelated = Db::insert('redirects', ['from_path' => '/indexnow-unrelated/', 'to_path' => '/trip/', 'source' => 'manual']);
    $trip = Db::insert('trips', ['slug' => 'indexnow-relevant', 'title' => 'Relevant', 'status' => 'published']);
    try {
        assert_same(['/trip/indexnow-relevant/', '/trip/indexnow-old/'], IndexNow::contentPaths('trip', $trip, ['old_path' => '/trip/indexnow-old/']));
        assert_same(['/indexnow-new/', '/indexnow-old/'], IndexNow::contentPaths('redirect', 0, ['new' => ['from_path' => '/indexnow-new/'], 'old' => ['from_path' => '/indexnow-old/']]));
        assert_same(['/about-us/'], IndexNow::contentPaths('seo', null, ['path' => '/about-us/']));
        assert_same(['/trip/removed/'], IndexNow::contentPaths('trip', 0, ['old_path' => '/trip/removed/']));
    } finally {
        Db::run('DELETE FROM trips WHERE id = ?', [$trip]);
        Db::run('DELETE FROM redirects WHERE id = ?', [$unrelated]);
    }
});
