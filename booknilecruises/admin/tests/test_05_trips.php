<?php
declare(strict_types=1);

use Bnc\{Db, Html, Roles};

$tripOwner = $GLOBALS['ownerBrowser'];
function trip_fields(array $replace = []): array
{
    return array_replace([
        'title' => 'Nile رحلة', 'slug' => 'phase-two-nile', 'status' => 'published', 'featured' => '1',
        'code' => 'BNC-2', 'excerpt' => 'A Nile cruise', 'price' => '900.50', 'sale_price' => '800.25', 'currency' => 'EUR',
        'duration_days' => '4', 'duration_nights' => '3', 'min_pax' => '1', 'max_pax' => '12',
        'overview_html' => '<p onclick="bad()">Overview<script>bad()</script></p>',
        'highlights' => "  نهر النيل  \n\n Luxor", 'includes' => "Meals\n Guide", 'excludes' => "Flights\nTips",
        'itinerary' => [['title' => '3 Nights From Aswan to Luxor', 'html' => ''], ['title' => 'Day one', 'html' => '<p onclick="bad()">Visit<script>bad()</script> Luxor</p>']],
        'faqs' => [['q' => 'When?', 'a' => '<p onclick="bad()">Daily<script>bad()</script></p>']],
        'seo_title' => 'Nile SEO', 'seo_description' => 'A cruise description', 'noindex' => '1',
        'term_ids' => [], 'image_id' => '', 'gallery' => [],
    ], $replace);
}
function trip_payload(int $id, array $replace = []): array
{
    $row = \Bnc\Trips\TripForm::load(Db::one('SELECT * FROM trips WHERE id = ?', [$id]));
    foreach (['highlights', 'includes', 'excludes'] as $field) $row[$field] = implode("\n", $row[$field]);
    return array_replace($row, $replace);
}

test('trip creates every section with exact sanitised export JSON and category order', function () use ($tripOwner) {
    $first = Db::insert('terms', ['taxonomy' => 'activities', 'slug' => 'phase-two-activity', 'name' => 'Activity', 'url' => '/activities/phase-two-activity/']);
    $second = Db::insert('terms', ['taxonomy' => 'destination', 'slug' => 'phase-two-destination', 'name' => 'Destination', 'url' => '/destinations/phase-two-destination/']);
    $cover = Db::insert('media', ['path' => '/images/tests/phase-cover.jpg', 'sizes' => '{"medium":{"file":"phase-cover-medium.jpg","width":300,"height":200}}']);
    $gallery = Db::insert('media', ['path' => '/images/tests/phase-gallery.jpg']);
    $fields = trip_fields(['term_ids' => [(string) $first, (string) $second], 'image_id' => (string) $cover, 'gallery' => [(string) $gallery, (string) $cover]]);
    assert_same(200, $tripOwner->get('/admin/trips/new')['status']);
    assert_same(400, $tripOwner->post('/admin/trips/new', $fields, false)['status']);
    assert_same(303, $tripOwner->post('/admin/trips/new', $fields)['status']);
    $row = Db::one("SELECT * FROM trips WHERE slug = 'phase-two-nile'");
    $GLOBALS['phaseTrip'] = (int) $row['id'];
    $GLOBALS['phaseTerms'] = [$first, $second];
    $GLOBALS['phasePhotos'] = [$cover, $gallery];
    foreach (['title', 'slug', 'status', 'code', 'excerpt', 'currency', 'seo_title', 'seo_description'] as $field) assert_same($fields[$field], $row[$field]);
    foreach (['featured' => 1, 'noindex' => 1, 'duration_days' => 4, 'duration_nights' => 3, 'min_pax' => 1, 'max_pax' => 12, 'image_id' => $cover] as $field => $value) assert_same($value, (int) $row[$field]);
    assert_same('900.50', $row['price']); assert_same('800.25', $row['sale_price']);
    assert_same('<p>Overview</p>', $row['overview_html']);
    $expected = ['highlights' => ['نهر النيل', 'Luxor'], 'includes' => ['Meals', 'Guide'], 'excludes' => ['Flights', 'Tips'], 'itinerary' => [['title' => '3 Nights From Aswan to Luxor', 'html' => ''], ['title' => 'Day one', 'html' => '<p>Visit Luxor</p>']], 'faqs' => [['q' => 'When?', 'a' => '<p>Daily</p>']], 'gallery' => [$gallery, $cover]];
    foreach ($expected as $field => $value) assert_same(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $row[$field], $field);
    assert_same([$first, $second], array_map('intval', array_column(Db::all('SELECT term_id FROM trip_terms WHERE trip_id = ? ORDER BY position', [$row['id']]), 'term_id')));
    assert_true((int) $row['created_by'] > 0); assert_same($row['created_by'], $row['updated_by']);
    assert_contains('phase-cover-medium.jpg', $tripOwner->get('/admin/trips')['body']);
    assert_contains('Nile رحلة', $tripOwner->get('/admin/trips?q=BNC-2&status=published&category=' . $first)['body']);
    assert_not_contains('Nile رحلة</td>', $tripOwner->get('/admin/trips?q=no-such-title')['body']);
});

