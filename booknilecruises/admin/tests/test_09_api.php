<?php
declare(strict_types=1);
use Bnc\{Config, Db};
use Bnc\Api\{ApiApp, Keys, SafeHttp};
use Bnc\Content\Exporter;

/** Replace config for both the HTTP worker and direct service tests. */
function phase5_config(array $changes): array
{
    $file = getenv('BNC_CONFIG');
    $old = require $file;
    $new = array_replace_recursive($old, $changes);
    file_put_contents($file, '<?php return ' . var_export($new, true) . ';');
    Config::load($new);
    return $old;
}
function phase5_restore(array $config): void
{
    file_put_contents(getenv('BNC_CONFIG'), '<?php return ' . var_export($config, true) . ';');
    Config::load($config);
}
function api_http(string $method, string $path, mixed $data = null, array $headers = []): array
{
    global $base;
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_PROXY => '', CURLOPT_NOPROXY => '*', CURLOPT_TIMEOUT => 20]);
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($data) ? $data : json_encode($data, JSON_THROW_ON_ERROR));
        $headers[] = 'Content-Type: application/json';
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $raw = (string) curl_exec($ch); $size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $result = ['status' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'body' => substr($raw, $size), 'headers' => substr($raw, 0, $size)];
    $result['json'] = json_decode($result['body'], true);
    curl_close($ch); return $result;
}
function api_headers(string $token): array { return ['Authorization: Bearer ' . $token]; }
function api_test_key(array $scopes = Keys::SCOPES): string
{
    return Keys::create('Test automation', $scopes, (int) Db::value('SELECT id FROM users LIMIT 1'));
}

test('API export rejects missing short and wrong tokens and returns the exact exporter JSON', function () {
    $old = phase5_config(['export_token' => str_repeat('e', 40)]);
    try {
        assert_same(401, api_http('GET', '/api/export')['status']);
        assert_same(401, api_http('GET', '/api/export', null, api_headers(str_repeat('x', 40)))['status']);
        foreach (['', 'short'] as $token) {
            phase5_config(['export_token' => $token]);
            assert_same(401, api_http('GET', '/api/export', null, api_headers($token))['status']);
        }
        phase5_config(['export_token' => str_repeat('e', 40)]);
        $r = api_http('GET', '/api/export', null, api_headers(str_repeat('e', 40)));
        assert_same(200, $r['status']);
        $expected = json_decode(Exporter::json(), true); $actual = $r['json'];
        unset($expected['generated_at'], $actual['generated_at']); assert_same($expected, $actual);
        assert_contains('application/json', $r['headers']); assert_contains('no-store', $r['headers']);
        assert_not_contains('Set-Cookie:', $r['headers']);
        assert_same(405, api_http('POST', '/api/export', '{}', api_headers(str_repeat('e', 40)))['status']);
        assert_same(404, api_http('GET', '/api/no-such-route')['status']);
    } finally { phase5_restore($old); }
});

test('API uses config base path and JSON validation without session or CSRF', function () {
    $old = phase5_config(['api_path' => '/automation', 'export_token' => str_repeat('e', 40)]);
    $server = $_SERVER;
    try {
        $_SERVER['REQUEST_URI'] = '/automation/export'; assert_same('/export', ApiApp::path());
        $_SERVER['REQUEST_URI'] = '/automation-other/export'; assert_same('/__not_found__', ApiApp::path());
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . str_repeat('e', 40);
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        assert_same(400, ApiApp::dispatch('POST', '/seo/check', '{broken')->status);
        assert_same(400, ApiApp::dispatch('POST', '/seo/check', '[]')->status);
        assert_same(413, ApiApp::dispatch('POST', '/seo/check', str_repeat('a', 1048577))->status);
        assert_same(200, ApiApp::dispatch('POST', '/seo/check', '{}')->status);
    } finally { $_SERVER = $server; phase5_restore($old); }
});

