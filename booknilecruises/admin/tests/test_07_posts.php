<?php
declare(strict_types=1);

use Bnc\{Db, Html};
use Bnc\Content\Exporter;

$postOwner = $GLOBALS['ownerBrowser'];
function post_fields(array $replace = []): array
{
    return array_replace(['title' => 'Phase four post', 'slug' => 'phase-four-post', 'status' => 'published', 'published_at' => '2026-09-13T12:30', 'excerpt' => 'Plain excerpt', 'content_html' => '<p onclick="bad()">Hello<script>bad()</script><img src="/images/tests/phase-cover.jpg" alt="Nile"></p>', 'image_id' => '', 'seo_title' => 'Post SEO', 'seo_description' => 'Post description', 'noindex' => '0'], $replace);
}
function post_payload(int $id, array $replace = []): array
{
    return array_replace(Db::one('SELECT * FROM posts WHERE id = ?', [$id]), $replace);
}

test('posts create sanitised HTML with dated WordPress URL and shared editor controls', function () use ($postOwner) {
    $form = $postOwner->get('/admin/posts/new');
    assert_same(200, $form['status']);
    foreach (['data-rich', 'data-pick="content"', 'data-pick="cover"', 'data-counter="60"', 'datetime-local'] as $control) assert_contains($control, $form['body']);
    assert_same(400, $postOwner->post('/admin/posts/new', post_fields(), false)['status']);
    assert_same(303, $postOwner->post('/admin/posts/new', post_fields())['status']);
    $row = Db::one("SELECT * FROM posts WHERE slug = 'phase-four-post'");
    $GLOBALS['phasePost'] = (int) $row['id'];
    assert_same('/2026/09/13/phase-four-post/', $row['url']);
    assert_same(Html::clean(post_fields()['content_html']), $row['content_html']);
    assert_same('admin', $row['source']);
    assert_same(1, (int) Db::value("SELECT COUNT(*) FROM audit_log WHERE entity = 'post' AND entity_id = ? AND action = 'create'", [$row['id']]));
});

test('post URL changes redirect and optimistic locking rejects stale edits', function () use ($postOwner) {
    $id = $GLOBALS['phasePost'];
    $stale = post_payload($id);
    assert_same(303, $postOwner->post("/admin/posts/$id/edit", post_payload($id, ['slug' => 'phase-four-renamed', 'published_at' => '2026-09-14T10:00']))['status']);
    assert_same('/2026/09/14/phase-four-renamed/', Db::value('SELECT url FROM posts WHERE id = ?', [$id]));
    assert_same('/2026/09/14/phase-four-renamed/', Db::value('SELECT to_path FROM redirects WHERE from_path = ?', ['/2026/09/13/phase-four-post/']));
    assert_same(409, $postOwner->post("/admin/posts/$id/edit", $stale)['status']);
    assert_same('phase-four-renamed', Db::value('SELECT slug FROM posts WHERE id = ?', [$id]));
});

test('posts reject invalid input with 422 and preserve input safely', function () use ($postOwner) {
    foreach ([['title' => ''], ['title' => str_repeat('x', 256)], ['slug' => 'bad_slug'], ['status' => 'bad'], ['published_at' => '2026-02-30T12:30'], ['published_at' => 'bad'], ['excerpt' => str_repeat('x', 501)], ['image_id' => '99999999'], ['seo_title' => str_repeat('x', 256)], ['seo_description' => str_repeat('x', 501)], ['slug' => 'phase-four-renamed', 'published_at' => '2026-09-14T10:00']] as $invalid) {
        assert_same(422, $postOwner->post('/admin/posts/new', post_fields($invalid))['status']);
    }
    $response = $postOwner->post('/admin/posts/new', post_fields(['slug' => 'bad slug', 'content_html' => '<script>alert(1)</script>']));
    assert_contains('&lt;script&gt;', $response['body']);
    assert_not_contains('<script>alert(1)</script>', $response['body']);
    assert_same(404, $postOwner->get('/admin/posts/99999999/edit')['status']);
});

