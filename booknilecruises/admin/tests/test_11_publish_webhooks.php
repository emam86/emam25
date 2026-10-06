<?php
declare(strict_types=1);
use Bnc\{Config, Db};
use Bnc\Publish\{GitHubClient, Publisher};
use Bnc\Webhooks\Delivery;

function publish_test_config(array $extra = []): array
{
    return phase5_config(array_replace_recursive(['github' => ['token' => 'fake-github-secret', 'repo' => 'example/nile', 'workflow' => 'publish-site.yml', 'ref' => 'main', 'fake' => __DIR__ . '/tmp/github-request.json', 'fake_status' => 204], 'export_token' => str_repeat('w', 40)], $extra));
}

test('publish panel dispatches the exact workflow body keeps history and enforces the 20 minute guard', function () {
    $old = publish_test_config(); $owner = $GLOBALS['ownerBrowser'];
    try {
        Db::run('DELETE FROM publish_jobs');
        $page = $owner->get('/admin/publish'); assert_same(200, $page['status']); assert_contains('نشر الموقع الآن', $page['body']); assert_not_contains('fake-github-secret', $page['body']);
        assert_same(400, $owner->post('/admin/publish', [], false)['status']);
        assert_same(303, $owner->post('/admin/publish')['status']);
        $job = Db::one('SELECT * FROM publish_jobs ORDER BY id DESC LIMIT 1'); assert_same('queued', $job['status']); assert_same('owner@example.com', $job['triggered_by']);
        $record = json_decode(file_get_contents(__DIR__ . '/tmp/github-request.json'), true);
        assert_same('https://api.github.com/repos/example/nile/actions/workflows/publish-site.yml/dispatches', $record['url']);
        assert_same(['ref' => 'main', 'inputs' => ['job_id' => (string) $job['id']]], $record['body']);
        assert_not_contains('fake-github-secret', file_get_contents(__DIR__ . '/tmp/github-request.json'));
        assert_same(409, $owner->post('/admin/publish')['status']); assert_same(1, (int) Db::value('SELECT COUNT(*) FROM publish_jobs'));
        Db::update('publish_jobs', ['status' => 'running'], 'id = ?', [$job['id']]); assert_same(409, $owner->post('/admin/publish')['status']);
        Db::update('publish_jobs', ['created_at' => date('Y-m-d H:i:s', time() - 1201)], 'id = ?', [$job['id']]);
        assert_same(303, $owner->post('/admin/publish')['status']); assert_same(2, (int) Db::value('SELECT COUNT(*) FROM publish_jobs'));
        assert_contains('في الانتظار', $owner->get('/admin/')['body']);
        Db::run("UPDATE publish_jobs SET status = 'failed'"); phase5_config(['github' => ['fake_status' => 422]]);
        assert_same(502, $owner->post('/admin/publish')['status']);
        $failure = Db::one('SELECT * FROM publish_jobs ORDER BY id DESC LIMIT 1'); assert_same('failed', $failure['status']); assert_same('Fake GitHub response', $failure['message']); assert_true($failure['finished_at'] !== null);
        $fake = new class implements GitHubClient { public function dispatch(int $jobId): array { return ['status' => 503, 'message' => 'Service unavailable']; } };
        try { Publisher::start('api:test', $fake); throw new AssertionFailed('Failure was accepted'); }
        catch (\Bnc\Api\ApiException $e) { assert_same(502, $e->status); }
        assert_same('Service unavailable', Db::value('SELECT message FROM publish_jobs ORDER BY id DESC LIMIT 1'));
        assert_same('api:test', Db::value("SELECT actor FROM audit_log WHERE entity = 'publish_job' ORDER BY id DESC LIMIT 1"));
        $token = api_test_key(['publish']); phase5_config(['github' => ['fake_status' => 204]]);
        $r = api_http('POST', '/api/v1/publish', new stdClass(), api_headers($token)); assert_same(201, $r['status']); assert_true(isset($r['json']['job_id']));
        assert_same('api:Test automation', Db::value('SELECT triggered_by FROM publish_jobs WHERE id = ?', [$r['json']['job_id']]));
        assert_same(409, api_http('POST', '/api/v1/publish', new stdClass(), api_headers($token))['status']);
        phase5_config(['github' => ['token' => '']]); assert_same(422, api_http('POST', '/api/v1/publish', new stdClass(), api_headers($token))['status']);
    } finally { phase5_restore($old); }
});

