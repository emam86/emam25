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

test('public post saves reclaim redirects on creation publication restoration and unchanged URLs', function () use ($postOwner) {
    $path = '/2021/01/01/reclaim-post/';
    Db::insert('redirects', ['from_path' => $path, 'to_path' => '/blog/']);
    assert_same(303, $postOwner->post('/admin/posts/new', post_fields(['slug' => 'reclaim-post', 'published_at' => '2021-01-01T12:00']))['status']);
    $id = (int) Db::value('SELECT id FROM posts WHERE url = ?', [$path]);
    assert_same(null, Db::value('SELECT id FROM redirects WHERE from_path = ?', [$path]));
    foreach (['draft', 'published', 'published'] as $status) {
        Db::run('DELETE FROM redirects WHERE from_path = ?', [$path]);
        Db::insert('redirects', ['from_path' => $path, 'to_path' => '/blog/']);
        assert_same(303, $postOwner->post("/admin/posts/$id/edit", post_payload($id, ['status' => $status]))['status']);
        assert_same($status === 'draft' ? '/blog/' : null, Db::value('SELECT to_path FROM redirects WHERE from_path = ?', [$path]));
    }
});

test('post unpublishing and scheduling with changed URLs create no automatic redirects', function () use ($postOwner) {
    foreach (['draft', 'scheduled'] as $kind) {
        $slug = 'unpublish-' . $kind;
        assert_same(303, $postOwner->post('/admin/posts/new', post_fields(['slug' => $slug, 'published_at' => '2020-02-01T12:00']))['status']);
        $id = (int) Db::value('SELECT id FROM posts WHERE slug = ?', [$slug]);
        $old = post_payload($id);
        $changes = ['slug' => $slug . '-new', 'status' => $kind === 'draft' ? 'draft' : 'published'];
        if ($kind === 'scheduled') $changes['published_at'] = date('Y-m-d\TH:i', time() + 86400);
        assert_same(303, $postOwner->post("/admin/posts/$id/edit", post_payload($id, $changes))['status']);
        assert_same(null, Db::value('SELECT id FROM redirects WHERE from_path = ?', [$old['url']]));
    }
});

test('announcement claim preserves the post edit token and permits a previously opened edit', function () use ($postOwner) {
    $id = Db::insert('posts', ['title' => 'Announcement token', 'content_html' => '<p>Body</p>', 'slug' => 'announcement-token', 'url' => '/2021/01/01/announcement-token/', 'status' => 'published', 'published_at' => '2021-01-01 12:00:00', 'updated_at' => '2021-01-01 00:00:00']);
    $before = post_payload($id);
    assert_true(\Bnc\Posts\Announcement::claim($id));
    assert_same($before['updated_at'], Db::value('SELECT updated_at FROM posts WHERE id = ?', [$id]));
    assert_same(303, $postOwner->post("/admin/posts/$id/edit", array_replace($before, ['image_id' => '', 'title' => 'Edited after announcement']))['status']);
});

test('API allocates unique dated URLs and preserves unchanged slugs shared across dates', function () {
    $key = ['name' => 'review', 'scopes' => ['posts.write', 'posts.publish']];
    $ids = [];
    try {
        foreach (['2020-03-01', '2020-03-02', '2020-03-01'] as $date) {
            $r = \Bnc\Api\Posts::save(['title' => 'Shared slug', 'content_html' => '<p>Body</p>', 'slug' => 'review-shared', 'published_at' => $date . 'T12:00:00Z'], $key);
            $ids[] = $r['id'];
        }
        assert_same('review-shared', Db::value('SELECT slug FROM posts WHERE id = ?', [$ids[1]]));
        assert_same('review-shared-2', Db::value('SELECT slug FROM posts WHERE id = ?', [$ids[2]]));
        \Bnc\Api\Posts::save(['title' => 'Title only'], $key, $ids[0]);
        assert_same('review-shared', Db::value('SELECT slug FROM posts WHERE id = ?', [$ids[0]]));
    } finally { foreach ($ids as $id) Db::run('DELETE FROM posts WHERE id = ?', [$id]); }
});

