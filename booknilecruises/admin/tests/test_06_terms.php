<?php
declare(strict_types=1);

use Bnc\{Db, Terms};

$termOwner = $GLOBALS['ownerBrowser'];
function term_fields(array $replace = []): array
{
    return array_replace(['taxonomy' => 'activities', 'name' => 'Activity parent', 'slug' => 'phase-parent', 'parent_id' => '', 'description' => 'Description', 'seo_title' => 'Term SEO', 'seo_description' => 'Term description'], $replace);
}

test('categories create a child activity with its computed URL and plain text metadata', function () use ($termOwner) {
    assert_same(200, $termOwner->get('/admin/terms/new')['status']);
    assert_same(400, $termOwner->post('/admin/terms/new', term_fields(), false)['status']);
    assert_same(303, $termOwner->post('/admin/terms/new', term_fields())['status']);
    $parent = (int) Db::value("SELECT id FROM terms WHERE slug = 'phase-parent'");
    assert_same(303, $termOwner->post('/admin/terms/new', term_fields(['slug' => 'phase-child', 'name' => 'Activity child', 'parent_id' => (string) $parent, 'description' => '<b>Child description</b>']))['status']);
    $child = Db::one("SELECT * FROM terms WHERE slug = 'phase-child'");
    assert_same('/activities/phase-parent/phase-child/', $child['url']); assert_same($parent, (int) $child['parent_id']);
    assert_same('Child description', $child['description']); assert_same('Term SEO', $child['seo_title']); assert_same('Term description', $child['seo_description']);
    $GLOBALS['phaseParent'] = $parent; $GLOBALS['phaseChild'] = (int) $child['id'];
    assert_contains('/activities/phase-parent/phase-child/', $termOwner->get('/admin/terms')['body']);
});

test('category parent rename updates children and redirects without chains', function () use ($termOwner) {
    $parent = $GLOBALS['phaseParent']; $child = $GLOBALS['phaseChild'];
    foreach (['phase-parent-b', 'phase-parent-c'] as $slug) assert_same(303, $termOwner->post("/admin/terms/$parent/edit", term_fields(['slug' => $slug, 'taxonomy' => 'destination']))['status']);
    assert_same('activities', Db::value('SELECT taxonomy FROM terms WHERE id = ?', [$parent]));
    assert_same('/activities/phase-parent-c/', Db::value('SELECT url FROM terms WHERE id = ?', [$parent]));
    assert_same('/activities/phase-parent-c/phase-child/', Db::value('SELECT url FROM terms WHERE id = ?', [$child]));
    foreach (['phase-parent', 'phase-parent-b'] as $slug) {
        assert_same('/activities/phase-parent-c/', Db::value('SELECT to_path FROM redirects WHERE from_path = ?', ["/activities/$slug/"]));
        assert_same('/activities/phase-parent-c/phase-child/', Db::value('SELECT to_path FROM redirects WHERE from_path = ?', ["/activities/$slug/phase-child/"]));
    }
});

test('categories reject invalid hierarchy duplicate slugs and destination parents', function () use ($termOwner) {
    $parent = $GLOBALS['phaseParent']; $child = $GLOBALS['phaseChild'];
    foreach ([['slug' => 'BAD'], ['slug' => 'phase-child'], ['slug' => 'invalid-grandchild', 'parent_id' => (string) $child], ['taxonomy' => 'destination', 'slug' => 'invalid-destination', 'parent_id' => (string) $parent], ['taxonomy' => 'trip_types', 'slug' => 'invalid-type', 'parent_id' => (string) $parent]] as $invalid) assert_same(422, $termOwner->post('/admin/terms/new', term_fields($invalid))['status']);
    assert_same(422, $termOwner->post("/admin/terms/$parent/edit", term_fields(['slug' => 'phase-parent-c', 'parent_id' => (string) $parent]))['status']);
    assert_same(422, $termOwner->post("/admin/terms/$child/edit", term_fields(['slug' => 'phase-child', 'parent_id' => '99999999']))['status']);
    foreach (['destination' => '/destinations/', 'trip_types' => '/trip-types/'] as $taxonomy => $prefix) {
        assert_same(303, $termOwner->post('/admin/terms/new', term_fields(['taxonomy' => $taxonomy, 'slug' => "phase-" . str_replace('_', '-', $taxonomy)]))['status']);
        assert_same($prefix . "phase-" . str_replace('_', '-', $taxonomy) . '/', Db::value('SELECT url FROM terms WHERE taxonomy = ? AND slug = ?', [$taxonomy, "phase-" . str_replace('_', '-', $taxonomy)]));
    }
});