test('API key is displayed once stored hashed supports scopes revocation and rolling rate limits', function () {
    $owner = $GLOBALS['ownerBrowser'];
    assert_same(400, $owner->post('/admin/api-keys', ['name' => 'No csrf', 'scopes' => ['trips.read']], false)['status']);
    assert_same(422, $owner->post('/admin/api-keys', ['name' => 'Bad scope', 'scopes' => ['users.manage']])['status']);
    assert_same(303, $owner->post('/admin/api-keys', ['name' => 'n8n once', 'scopes' => ['trips.read']])['status']);
    $page = $owner->get('/admin/api-keys');
    assert_true((bool) preg_match('/bnc_[a-f0-9]{40}/', $page['body'], $m)); $token = $m[0];
    assert_not_contains($token, $owner->get('/admin/api-keys')['body']);
    $key = Db::one('SELECT * FROM api_keys WHERE name = ?', ['n8n once']);
    assert_same(hash('sha256', $token), $key['key_hash']); assert_same(substr($token, 0, 8), $key['key_prefix']);
    assert_not_contains($token, json_encode(Db::all('SELECT * FROM audit_log')));
    assert_same(200, api_http('GET', '/api/v1/trips', null, ['X-API-Key: ' . $token])['status']);
    assert_true(Db::value('SELECT last_used_at FROM api_keys WHERE id = ?', [$key['id']]) !== null);
    assert_same(403, api_http('GET', '/api/v1/enquiries', null, api_headers($token))['status']);
    assert_same(401, api_http('GET', '/api/v1/trips', null, api_headers('bnc_' . str_repeat('0', 40)))['status']);
    for ($i = 1; $i < 120; $i++) Db::insert('api_requests', ['bucket' => 'key:' . $key['id']]);
    assert_same(429, api_http('GET', '/api/v1/trips', null, api_headers($token))['status']);
    Db::run('UPDATE api_requests SET created_at = ? WHERE bucket = ?', [date('Y-m-d H:i:s', time() - 61), 'key:' . $key['id']]);
    assert_same(200, api_http('GET', '/api/v1/trips', null, api_headers($token))['status']);
    assert_same(303, $owner->post('/admin/api-keys/' . $key['id'] . '/revoke')['status']);
    assert_same(401, api_http('GET', '/api/v1/trips', null, api_headers($token))['status']);
    assert_same(303, $owner->post('/admin/api-keys/' . $key['id'] . '/delete')['status']);
});

test('API posts sanitise HTML allocate unique slugs enforce publishing and exclude scheduled content', function () {
    $token = api_test_key(['posts.write']);
    $input = ['title' => 'API Nile article', 'content_html' => '<p onclick="bad()">Good</p><script>bad()</script>', 'slug' => 'api-nile-article'];
    assert_same(403, api_http('POST', '/api/v1/posts', $input + ['status' => 'published'], api_headers($token))['status']);
    assert_same(422, api_http('POST', '/api/v1/posts', ['title' => 'Missing body'], api_headers($token))['status']);
    $r = api_http('POST', '/api/v1/posts', $input, api_headers($token)); assert_same(201, $r['status']);
    $id = $r['json']['id']; $row = Db::one('SELECT * FROM posts WHERE id = ?', [$id]);
    assert_same('<p>Good</p>', $row['content_html']); assert_same('api:Test automation', $row['source']);
    $duplicate = api_http('POST', '/api/v1/posts', $input, api_headers($token)); assert_same(201, $duplicate['status']);
    assert_same('api-nile-article-2', Db::value('SELECT slug FROM posts WHERE id = ?', [$duplicate['json']['id']]));
    $generated = api_http('POST', '/api/v1/posts', ['title' => 'Generated API Slug', 'content_html' => '<p>Body</p>'], api_headers($token));
    assert_same(201, $generated['status']); assert_contains('generated-api-slug', $generated['json']['url']);
    assert_same(403, api_http('PATCH', '/api/v1/posts/' . $id, ['status' => 'published'], api_headers($token))['status']);
    $publisher = api_test_key(['posts.write', 'posts.publish']);
    $future = api_http('POST', '/api/v1/posts', $input + ['status' => 'published', 'published_at' => (new DateTimeImmutable('+2 days'))->format(DATE_ATOM)], api_headers($publisher));
    assert_same(201, $future['status']); assert_same('scheduled', $future['json']['status']);
    assert_true(!in_array($future['json']['id'], array_column(Exporter::build()['posts'], 'id'), true));
    assert_same(403, api_http('PATCH', '/api/v1/posts/' . $future['json']['id'], ['published_at' => (new DateTimeImmutable('-1 minute'))->format(DATE_ATOM)], api_headers($token))['status']);
    Db::update('posts', ['published_at' => date('Y-m-d H:i:s', time() - 60)], 'id = ?', [$future['json']['id']]);
    assert_true(in_array($future['json']['id'], array_column(Exporter::build()['posts'], 'id'), true));
    assert_same(200, api_http('PATCH', '/api/v1/posts/' . $id, ['status' => 'published'], api_headers($publisher))['status']);
    $oldUrl = Db::value('SELECT url FROM posts WHERE id = ?', [$id]);
    $patch = api_http('PATCH', '/api/v1/posts/' . $id, ['slug' => 'api-renamed', 'content_html' => '<img src="javascript:bad()"><p>Safe</p>'], api_headers($token));
    assert_same(200, $patch['status']); assert_same('<p>Safe</p>', Db::value('SELECT content_html FROM posts WHERE id = ?', [$id]));
    assert_same(Db::value('SELECT url FROM posts WHERE id = ?', [$id]), Db::value('SELECT to_path FROM redirects WHERE from_path = ?', [$oldUrl]));
    $adminPost = (int) Db::value("SELECT id FROM posts WHERE source = 'admin' LIMIT 1");
    assert_same(403, api_http('PATCH', '/api/v1/posts/' . $adminPost, ['title' => 'Forbidden'], api_headers($token))['status']);
    assert_same(404, api_http('PATCH', '/api/v1/posts/999999', ['title' => 'Missing'], api_headers($token))['status']);
    assert_same('api:Test automation', Db::value("SELECT actor FROM audit_log WHERE entity = 'post' AND entity_id = ? ORDER BY id DESC LIMIT 1", [(string) $id]));
    assert_same(422, api_http('POST', '/api/v1/posts', $input + ['image_url' => 'http://example.org/photo.jpg'], api_headers($token))['status']);
    assert_same(422, api_http('POST', '/api/v1/posts', $input + ['image_url' => 'https://127.0.0.1/photo.jpg'], api_headers($token))['status']);
});