test('publish workflow callback validates fields and records running and terminal states idempotently', function () {
    $old = publish_test_config();
    try {
        $id = Db::insert('publish_jobs', ['triggered_by' => 'callback-test']);
        $d = ['job_id' => $id, 'status' => 'running', 'run_url' => 'https://github.com/example/nile/actions/runs/123', 'message' => 'Build started'];
        assert_same(401, api_http('POST', '/api/publish/status', $d)['status']);
        assert_same(200, api_http('POST', '/api/publish/status', $d, api_headers(str_repeat('w', 40)))['status']);
        assert_same('running', Db::value('SELECT status FROM publish_jobs WHERE id = ?', [$id])); assert_same(null, Db::value('SELECT finished_at FROM publish_jobs WHERE id = ?', [$id]));
        foreach ([['job_id' => (string) $id], ['status' => 'bad'], ['run_url' => 'http://github.com/a'], ['run_url' => 'https://github.com.evil/a'], ['message' => str_repeat('a', 256)]] as $change) assert_same(422, api_http('POST', '/api/publish/status', array_replace($d, $change), api_headers(str_repeat('w', 40)))['status']);
        assert_same(404, api_http('POST', '/api/publish/status', array_replace($d, ['job_id' => 999999]), api_headers(str_repeat('w', 40)))['status']);
        $d['status'] = 'succeeded'; $d['message'] = 'Published';
        assert_same(200, api_http('POST', '/api/publish/status', $d, api_headers(str_repeat('w', 40)))['status']);
        assert_same('succeeded', Db::value('SELECT status FROM publish_jobs WHERE id = ?', [$id])); assert_true(Db::value('SELECT finished_at FROM publish_jobs WHERE id = ?', [$id]) !== null);
        assert_same($d['run_url'], Db::value('SELECT run_url FROM publish_jobs WHERE id = ?', [$id]));
        $count = (int) Db::value("SELECT COUNT(*) FROM audit_log WHERE entity = 'publish_job' AND entity_id = ?", [(string) $id]);
        assert_same(200, api_http('POST', '/api/publish/status', $d, api_headers(str_repeat('w', 40)))['status']);
        assert_same($count, (int) Db::value("SELECT COUNT(*) FROM audit_log WHERE entity = 'publish_job' AND entity_id = ?", [(string) $id]));
        assert_same(409, api_http('POST', '/api/publish/status', array_replace($d, ['status' => 'running']), api_headers(str_repeat('w', 40)))['status']);
        assert_same('publish-workflow', Db::value("SELECT actor FROM audit_log WHERE entity = 'publish_job' AND entity_id = ? ORDER BY id DESC LIMIT 1", [(string) $id]));
    } finally { phase5_restore($old); }
});

test('webhooks show new secrets once regenerate block private addresses and support CSRF protected management', function () {
    $owner = $GLOBALS['ownerBrowser'];
    assert_same(422, $owner->post('/admin/webhooks/new', ['name' => 'Bad', 'url' => 'http://example.org', 'events' => ['site.published']])['status']);
    assert_same(303, $owner->post('/admin/webhooks/new', ['name' => 'Private blocked', 'url' => 'https://127.0.0.1/hook', 'events' => ['site.published'], 'is_active' => '1'])['status']);
    $hook = Db::one('SELECT * FROM webhooks ORDER BY id DESC LIMIT 1');
    assert_contains($hook['secret'], $owner->get('/admin/webhooks/' . $hook['id'] . '/edit')['body']);
    assert_not_contains($hook['secret'], $owner->get('/admin/webhooks/' . $hook['id'] . '/edit')['body']);
    assert_not_contains($hook['secret'], $owner->get('/admin/webhooks')['body']);
    assert_same(400, $owner->post('/admin/webhooks/' . $hook['id'] . '/test', [], false)['status']);
    assert_same(303, $owner->post('/admin/webhooks/' . $hook['id'] . '/test')['status']);
    assert_same('blocked: private address', Db::value('SELECT last_status FROM webhooks WHERE id = ?', [$hook['id']]));
    assert_true(Db::value('SELECT last_called_at FROM webhooks WHERE id = ?', [$hook['id']]) !== null);
    assert_same(303, $owner->post('/admin/webhooks/' . $hook['id'] . '/regenerate')['status']);
    $secret = Db::value('SELECT secret FROM webhooks WHERE id = ?', [$hook['id']]); assert_true($secret !== $hook['secret']); assert_same(64, strlen($secret));
    assert_contains($secret, $owner->get('/admin/webhooks/' . $hook['id'] . '/edit')['body']);
    assert_not_contains($secret, json_encode(Db::all('SELECT * FROM audit_log')));
    assert_same(303, $owner->post('/admin/webhooks/' . $hook['id'] . '/edit', ['name' => 'Edited', 'url' => 'https://127.0.0.1/hook', 'events' => ['enquiry.created']])['status']);
    assert_same(0, (int) Db::value('SELECT is_active FROM webhooks WHERE id = ?', [$hook['id']]));
    assert_same(303, $owner->post('/admin/webhooks/' . $hook['id'] . '/delete')['status']);
    assert_same(null, Db::value('SELECT id FROM webhooks WHERE id = ?', [$hook['id']]));
});