test('all protected activity slugs are read-only and cannot be renamed or deleted', function () use ($termOwner) {
    foreach (Terms::PROTECTED_ACTIVITY_SLUGS as $slug) {
        $id = Db::insert('terms', ['taxonomy' => 'activities', 'slug' => $slug, 'name' => $slug, 'url' => "/activities/$slug/"]);
        assert_contains('readonly', $termOwner->get("/admin/terms/$id/edit")['body']);
        assert_same(422, $termOwner->post("/admin/terms/$id/edit", term_fields(['slug' => "$slug-renamed"]))['status']);
        assert_same($slug, Db::value('SELECT slug FROM terms WHERE id = ?', [$id]));
        assert_same(303, $termOwner->post("/admin/terms/$id/delete")['status']);
        assert_true(Db::value('SELECT id FROM terms WHERE id = ?', [$id]) !== null);
    }
});

test('category deletion refuses parents then redirects child to parent and parent to index', function () use ($termOwner) {
    $parent = $GLOBALS['phaseParent']; $child = $GLOBALS['phaseChild'];
    assert_same(303, $termOwner->post("/admin/terms/$parent/delete")['status']); assert_true(Db::value('SELECT id FROM terms WHERE id = ?', [$parent]) !== null);
    $trip = (int) Db::value("SELECT id FROM trips WHERE slug = 'phase-two-nile-copy'");
    Db::insert('trip_terms', ['trip_id' => $trip, 'term_id' => $child]);
    assert_same(303, $termOwner->post("/admin/terms/$child/delete")['status']);
    assert_same(null, Db::value('SELECT id FROM terms WHERE id = ?', [$child]));
    assert_same(0, (int) Db::value('SELECT COUNT(*) FROM trip_terms WHERE term_id = ?', [$child]));
    assert_same('/activities/phase-parent-c/', Db::value('SELECT to_path FROM redirects WHERE from_path = ?', ['/activities/phase-parent-c/phase-child/']));
    assert_same(303, $termOwner->post("/admin/terms/$parent/delete")['status']);
    assert_same('/activities/', Db::value('SELECT to_path FROM redirects WHERE from_path = ?', ['/activities/phase-parent-c/']));
    assert_same('/activities/', Db::value('SELECT to_path FROM redirects WHERE from_path = ?', ['/activities/phase-parent/phase-child/']));
});

test('sales cannot access category routes', function () use ($base) {
    $browser = new Browser($base); $browser->get('/admin/login');
    $browser->post('/admin/login', ['email' => 'phase-sales@example.com', 'password' => 'phase-password-123']);
    assert_same(403, $browser->get('/admin/terms')['status']); assert_same(403, $browser->get('/admin/terms/new')['status']);
    $id = (int) Db::value('SELECT id FROM terms LIMIT 1');
    assert_same(403, $browser->get("/admin/terms/$id/edit")['status']);
    assert_same(403, $browser->post('/admin/terms/new', term_fields())['status']);
    assert_same(403, $browser->post("/admin/terms/$id/edit", term_fields())['status']);
    assert_same(403, $browser->post("/admin/terms/$id/delete")['status']);
});