test('HTTPS guard blocks private reserved and rebinding targets without network access', function () {
    foreach (['http://example.org/a', 'https://127.0.0.1/a', 'https://[::1]/a', 'https://user:pass@example.org/a'] as $url) {
        try { SafeHttp::target($url); throw new AssertionFailed('URL was not blocked'); }
        catch (RuntimeException $e) { assert_contains('blocked:', $e->getMessage()); }
    }
    try { SafeHttp::target('https://internal.example/a', false, fn () => ['127.0.0.1']); throw new AssertionFailed('Resolved loopback accepted'); }
    catch (RuntimeException $e) { assert_same('blocked: private address', $e->getMessage()); }
    try { SafeHttp::target('https://mixed.example/a', false, fn () => ['8.8.8.8', '10.0.0.1']); throw new AssertionFailed('Mixed DNS accepted'); }
    catch (RuntimeException $e) { assert_same('blocked: private address', $e->getMessage()); }
    assert_same(['public.example', 443, '8.8.8.8'], SafeHttp::target('https://public.example/a', false, fn () => ['8.8.8.8']));
    foreach (['0.0.0.0', '10.0.0.1', '127.0.0.1', '169.254.1.1', '192.168.1.1', '100.64.0.1', '::1', '::ffff:127.0.0.1', 'fe80::1', 'fc00::1', '2001:db8::1', '192.0.0.1', '192.0.2.1', '198.51.100.1', '203.0.113.1', '198.18.0.1', '224.0.0.1', '2001::1', '2002:7f00:1::1'] as $ip) assert_same(false, SafeHttp::publicIp($ip), $ip);
});