test('local webhook receiver verifies raw signatures ping and all three event deliveries', function () {
    $port = 19000 + random_int(0, 999); $log = __DIR__ . '/tmp/webhook-received.jsonl'; file_put_contents($log, '');
    $receiver = __DIR__ . '/tmp/webhook-receiver.php';
    file_put_contents($receiver, '<?php declare(strict_types=1); $body = file_get_contents("php://input"); file_put_contents(__DIR__ . "/webhook-received.jsonl", json_encode(["body" => $body, "event" => $_SERVER["HTTP_X_BNC_EVENT"] ?? "", "signature" => $_SERVER["HTTP_X_BNC_SIGNATURE"] ?? ""]) . "\n", FILE_APPEND); echo "ok";');
    $process = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, $receiver], [1 => ['file', __DIR__ . '/tmp/webhook-server.log', 'a'], 2 => ['file', __DIR__ . '/tmp/webhook-server.log', 'a']], $pipes);
    $old = publish_test_config(['webhooks_allow_private' => true]); $owner = $GLOBALS['ownerBrowser']; $secret = bin2hex(random_bytes(32));
    $id = Db::insert('webhooks', ['name' => 'Local receiver', 'url' => "http://127.0.0.1:$port/hook", 'events' => json_encode(Delivery::EVENTS), 'secret' => $secret]);
    try {
        for ($i = 0; $i < 50; $i++) { $socket = @fsockopen('127.0.0.1', $port); if ($socket) { fclose($socket); break; } usleep(100000); }
        assert_same(303, $owner->post('/admin/webhooks/' . $id . '/test')['status']); assert_same('200', Db::value('SELECT last_status FROM webhooks WHERE id = ?', [$id]));
        enquiry_reset_rates(); assert_same(201, api_http('POST', '/api/enquiries', enquiry_input())['status']);
        $token = api_test_key(['posts.write', 'posts.publish']);
        assert_same(201, api_http('POST', '/api/v1/posts', ['title' => 'Webhook article', 'content_html' => '<p>Body</p>', 'status' => 'published'], api_headers($token))['status']);
        $job = Db::insert('publish_jobs', ['triggered_by' => 'local receiver']);
        assert_same(200, api_http('POST', '/api/publish/status', ['job_id' => $job, 'status' => 'succeeded', 'run_url' => 'https://github.com/example/nile/actions/runs/9', 'message' => 'Done'], api_headers(str_repeat('w', 40)))['status']);
        $records = array_map(fn ($line) => json_decode($line, true), array_filter(explode("\n", file_get_contents($log))));
        assert_same(['ping', 'enquiry.created', 'post.published', 'site.published'], array_column($records, 'event'));
        foreach ($records as $record) { assert_same('sha256=' . hash_hmac('sha256', $record['body'], $secret), $record['signature']); assert_same($record['event'], json_decode($record['body'], true)['event']); }
        $before = count($records);
        assert_same(201, api_http('POST', '/api/v1/posts', ['title' => 'Scheduled no event', 'content_html' => '<p>Later</p>', 'status' => 'published', 'published_at' => (new DateTimeImmutable('+1 day'))->format(DATE_ATOM)], api_headers($token))['status']);
        assert_same($before, count(array_filter(explode("\n", file_get_contents($log)))));
        file_put_contents($receiver, '<?php declare(strict_types=1); header("Location: http://127.0.0.1:1/", true, 302);');
        Delivery::send(Db::one('SELECT * FROM webhooks WHERE id = ?', [$id]), 'ping', []);
        assert_same('302', Db::value('SELECT last_status FROM webhooks WHERE id = ?', [$id]));
    } finally { Db::run('DELETE FROM webhooks WHERE id = ?', [$id]); phase5_restore($old); proc_terminate($process); proc_close($process); }
});

test('failed workflow callback finishes the job and publish history is limited to twenty', function () {
    $old = publish_test_config();
    try {
        $id = Db::insert('publish_jobs', ['triggered_by' => 'failed callback']);
        $r = api_http('POST', '/api/publish/status', ['job_id' => $id, 'status' => 'failed', 'run_url' => 'https://github.com/example/nile/actions/runs/10', 'message' => 'Build failed'], api_headers(str_repeat('w', 40)));
        assert_same(200, $r['status']); assert_same('failed', Db::value('SELECT status FROM publish_jobs WHERE id = ?', [$id])); assert_true(Db::value('SELECT finished_at FROM publish_jobs WHERE id = ?', [$id]) !== null);
        for ($n = 0; $n < 21; $n++) Db::insert('publish_jobs', ['triggered_by' => 'history-marker-' . $n, 'status' => 'succeeded']);
        $page = $GLOBALS['ownerBrowser']->get('/admin/publish');
        assert_contains('history-marker-20', $page['body']); assert_contains('history-marker-1<', $page['body']); assert_not_contains('history-marker-0<', $page['body']);
    } finally { phase5_restore($old); }
});
