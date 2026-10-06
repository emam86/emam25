<?php
declare(strict_types=1);
use Bnc\{Db, Roles, Settings};
use Bnc\Enquiries\{EnquiryService, Notifier};

function enquiry_input(array $changes = []): array
{
    return array_replace(['name' => 'Beacon visitor', 'email' => 'visitor@example.com', 'phone' => '+20 (123) 456-789', 'travel_date' => date('Y-m-d', time() + 86400), 'adults' => 2, 'children' => 1, 'message' => 'A Nile cruise please', 'page_url' => '/trip/nile/', 'channel' => 'form', 'website' => ''], $changes);
}
function enquiry_reset_rates(): void { Db::run("DELETE FROM api_requests WHERE bucket LIKE 'enquiry:%'"); }

test('public beacon enquiries store every field and IP without CSRF or session', function () {
    enquiry_reset_rates();
    $trip = Db::one("SELECT id, slug, title FROM trips WHERE status = 'published' LIMIT 1");
    $r = api_http('POST', '/api/enquiries', enquiry_input(['trip' => $trip['slug']]), ['Origin: https://booknilecruises.net', 'Cookie: bnc_admin=invalid-session']);
    assert_same(201, $r['status']); assert_same(['ok' => true], $r['json']); assert_not_contains('Set-Cookie:', $r['headers']);
    $row = Db::one('SELECT * FROM enquiries ORDER BY id DESC LIMIT 1');
    assert_same('Beacon visitor', $row['name']); assert_same('127.0.0.1', $row['ip']);
    assert_same((int) $trip['id'], (int) $row['trip_id']); assert_same($trip['title'], $row['trip_title']);
    assert_same(2, (int) $row['adults']); assert_same(1, (int) $row['children']); assert_same('new', $row['status']);
    assert_same(null, Db::value("SELECT user_id FROM audit_log WHERE entity = 'enquiry' AND entity_id = ? ORDER BY id LIMIT 1", [(string) $row['id']]));
    foreach (['https://evil.example', 'null'] as $origin) assert_same(403, api_http('POST', '/api/enquiries', enquiry_input(), ['Origin: ' . $origin])['status']);
    $options = api_http('OPTIONS', '/api/enquiries', null, ['Origin: https://evil.example']);
    assert_same(204, $options['status']); assert_same('', $options['body']); assert_not_contains('Access-Control-', $options['headers']);
    assert_same(400, api_http('POST', '/api/enquiries', '{broken')['status']);
    assert_same(413, api_http('POST', '/api/enquiries', str_repeat('a', 1048577))['status']);
});

test('enquiry honeypot stores nothing and form contacts dates paths and counts validate', function () {
    enquiry_reset_rates(); $count = (int) Db::value('SELECT COUNT(*) FROM enquiries');
    assert_same(201, api_http('POST', '/api/enquiries', ['website' => 'spam'])['status']);
    assert_same($count, (int) Db::value('SELECT COUNT(*) FROM enquiries'));
    foreach ([['email' => '', 'phone' => ''], ['email' => 'bad@'], ['phone' => 'abc'], ['travel_date' => '2026-02-30'], ['travel_date' => date('Y-m-d', time() - 86400)], ['page_url' => 'https://example.org/'], ['page_url' => '//example.org/'], ['adults' => 100], ['children' => -1], ['name' => '']] as $change) {
        enquiry_reset_rates(); $r = api_http('POST', '/api/enquiries', enquiry_input($change));
        assert_same($change === ['email' => '', 'phone' => ''] ? 422 : 201, $r['status']);
    }
    assert_same('ab', Notifier::header("a\r\nb"));
});

test('WhatsApp and email beacons preserve malformed optional contacts and allow no contact', function () {
    enquiry_reset_rates();
    foreach (['whatsapp', 'email'] as $channel) {
        assert_same(201, api_http('POST', '/api/enquiries', enquiry_input(['channel' => $channel, 'email' => 'raw@invalid', 'phone' => 'call me maybe']))['status']);
        $row = Db::one('SELECT * FROM enquiries ORDER BY id DESC LIMIT 1');
        assert_same(null, $row['email']); assert_same(null, $row['phone']);
        assert_contains('raw@invalid', $row['message']); assert_contains('call me maybe', $row['message']);
        assert_same(201, api_http('POST', '/api/enquiries', enquiry_input(['channel' => $channel, 'email' => '', 'phone' => '']))['status']);
    }
});