test('local downloaded media keeps upload validation re-encoding and API audit attribution', function () {
    $path = __DIR__ . '/tmp/downloaded-api.jpg';
    $image = imagecreatetruecolor(400, 200); imagejpeg($image, $path); imagedestroy($image);
    file_put_contents($path, '<?php echo "unsafe"; ?>', FILE_APPEND);
    $id = (new \Bnc\Media\Uploader())->saveLocal($path, 'Downloaded API.jpg', 'api:local-media');
    $row = Db::one('SELECT * FROM media WHERE id = ?', [$id]);
    $disk = Config::get('images_dir') . substr($row['path'], strlen('/images'));
    assert_not_contains('<?php', file_get_contents($disk)); assert_same(null, $row['uploaded_by']);
    assert_same('api:local-media', Db::value("SELECT actor FROM audit_log WHERE entity = 'media' AND entity_id = ? ORDER BY id DESC LIMIT 1", [(string) $id]));
    assert_true(isset(json_decode($row['sizes'], true)['medium']));
    $bad = __DIR__ . '/tmp/downloaded-fake.jpg'; file_put_contents($bad, '<?php echo 1; ?>');
    try { (new \Bnc\Media\Uploader())->saveLocal($bad, 'fake.jpg', 'api:local-media'); throw new AssertionFailed('Fake image accepted'); }
    catch (RuntimeException $e) { assert_contains('JPEG', $e->getMessage()); }
    $old = phase5_config(['max_upload_mb' => 0.00001]);
    try {
        try { (new \Bnc\Media\Uploader())->saveLocal($path, 'large.jpg', 'api:local-media'); throw new AssertionFailed('Size limit bypassed'); }
        catch (RuntimeException $e) { assert_contains('ميجابايت', $e->getMessage()); }
    } finally { phase5_restore($old); }
});

test('API failures never reveal traces and post creation rolls back when audit fails', function () {
    $token = api_test_key(['posts.write']); $count = (int) Db::value('SELECT COUNT(*) FROM posts');
    Db::run("CREATE TRIGGER phase5_fail_audit BEFORE INSERT ON audit_log FOR EACH ROW BEGIN IF NEW.entity = 'post' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'secret-test-error'; END IF; END");
    try {
        $r = api_http('POST', '/api/v1/posts', ['title' => 'Rollback API post', 'content_html' => '<p>Body</p>'], api_headers($token));
        assert_same(500, $r['status']); assert_same(['error' => 'Internal server error'], $r['json']);
        assert_same($count, (int) Db::value('SELECT COUNT(*) FROM posts'));
        assert_not_contains('secret-test-error', $r['body']); assert_not_contains('Stack trace', $r['body']);
    } finally { Db::run('DROP TRIGGER phase5_fail_audit'); }
});

test('trips automation response contains canonical public fields and never includes drafts', function () {
    $token = api_test_key(['trips.read']);
    $r = api_http('GET', '/api/v1/trips', null, api_headers($token)); assert_same(200, $r['status']);
    $published = Db::all("SELECT * FROM trips WHERE status = 'published' ORDER BY sort_order, id");
    assert_same(count($published), count($r['json']['trips']));
    foreach ($published as $n => $row) {
        $trip = $r['json']['trips'][$n]; assert_same((int) $row['id'], $trip['id']); assert_same($row['title'], $trip['title']);
        assert_same('https://booknilecruises.net/trip/' . $row['slug'] . '/', $trip['url']);
        foreach (['price', 'currency', 'duration_days', 'duration_nights', 'categories', 'cover_image_url'] as $field) assert_true(array_key_exists($field, $trip));
    }
    $server = $_SERVER; $session = $_SESSION ?? null; $status = session_status();
    try {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token; $_SESSION = ['uid' => 1, 'pwv' => 'invalid', 'sentinel' => 'unchanged'];
        assert_same(200, ApiApp::dispatch('GET', '/v1/trips')->status);
        assert_same(['uid' => 1, 'pwv' => 'invalid', 'sentinel' => 'unchanged'], $_SESSION); assert_same($status, session_status());
    } finally { $_SERVER = $server; if ($session === null) unset($_SESSION); else $_SESSION = $session; }
});

test('export token is read when the server only passes REDIRECT_HTTP_AUTHORIZATION', function () {
    $old = phase5_config(['export_token' => str_repeat('r', 40)]);
    $saved = $_SERVER;
    try {
        unset($_SERVER['HTTP_AUTHORIZATION']);
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Bearer ' . str_repeat('r', 40);
        assert_same(200, \Bnc\Api\ApiApp::dispatch('GET', '/export')->status);
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Bearer ' . str_repeat('x', 40);
        assert_same(401, \Bnc\Api\ApiApp::dispatch('GET', '/export')->status);
    } finally {
        $_SERVER = $saved;
        phase5_restore($old);
    }
});