test('trip validation preserves all entered rows and photos on 422', function () use ($tripOwner) {
    foreach ([['slug' => 'BAD slug'], ['slug' => 'phase-two-nile'], ['slug' => 'validation-sale', 'sale_price' => '900.50'], ['slug' => 'validation-photo', 'image_id' => '99999999'], ['slug' => 'validation-gallery', 'gallery' => ['99999999']]] as $invalid) {
        $fields = trip_fields($invalid + ['itinerary' => [['title' => 'Keep heading', 'html' => ''], ['title' => 'Keep day', 'html' => '<p>Keep content</p>']], 'faqs' => [['q' => 'Keep question', 'a' => '<p>Keep answer</p>']]]);
        $result = $tripOwner->post('/admin/trips/new', $fields);
        assert_same(422, $result['status']);
        foreach (['Keep heading', 'Keep day', 'Keep content', 'Keep question', 'Keep answer'] as $text) assert_contains($text, $result['body']);
        if (isset($invalid['image_id']) || isset($invalid['gallery'])) assert_contains('99999999', $result['body']);
    }
});

test('trip optimistic locking rejects stale input and audits changed fields', function () use ($tripOwner) {
    $id = $GLOBALS['phaseTrip']; $fields = trip_payload($id);
    assert_same(303, $tripOwner->post("/admin/trips/$id/edit", array_replace($fields, ['title' => 'Updated Nile']))['status']);
    $result = $tripOwner->post("/admin/trips/$id/edit", array_replace($fields, ['title' => 'Stale Nile']));
    assert_same(409, $result['status']); assert_contains('Stale Nile', $result['body']);
    assert_same('Updated Nile', Db::value('SELECT title FROM trips WHERE id = ?', [$id]));
    $details = json_decode(Db::value("SELECT details FROM audit_log WHERE entity = 'trip' AND action = 'update' AND entity_id = ? ORDER BY id DESC LIMIT 1", [$id]), true);
    assert_true(in_array('title', $details['changed'], true));
});

test('published trip renames repoint redirects and renaming back leaves no loop', function () use ($tripOwner) {
    $id = $GLOBALS['phaseTrip'];
    foreach (['phase-two-b', 'phase-two-c'] as $slug) assert_same(303, $tripOwner->post("/admin/trips/$id/edit", trip_payload($id, ['slug' => $slug]))['status']);
    assert_same('/trip/phase-two-c/', Db::value('SELECT to_path FROM redirects WHERE from_path = ?', ['/trip/phase-two-nile/']));
    assert_same('/trip/phase-two-c/', Db::value('SELECT to_path FROM redirects WHERE from_path = ?', ['/trip/phase-two-b/']));
    assert_same(303, $tripOwner->post("/admin/trips/$id/edit", trip_payload($id, ['slug' => 'phase-two-nile']))['status']);
    assert_same(null, Db::value('SELECT id FROM redirects WHERE from_path = ?', ['/trip/phase-two-nile/']));
    foreach (['phase-two-b', 'phase-two-c'] as $slug) assert_same('/trip/phase-two-nile/', Db::value('SELECT to_path FROM redirects WHERE from_path = ?', ["/trip/$slug/"]));
    assert_same(0, (int) Db::value('SELECT COUNT(*) FROM redirects WHERE from_path = to_path'));
});