test('scheduled posts stay out of exports until their publish date and show scheduled tag', function () use ($postOwner) {
    $future = date('Y-m-d\TH:i', time() + 86400);
    assert_same(303, $postOwner->post('/admin/posts/new', post_fields(['slug' => 'phase-four-scheduled', 'published_at' => $future]))['status']);
    $id = (int) Db::value("SELECT id FROM posts WHERE slug = 'phase-four-scheduled'");
    assert_true(!in_array($id, array_column(Exporter::build()['posts'], 'id'), true));
    assert_contains('scheduled', $postOwner->get('/admin/posts?q=phase-four-scheduled')['body']);
    $past = date('Y-m-d\TH:i', time() - 86400);
    assert_same(303, $postOwner->post("/admin/posts/$id/edit", post_payload($id, ['published_at' => $past]))['status']);
    assert_true(in_array($id, array_column(Exporter::build()['posts'], 'id'), true));
    assert_same(303, $postOwner->post('/admin/posts/new', post_fields(['slug' => 'phase-four-past-insert', 'published_at' => $past]))['status']);
    assert_true(in_array('phase-four-past-insert', array_column(Exporter::build()['posts'], 'slug'), true));
});

test('post permissions enforce sales refusal and SEO edit without create or delete', function () use ($base) {
    $id = $GLOBALS['phasePost'];
    foreach (['sales', 'seo'] as $role) {
        $browser = new Browser($base); $browser->get('/admin/login');
        assert_same(303, $browser->post('/admin/login', ['email' => "phase-$role@example.com", 'password' => 'phase-password-123'])['status']);
        $allowed = $role === 'seo';
        assert_same($allowed ? 200 : 403, $browser->get('/admin/posts')['status']);
        assert_same($allowed ? 200 : 403, $browser->get("/admin/posts/$id/edit")['status']);
        assert_same(403, $browser->get('/admin/posts/new')['status']);
        assert_same(403, $browser->post('/admin/posts/new', post_fields())['status']);
        assert_same($allowed ? 303 : 403, $browser->post("/admin/posts/$id/edit", post_payload($id))['status']);
        assert_same(403, $browser->get("/admin/posts/$id/delete")['status']);
        assert_same(403, $browser->post("/admin/posts/$id/delete", ['redirect_to' => '/blog/'])['status']);
    }
});

test('post list searches literally paginates 50 and shows source labels', function () use ($postOwner) {
    $ids = [];
    try {
        for ($i = 1; $i <= 51; $i++) $ids[] = Db::insert('posts', ['title' => "Paging post $i", 'slug' => "paging-post-$i", 'url' => "/2026/09/01/paging-post-$i/", 'content_html' => '', 'source' => $i === 1 ? 'import' : 'api:demo']);
        assert_same(50, substr_count($postOwner->get('/admin/posts?q=Paging')['body'], '<td>Paging post '));
        assert_same(1, substr_count($postOwner->get('/admin/posts?q=Paging&page=2')['body'], '<td>Paging post '));
        assert_contains('import', $postOwner->get('/admin/posts?q=paging-post-1')['body']);
        assert_contains('api:demo', $postOwner->get('/admin/posts?q=paging-post-2')['body']);
        assert_same(0, substr_count($postOwner->get('/admin/posts?q=%25')['body'], '<td>Paging post '));
    } finally { foreach ($ids as $id) Db::run('DELETE FROM posts WHERE id = ?', [$id]); }
});

test('post deletion validates target creates redirect and keeps media', function () use ($postOwner) {
    assert_same(303, $postOwner->post('/admin/posts/new', post_fields(['slug' => 'phase-delete-post', 'image_id' => (string) $GLOBALS['phasePhotos'][0]]))['status']);
    $row = Db::one("SELECT * FROM posts WHERE slug = 'phase-delete-post'"); $id = (int) $row['id'];
    assert_contains('/blog/', $postOwner->get("/admin/posts/$id/delete")['body']);
    assert_same(422, $postOwner->post("/admin/posts/$id/delete", ['redirect_to' => 'javascript:alert(1)'])['status']);
    assert_same(303, $postOwner->post("/admin/posts/$id/delete", ['redirect_to' => '/blog/'])['status']);
    assert_same(null, Db::value('SELECT id FROM posts WHERE id = ?', [$id]));
    assert_same('/blog/', Db::value('SELECT to_path FROM redirects WHERE from_path = ?', [$row['url']]));
    assert_true(Db::value('SELECT id FROM media WHERE id = ?', [$GLOBALS['phasePhotos'][0]]) !== null);
});