class ReviewCountingPdo extends PDO
{
    public array $queries = [];
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->queries[] = $query;
        return parent::prepare($query, $options);
    }
}
function review_count_queries(callable $fn): array
{
    $property = new ReflectionProperty(Db::class, 'pdo');
    $original = $property->getValue();
    $pdo = new ReviewCountingPdo((string) \Bnc\Config::get('db.dsn'), (string) \Bnc\Config::get('db.user'), (string) \Bnc\Config::get('db.pass'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    $property->setValue(null, $pdo);
    try { $fn(); return $pdo->queries; }
    finally { $property->setValue(null, $original); }
}

test('trip categories use one batch query regardless of trip count', function () {
    $ids = [];
    try {
        for ($i = 0; $i < 3; $i++) $ids[] = Db::insert('trips', ['slug' => 'batch-query-' . $i, 'title' => 'Batch', 'status' => 'published']);
        $queries = review_count_queries(fn () => (new ReflectionMethod(\Bnc\Api\ApiApp::class, 'trips'))->invoke(null));
        $categoryQueries = array_values(array_filter($queries, fn ($sql) => str_contains($sql, 'trip_terms')));
        assert_same(1, count($categoryQueries)); assert_contains(' IN (', $categoryQueries[0]);
    } finally { foreach ($ids as $id) Db::run('DELETE FROM trips WHERE id = ?', [$id]); }
});

test('API post failed save removes downloaded media row and every file', function () {
    $before = (int) Db::value('SELECT COUNT(*) FROM media');
    $dir = (string) \Bnc\Config::get('images_dir');
    $files = glob($dir . '/*'); sort($files);
    Db::run("CREATE TRIGGER review_fail_post BEFORE INSERT ON posts FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'test post failure'");
    try {
        $download = static function (string $url): string {
            $file = tempnam(__DIR__ . '/tmp', 'download-');
            $im = imagecreatetruecolor(1400, 900); imagejpeg($im, $file); imagedestroy($im); return $file;
        };
        try {
            \Bnc\Api\Posts::save(['title' => 'Failed image post', 'content_html' => 'Body', 'image_url' => 'https://example.com/review.jpg'], ['name' => 'review', 'scopes' => []], 0, $download);
            throw new AssertionFailed('save succeeded');
        } catch (PDOException $e) { assert_contains('test post failure', $e->getMessage()); }
        assert_same($before, (int) Db::value('SELECT COUNT(*) FROM media'));
        $after = glob($dir . '/*'); sort($after); assert_same($files, $after);
    } finally { Db::run('DROP TRIGGER review_fail_post'); }
});

test('a user cannot give an API key or webhook more access than they have', function () use ($base) {
    $roleId = \Bnc\Db::insert('roles', ['slug' => 'role-automation-test', 'name' => 'Automation only', 'permissions' => json_encode(['api.manage', 'trips.view'])]);
    \Bnc\Db::insert('users', ['name' => 'Auto', 'email' => 'auto-only@example.com', 'password_hash' => password_hash('password-auto-1', PASSWORD_DEFAULT), 'role_id' => $roleId]);
    $b = new Browser($base);
    $b->post('/admin/login', ['email' => 'auto-only@example.com', 'password' => 'password-auto-1']);
    $before = (int) \Bnc\Db::value('SELECT COUNT(*) FROM api_keys');
    assert_same(422, $b->post('/admin/api-keys', ['name' => 'x', 'scopes' => ['enquiries.read']])['status'], 'enquiries.read refused');
    assert_same(422, $b->post('/admin/api-keys', ['name' => 'x', 'scopes' => ['publish']])['status'], 'publish refused');
    assert_same($before, (int) \Bnc\Db::value('SELECT COUNT(*) FROM api_keys'));
    assert_same(303, $b->post('/admin/api-keys', ['name' => 'trips', 'scopes' => ['trips.read']])['status'], 'held scope allowed');
    assert_not_contains('value="enquiries.read"', $b->get('/admin/api-keys')['body']);
    $r = $b->post('/admin/webhooks/new', ['name' => 'leak', 'url' => 'https://example.com/hook', 'events' => ['enquiry.created'], 'is_active' => '1']);
    assert_same(422, $r['status'], 'enquiry webhook refused');
});