test('draft post deletion hides redirect input ignores invalid target and omits redirect audit detail', function () use ($postOwner) {
    assert_same(303, $postOwner->post('/admin/posts/new', post_fields(['slug' => 'delete-review-draft', 'status' => 'draft']))['status']);
    $id = (int) Db::value("SELECT id FROM posts WHERE slug = 'delete-review-draft'");
    assert_not_contains('name="redirect_to"', $postOwner->get("/admin/posts/$id/delete")['body']);
    assert_same(303, $postOwner->post("/admin/posts/$id/delete", ['redirect_to' => 'invalid'])['status']);
    $details = json_decode(Db::value("SELECT details FROM audit_log WHERE entity = 'post' AND entity_id = ? AND action = 'delete'", [$id]) ?? 'null', true);
    assert_true(!isset($details['redirect_to']));
});

test('panel post saves wait for the API URL allocation lock before reading posts', function () {
    $id = (int) Db::value('SELECT id FROM posts LIMIT 1');
    $fields = post_payload($id, ['updated_at' => 'stale-token']);
    Db::run("INSERT IGNORE INTO api_locks (name) VALUES ('posts')");
    $connection = Db::pdo();
    $connection->beginTransaction();
    Db::one("SELECT name FROM api_locks WHERE name = 'posts' FOR UPDATE");
    try {
        review_count_queries(function () use ($fields, $id) {
            Db::run('SET SESSION innodb_lock_wait_timeout = 1');
            try {
                \Bnc\Posts\PostRepository::save($fields, $id);
                throw new AssertionFailed('panel bypassed API allocation lock');
            } catch (PDOException $e) {
                assert_same(1205, (int) $e->errorInfo[1]);
            }
        });
    } finally { $connection->rollBack(); }
    assert_same(null, \Bnc\Posts\PostRepository::save($fields, $id));
});

test('API title-only updates keep slugs already shared by posts on different dates', function () {
    $ids = [];
    try {
        foreach (['2021-04-01', '2021-04-02'] as $date) $ids[] = Db::insert('posts', ['title' => 'Shared', 'content_html' => '<p>Body</p>', 'slug' => 'review-existing-shared', 'url' => str_replace('-', '/', '/' . $date) . '/review-existing-shared/', 'published_at' => $date . ' 12:00:00', 'source' => 'api:review']);
        $before = post_payload($ids[0]);
        \Bnc\Api\Posts::save(['title' => 'Title changed'], ['name' => 'review', 'scopes' => ['posts.write']], $ids[0]);
        $after = post_payload($ids[0]);
        assert_same('Title changed', $after['title']);
        assert_same($before['slug'], $after['slug']);
        assert_same($before['url'], $after['url']);
    } finally { foreach ($ids as $id) Db::run('DELETE FROM posts WHERE id = ?', [$id]); }
});

test('legacy public posts without a publish date still redirect when their URL moves', function () use ($postOwner) {
    $id = Db::insert('posts', ['title' => 'Legacy public', 'content_html' => '<p>Body</p>', 'slug' => 'review-null-date', 'url' => '/review-null-date/', 'status' => 'published', 'published_at' => null]);
    try {
        assert_same(303, $postOwner->post("/admin/posts/$id/edit", post_payload($id, ['image_id' => '', 'slug' => 'review-null-date-new', 'published_at' => '2021-05-01T12:00']))['status']);
        assert_same('/2021/05/01/review-null-date-new/', Db::value('SELECT to_path FROM redirects WHERE from_path = ?', ['/review-null-date/']));
    } finally {
        Db::run('DELETE FROM posts WHERE id = ?', [$id]);
        Db::run('DELETE FROM redirects WHERE from_path = ?', ['/review-null-date/']);
    }
});