test('form encoded enquiries work and rolling IP and daily overall limits return 429', function () {
    enquiry_reset_rates(); global $base;
    $ch = curl_init($base . '/api/enquiries');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(enquiry_input()), CURLOPT_RETURNTRANSFER => true, CURLOPT_PROXY => '', CURLOPT_NOPROXY => '*']);
    curl_exec($ch); assert_same(201, (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE)); curl_close($ch);
    for ($i = 1; $i < 5; $i++) assert_same(201, api_http('POST', '/api/enquiries', enquiry_input())['status']);
    assert_same(429, api_http('POST', '/api/enquiries', enquiry_input())['status']);
    Db::run("UPDATE api_requests SET created_at = ? WHERE bucket LIKE 'enquiry:%'", [date('Y-m-d H:i:s', time() - 601)]);
    assert_same(201, api_http('POST', '/api/enquiries', enquiry_input())['status']);
    enquiry_reset_rates();
    for ($i = 0; $i < 200; $i++) Db::insert('api_requests', ['bucket' => 'enquiry:all']);
    assert_same(429, api_http('POST', '/api/enquiries', enquiry_input())['status']); enquiry_reset_rates();
});

test('sales can manage and export enquiries while editor receives 403; CSV cells are safe', function () {
    global $base; $owner = $GLOBALS['ownerBrowser'];
    foreach (['sales', 'editor'] as $role) assert_same(303, $owner->post('/admin/users/new', ['name' => 'Phase5 ' . $role, 'email' => "phase5-$role@example.com", 'password' => 'phase5-password-123', 'role_id' => (string) Roles::bySlug($role)['id'], 'is_active' => '1'])['status']);
    $sales = new Browser($base); $sales->get('/admin/login');
    assert_same(303, $sales->post('/admin/login', ['email' => 'phase5-sales@example.com', 'password' => 'phase5-password-123'])['status']);
    $id = Db::insert('enquiries', ['name' => '=HYPERLINK("evil")', 'phone' => '+123', 'message' => '@command', 'notes' => '-danger', 'ip' => '127.0.0.1']);
    assert_same(200, $sales->get('/admin/enquiries')['status']); assert_same(200, $sales->get('/admin/enquiries/' . $id)['status']);
    assert_same(400, $sales->post('/admin/enquiries/' . $id, ['status' => 'contacted'], false)['status']);
    assert_same(422, $sales->post('/admin/enquiries/' . $id, ['status' => 'bad'])['status']);
    assert_same(303, $sales->post('/admin/enquiries/' . $id, ['status' => 'contacted', 'notes' => '<script>safe as text</script>'])['status']);
    assert_same('contacted', Db::value('SELECT status FROM enquiries WHERE id = ?', [$id]));
    assert_contains('&lt;script&gt;', $sales->get('/admin/enquiries/' . $id)['body']);
    $csv = $sales->get('/admin/enquiries.csv'); assert_same(200, $csv['status']); assert_same("\xEF\xBB\xBF", substr($csv['body'], 0, 3));
    assert_contains('text/csv', $csv['headers']); assert_contains("'=HYPERLINK", $csv['body']); assert_contains("'+123", $csv['body']); assert_contains("'@command", $csv['body']);
    foreach (['=x', '+x', '-x', '@x', "\t=x"] as $cell) assert_same("'" . $cell, EnquiryService::csvCell($cell));
    assert_same('normal', EnquiryService::csvCell('normal'));
    assert_same(422, $sales->post('/admin/enquiries/notify', ['enquiry_notify_email' => "bad\r\nmail"])['status']);
    assert_same(303, $sales->post('/admin/enquiries/notify', ['enquiry_notify_email' => 'sales@example.com'])['status']);
    Settings::reset(); assert_same('sales@example.com', Settings::get('enquiry_notify_email'));
    assert_same(303, $sales->post('/admin/enquiries/notify', ['enquiry_notify_email' => ''])['status']);
    $ed = new Browser($base); $ed->get('/admin/login');
    assert_same(303, $ed->post('/admin/login', ['email' => 'phase5-editor@example.com', 'password' => 'phase5-password-123'])['status']);
    foreach (['/admin/enquiries', '/admin/enquiries/' . $id, '/admin/enquiries.csv', '/admin/api-keys', '/admin/webhooks'] as $path) assert_same(403, $ed->get($path)['status']);
    assert_same(403, $ed->post('/admin/enquiries/' . $id, ['status' => 'booked'])['status']);
    assert_same(403, $ed->post('/admin/enquiries/' . $id . '/delete')['status']);
    assert_same(403, $sales->get('/admin/publish')['status']);
    assert_same(303, $sales->post('/admin/enquiries/' . $id . '/delete')['status']);
    assert_same(null, Db::value('SELECT id FROM enquiries WHERE id = ?', [$id]));
});