test('duplicates are unique drafts with identical categories and photos; draft rename creates no redirect', function () use ($tripOwner) {
    $id = $GLOBALS['phaseTrip'];
    foreach (['phase-two-nile-copy', 'phase-two-nile-copy-2'] as $slug) {
        $result = $tripOwner->post("/admin/trips/$id/duplicate"); assert_same(303, $result['status']);
        $copy = Db::one('SELECT * FROM trips WHERE slug = ?', [$slug]); assert_true($copy !== null);
        assert_same('/admin/trips/' . $copy['id'] . '/edit', $result['location']);
        assert_same('draft', $copy['status']); assert_same('Updated Nile (copy)', $copy['title']);
        $original = Db::one('SELECT * FROM trips WHERE id = ?', [$id]);
        foreach (['image_id', 'gallery', 'itinerary', 'faqs'] as $field) assert_same($original[$field], $copy[$field]);
        assert_same($GLOBALS['phaseTerms'], array_map('intval', array_column(Db::all('SELECT term_id FROM trip_terms WHERE trip_id = ? ORDER BY position', [$copy['id']]), 'term_id')));
        $GLOBALS['phaseDraft'] = (int) $copy['id'];
    }
    $copy = $GLOBALS['phaseDraft'];
    assert_same(303, $tripOwner->post("/admin/trips/$copy/edit", trip_payload($copy, ['slug' => 'renamed-draft']))['status']);
    assert_same(null, Db::value('SELECT id FROM redirects WHERE from_path = ?', ['/trip/phase-two-nile-copy-2/']));
});

test('trip permissions for sales editor and SEO match the presets', function () use ($base) {
    foreach (['sales', 'editor', 'seo'] as $role) {
        Db::insert('users', ['name' => $role, 'email' => "phase-$role@example.com", 'password_hash' => password_hash('phase-password-123', PASSWORD_DEFAULT), 'role_id' => Roles::bySlug($role)['id']]);
        $browser = new Browser($base); $browser->get('/admin/login'); assert_same(303, $browser->post('/admin/login', ['email' => "phase-$role@example.com", 'password' => 'phase-password-123'])['status']);
        $id = $GLOBALS['phaseTrip'];
        assert_same(200, $browser->get('/admin/trips')['status'], "$role can see the trip list");
        assert_same($role === 'sales' ? 403 : 200, $browser->get("/admin/trips/$id/edit")['status']);
        assert_same($role === 'sales' ? 403 : 303, $browser->post("/admin/trips/$id/edit", trip_payload($id))['status']);
        assert_same(403, $browser->get("/admin/trips/$id/delete")['status']);
        assert_same(403, $browser->post("/admin/trips/$id/delete", ['redirect_to' => '/trip/'])['status']);
        assert_same($role === 'editor' ? 200 : 403, $browser->get('/admin/trips/new')['status']);
        if ($role !== 'editor') {
            assert_same(403, $browser->post('/admin/trips/new', trip_fields(['slug' => "forbidden-$role"]))['status']);
            assert_same(403, $browser->post("/admin/trips/$id/duplicate")['status']);
        }
    }
});

test('media used only in a trip gallery still cannot be deleted', function () use ($tripOwner) {
    $photo = $GLOBALS['phasePhotos'][1];
    assert_same(303, $tripOwner->post("/admin/media/$photo/delete")['status']);
    assert_true(Db::value('SELECT id FROM media WHERE id = ?', [$photo]) !== null);
    assert_contains('مستخدمة', $tripOwner->get("/admin/media/$photo")['body']);
});

