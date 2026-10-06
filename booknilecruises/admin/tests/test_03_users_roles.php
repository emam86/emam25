<?php
declare(strict_types=1);

use Bnc\{Db, Roles};

$owner = $GLOBALS['ownerBrowser'];
$roleId = fn (string $slug): int => (int) Roles::bySlug($slug)['id'];
$userId = fn (string $email): int => (int) Db::value('SELECT id FROM users WHERE email = ?', [$email]);
$login = function (string $email, string $password) use ($base): Browser {
    $b = new Browser($base);
    $r = $b->post('/admin/login', ['email' => $email, 'password' => $password]);
    assert_same(303, $r['status'], "login $email");
    return $b;
};

test('owner adds an admin and an editor', function () use ($owner, $roleId) {
    foreach ([['admin@example.com', 'admin'], ['editor@example.com', 'editor']] as [$email, $slug]) {
        $r = $owner->post('/admin/users/new', ['name' => ucfirst($slug), 'email' => $email, 'password' => 'password-' . $slug . '-1', 'role_id' => $roleId($slug), 'is_active' => '1']);
        assert_same(303, $r['status'], "create $slug");
    }
    assert_same(3, (int) Db::value('SELECT COUNT(*) FROM users'));
});

test('user form validates input', function () use ($owner, $roleId) {
    $r = $owner->post('/admin/users/new', ['name' => '', 'email' => 'admin@example.com', 'password' => 'short', 'role_id' => $roleId('editor')]);
    assert_same(422, $r['status']);
    assert_contains('الإيميل مستخدم بالفعل', $r['body']);
    assert_contains('10 حروف', $r['body']);
});

test('editor cannot reach user management', function () use ($login) {
    $ed = $login('editor@example.com', 'password-editor-1');
    assert_same(403, $ed->get('/admin/users')['status']);
    assert_same(403, $ed->post('/admin/users/new', ['name' => 'x'])['status']);
    assert_not_contains('/admin/users"', $ed->get('/admin/')['body'], 'menu hides users');
});

test('admin cannot create owners or touch owner accounts', function () use ($login, $roleId, $userId) {
    $ad = $login('admin@example.com', 'password-admin-1');
    $r = $ad->post('/admin/users/new', ['name' => 'Sneaky', 'email' => 'sneaky@example.com', 'password' => 'password-sneaky', 'role_id' => $roleId('owner'), 'is_active' => '1']);
    assert_same(422, $r['status']);
    assert_same(0, (int) Db::value("SELECT COUNT(*) FROM users WHERE email = 'sneaky@example.com'"));
    $ownerId = $userId('owner@example.com');
    assert_same(403, $ad->get("/admin/users/$ownerId/edit")['status']);
    assert_same(403, $ad->post("/admin/users/$ownerId/delete")['status']);
    assert_same(403, $ad->get('/admin/roles/' . $roleId('owner') . '/edit')['status']);
});

test('admin cannot grant permissions they do not hold', function () use ($owner, $login, $roleId) {
    // Take posts.edit away from the admin role, then let the admin try to build a role with it.
    $perms = array_values(array_diff(Roles::bySlug('admin')['permissions'], ['posts.edit']));
    $r = $owner->post('/admin/roles/' . $roleId('admin') . '/edit', ['name' => 'مدير', 'permissions' => $perms]);
    assert_same(303, $r['status']);
    $ad = $login('admin@example.com', 'password-admin-1');
    $r = $ad->post('/admin/roles/new', ['name' => 'Publisher', 'permissions' => ['posts.edit', 'trips.view']]);
    assert_same(303, $r['status']);
    assert_same(['trips.view'], Roles::all()[5]['permissions'] ?? null, 'posts.edit was stripped');
    // The editor role has posts.edit, which the admin lacks, so the admin can't edit it or assign it.
    assert_same(403, $ad->get('/admin/roles/' . $roleId('editor') . '/edit')['status']);
    $r = $ad->post('/admin/users/new', ['name' => 'E2', 'email' => 'e2@example.com', 'password' => 'password-e2-123', 'role_id' => $roleId('editor'), 'is_active' => '1']);
    assert_same(422, $r['status']);
});

test('the last owner cannot be demoted, deactivated or deleted', function () use ($owner, $roleId, $userId) {
    $id = $userId('owner@example.com');
    $r = $owner->post("/admin/users/$id/edit", ['name' => 'Owner', 'email' => 'owner@example.com', 'password' => '', 'role_id' => $roleId('owner')]);
    assert_same(422, $r['status'], 'deactivate self');
    $r = $owner->post("/admin/users/$id/delete");
    assert_same(303, $r['status']);
    assert_same(1, (int) Db::value('SELECT COUNT(*) FROM users WHERE id = ?', [$id]));
});

test('roles in use cannot be deleted; empty custom roles can', function () use ($owner, $roleId) {
    $owner->post('/admin/roles/' . $roleId('editor') . '/delete');
    assert_true(Roles::bySlug('editor') !== null);
    $roles = Roles::all();
    $custom = end($roles);
    $owner->post("/admin/roles/{$custom['id']}/delete");
    assert_same(null, Roles::find((int) $custom['id']));
});

test('deactivating a user ends their session; password change ends other sessions', function () use ($owner, $login, $roleId, $userId) {
    $ed = $login('editor@example.com', 'password-editor-1');
    assert_same(200, $ed->get('/admin/')['status']);
    $id = $userId('editor@example.com');
    $owner->post("/admin/users/$id/edit", ['name' => 'Editor', 'email' => 'editor@example.com', 'password' => '', 'role_id' => $roleId('editor')]);
    assert_same(303, $ed->get('/admin/')['status'], 'kicked out after deactivation');

    $owner->post("/admin/users/$id/edit", ['name' => 'Editor', 'email' => 'editor@example.com', 'password' => '', 'role_id' => $roleId('editor'), 'is_active' => '1']);
    $a = $login('editor@example.com', 'password-editor-1');
    $b = $login('editor@example.com', 'password-editor-1');
    $r = $a->post('/admin/account', ['name' => 'Editor', 'current_password' => 'password-editor-1', 'new_password' => 'password-editor-2']);
    assert_same(303, $r['status']);
    assert_same(200, $a->get('/admin/account')['status'], 'own session survives');
    assert_same(303, $b->get('/admin/account')['status'], 'other session ended');
});

test('every change is in the audit log, with the actor', function () use ($owner) {
    $page = $owner->get('/admin/audit?entity=user');
    assert_same(200, $page['status']);
    assert_contains('admin@example.com', $page['body']);
    assert_true((int) Db::value("SELECT COUNT(*) FROM audit_log WHERE action = 'create' AND entity = 'user'") >= 2);
    assert_true((int) Db::value("SELECT COUNT(*) FROM audit_log WHERE actor = 'admin@example.com' AND entity = 'role'") >= 1);
});

test('output is escaped', function () use ($owner, $roleId) {
    $owner->post('/admin/users/new', ['name' => '<img src=x onerror=alert(1)>', 'email' => 'xss@example.com', 'password' => 'password-xss-12', 'role_id' => $roleId('sales'), 'is_active' => '1']);
    $page = $owner->get('/admin/users');
    assert_not_contains('<img src=x', $page['body']);
    assert_contains('&lt;img src=x', $page['body']);
});