test('inbox filters paginates and scoped enquiries API reads at most 200 oldest first', function () {
    $owner = $GLOBALS['ownerBrowser'];
    Db::insert('enquiries', ['name' => 'older read marker', 'status' => 'booked', 'created_at' => '2026-01-01 00:00:00']);
    for ($i = 0; $i < 205; $i++) Db::insert('enquiries', ['name' => 'Read marker ' . $i, 'status' => 'booked', 'created_at' => '2026-10-01 12:00:00']);
    $page = $owner->get('/admin/enquiries?status=booked&q=Read%20marker');
    assert_same(200, $page['status']); assert_contains('صفحة 1 / 5', $page['body']); assert_not_contains('Read marker 100<', $page['body']);
    assert_contains('Read marker', $owner->get('/admin/enquiries?status=booked&q=Read%20marker&page=5')['body']);
    $token = api_test_key(['enquiries.read']);
    $r = api_http('GET', '/api/v1/enquiries?since=2026-09-01T00:00:00&status=booked', null, api_headers($token));
    assert_same(200, $r['status']); assert_same(200, count($r['json']['enquiries'])); assert_same('Read marker 0', $r['json']['enquiries'][0]['name']);
    assert_same(422, api_http('GET', '/api/v1/enquiries?since=broken', null, api_headers($token))['status']);
    assert_same(422, api_http('GET', '/api/v1/enquiries?status=broken', null, api_headers($token))['status']);
    assert_same(401, api_http('GET', '/api/v1/enquiries')['status']);
});


test('enquiry repairs preserve raw fields and Cairo yesterday while dropping excess message', function () {
    enquiry_reset_rates();
    $r = EnquiryService::create(enquiry_input(['name' => '', 'channel' => 'unknown', 'email' => 'broken', 'travel_date' => '2000-01-01', 'adults' => 'two', 'children' => 100, 'message' => str_repeat('m', 5000) . 'DROP_THIS']));
    assert_same('(no name)', $r['name']); assert_same('form', $r['channel']);
    foreach (['email', 'travel_date', 'adults', 'children'] as $f) assert_same(null, $r[$f]);
    foreach (['[email: broken]', '[travel_date: 2000-01-01]', '[adults: two]', '[children: 100]', '[channel: unknown]', '[name: ]'] as $note) assert_contains($note, $r['message']);
    assert_not_contains('DROP_THIS', $r['message']);
    $yesterday = (new DateTimeImmutable('today', new DateTimeZone('Africa/Cairo')))->modify('-1 day')->format('Y-m-d');
    assert_same($yesterday, EnquiryService::create(enquiry_input(['travel_date' => $yesterday]))['travel_date']);
});

test('enquiries cursor drains identical timestamps and legacy since includes boundary', function () {
    $start = (int) Db::value('SELECT MAX(id) FROM enquiries');
    for ($i = 0; $i < 205; $i++) Db::insert('enquiries', ['name' => 'Cursor', 'created_at' => '2026-01-01 00:00:00']);
    $headers = api_headers(api_test_key(['enquiries.read']));
    $first = api_http('GET', '/api/v1/enquiries?since_id=' . $start . '&since=2026-01-01T00:00:00', null, $headers)['json'];
    assert_same(200, count($first['enquiries']));
    $second = api_http('GET', '/api/v1/enquiries?since_id=' . $first['next_since_id'], null, $headers)['json'];
    assert_same(5, count($second['enquiries']));
    $empty = api_http('GET', '/api/v1/enquiries?since_id=' . $second['next_since_id'], null, $headers)['json'];
    assert_same([], $empty['enquiries']); assert_same($second['next_since_id'], $empty['next_since_id']);
});

test('empty enquiry recipient skips mail without failure audit', function () {
    $old = phase5_config(['mail' => ['notify' => '']]); $setting = Settings::get('enquiry_notify_email');
    try {
        Settings::set('enquiry_notify_email', '');
        $before = (int) Db::value("SELECT COUNT(*) FROM audit_log WHERE action = 'notification_failed'");
        Notifier::send(['id' => 1, 'name' => 'No email']);
        assert_same($before, (int) Db::value("SELECT COUNT(*) FROM audit_log WHERE action = 'notification_failed'"));
    } finally { Settings::set('enquiry_notify_email', $setting); phase5_restore($old); }
});