test('trip deletion validates chosen target then redirects and cascades categories', function () use ($tripOwner) {
    $id = $GLOBALS['phaseTrip']; $draft = $GLOBALS['phaseDraft'];
    assert_same(200, $tripOwner->get("/admin/trips/$id/delete")['status']);
    assert_same(422, $tripOwner->post("/admin/trips/$id/delete", ['redirect_to' => '/arbitrary/'])['status']);
    $target = Db::value('SELECT url FROM terms WHERE id = ?', [$GLOBALS['phaseTerms'][0]]);
    assert_same(303, $tripOwner->post("/admin/trips/$id/delete", ['redirect_to' => $target])['status']);
    assert_same(null, Db::value('SELECT id FROM trips WHERE id = ?', [$id]));
    assert_same(0, (int) Db::value('SELECT COUNT(*) FROM trip_terms WHERE trip_id = ?', [$id]));
    foreach (['phase-two-nile', 'phase-two-b', 'phase-two-c'] as $slug) assert_same($target, Db::value('SELECT to_path FROM redirects WHERE from_path = ?', ["/trip/$slug/"]));
    assert_true(Db::value('SELECT id FROM media WHERE id = ?', [$GLOBALS['phasePhotos'][0]]) !== null);
    assert_same(303, $tripOwner->post("/admin/trips/$draft/delete", ['redirect_to' => '/trip/'])['status']);
    assert_same(null, Db::value('SELECT id FROM redirects WHERE from_path = ?', ['/trip/renamed-draft/']));
});

test('trip limits validate lists rows duration travellers and currency', function () use ($tripOwner) {
    foreach ([['highlights' => implode("\n", array_fill(0, 51, 'item'))], ['includes' => str_repeat('x', 501)], ['itinerary' => array_fill(0, 61, ['title' => 'Day', 'html' => ''])], ['faqs' => array_fill(0, 41, ['q' => 'Question', 'a' => '<p>Answer</p>'])], ['duration_days' => '366'], ['duration_nights' => '-1'], ['min_pax' => '0'], ['max_pax' => '1000'], ['min_pax' => '13', 'max_pax' => '12'], ['currency' => 'JPY'], ['price' => 'NaN'], ['term_ids' => ['99999999']], ['title' => str_repeat('x', 256)]] as $invalid) {
        assert_same(422, $tripOwner->post('/admin/trips/new', trip_fields($invalid + ['slug' => 'invalid-limit']))['status']);
    }
});

test('trip listing paginates 50 rows and keeps literal search and filter state', function () use ($tripOwner) {
    $ids = [];
    try {
        for ($i = 1; $i <= 51; $i++) $ids[] = Db::insert('trips', ['title' => 'Paging trip ' . $i, 'slug' => 'paging-trip-' . $i, 'status' => 'draft', 'code' => 'Paging']);
        $first = $tripOwner->get('/admin/trips?q=Paging&status=draft');
        assert_same(50, substr_count($first['body'], '<td>Paging trip '));
        assert_contains('class="pager"', $first['body']); assert_contains('q=Paging&amp;status=draft', $first['body']);
        assert_same(1, substr_count($tripOwner->get('/admin/trips?q=Paging&status=draft&page=2')['body'], '<td>Paging trip '));
        assert_same(0, substr_count($tripOwner->get('/admin/trips?q=%25')['body'], '<td>Paging trip '));
    } finally {
        foreach ($ids as $id) Db::run('DELETE FROM trips WHERE id = ?', [$id]);
    }
});

test('trip saves roll back row category and redirect changes if auditing fails', function () use ($tripOwner) {
    $id = (int) Db::value("SELECT id FROM trips WHERE slug = 'phase-two-nile-copy'");
    assert_same(303, $tripOwner->post("/admin/trips/$id/edit", trip_payload($id, ['status' => 'published']))['status']);
    $before = Db::one('SELECT * FROM trips WHERE id = ?', [$id]);
    $terms = Db::all('SELECT * FROM trip_terms WHERE trip_id = ? ORDER BY position', [$id]);
    Db::run("CREATE TRIGGER phase_trip_audit_failure BEFORE INSERT ON audit_log FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'test audit failure'");
    try {
        assert_same(500, $tripOwner->post("/admin/trips/$id/edit", trip_payload($id, ['slug' => 'failed-transaction', 'term_ids' => []]))['status']);
    } finally { Db::run('DROP TRIGGER phase_trip_audit_failure'); }
    assert_same($before, Db::one('SELECT * FROM trips WHERE id = ?', [$id]));
    assert_same($terms, Db::all('SELECT * FROM trip_terms WHERE trip_id = ? ORDER BY position', [$id]));
    assert_same(null, Db::value('SELECT id FROM redirects WHERE from_path = ?', ['/trip/phase-two-nile-copy/']));
});
